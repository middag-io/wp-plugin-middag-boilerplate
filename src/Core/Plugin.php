<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Core;

use Middag\Framework\Kernel\ContainerFactory;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\Framework\Kernel\HostContext;
use Middag\Framework\Translation\Contract\TranslatorInterface;
use Middag\WordPress\Admin\AdminRouteRegistrar;
use Middag\WordPress\Admin\MenuPage;
use Middag\WordPress\Cron\CronHandler;
use Middag\WordPress\Cron\CronRegistrar;
use Middag\WordPress\Cron\Enum\CronInterval;
use Middag\WordPress\Database\WpdbConnectionAdapter;
use Middag\WordPress\Definition\DefinitionRegistrar;
use Middag\WordPress\Definition\PostTypeDefinition;
use Middag\WordPress\Hook\HookRegistrar;
use Middag\WordPress\Http\Inertia\InertiaAdapter;
use Middag\WordPress\Http\Routing\RestRouteRegistrar;
use Middag\WordPress\Http\Routing\Router;
use Middag\WordPress\Runtime\WpComponentContext;
use Middag\WordPress\Security\Enum\WpCapability;
use Middag\WpBoilerplate\Api\V1\WidgetController;
use Middag\WpBoilerplate\Domain\Widget\SyncWidgetsService;
use Middag\WpBoilerplate\UI\WidgetPageController;
use Middag\WpBoilerplate\WordPress\Schema\SchemaInstaller;
use Middag\WpBoilerplate\WordPress\Settings\WidgetSettings;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Runtime composition root: builds the DI container once on `plugins_loaded`
 * and wires the plugin's WordPress hooks off the compiled container.
 *
 * Kept deliberately thin — no business logic. Each subsystem (REST, admin,
 * cron, settings, CPT, hooks) is wired in {@see self::registerHooks()} by
 * resolving its collaborators from the container built here.
 */
final class Plugin
{
    private static ?ContainerBuilder $container = null;

    /**
     * Boot the plugin: identity → container → hook wiring.
     *
     * @param string $pluginFile absolute path to the plugin main file
     */
    public static function boot(string $pluginFile): void
    {
        // ServiceProvider strips "{projectRoot}/src/" from scanned paths, so the
        // root must carry no trailing slash.
        $projectRoot = rtrim(plugin_dir_path($pluginFile), '/\\');

        $context = new WpComponentContext(
            componentName: 'middag-boilerplate',
            assetVersion: \MIDDAG_BOILERPLATE_VERSION,
            basePath: plugin_dir_path($pluginFile),
        );

        // DI is the primary path; HostContext is the static fallback the libs read
        // when a collaborator was constructed outside the container.
        HostContext::set($context);

        /** @var \wpdb $wpdb */
        global $wpdb;

        $factory = new ContainerFactory();
        // Runtime globals become synthetic instances, injected after compile.
        $factory->addSynthetic('wordpress.wpdb', $wpdb);
        $factory->addSynthetic(HostComponentContextInterface::class, $context);

        self::$container = $factory->build(new AppBootstrap($projectRoot), [], $projectRoot);

        self::registerHooks(self::$container, $context, plugin_dir_url($pluginFile) . 'assets/dist/');
    }

    public static function container(): ?ContainerBuilder
    {
        return self::$container;
    }

    /**
     * Wire the plugin's WordPress hooks from the compiled container.
     *
     * Grows one subsystem at a time as the layers land (REST route registrar,
     * admin route registrar, cron, settings page, CPT, hook registrar).
     */
    private static function registerHooks(ContainerBuilder $container, HostComponentContextInterface $context, string $assetsBaseUrl): void
    {
        $hookDir = rtrim((string) $context->basePath(), '/\\') . '/src/WordPress/Hook';

        add_action('init', static function () use ($container, $context, $hookDir): void {
            // Declarative CPT registration — the wp-adapter's DefinitionRegistrar
            // wires register_post_type from a typed PostTypeDefinition. Distinct
            // from the custom-table Widget: this shows native WP content storage.
            (new DefinitionRegistrar())->register([
                new PostTypeDefinition('mdgbp_note', 'Note', 'Notes', args: [
                    'public' => true,
                    'show_in_rest' => true,
                    'supports' => ['title', 'editor'],
                    'menu_icon' => 'dashicons-sticky',
                ]),
            ]);

            // Discover + register every *Hooks class under src/WordPress/Hook,
            // resolving each from the container so hooks can take dependencies.
            (new HookRegistrar($container, 'Middag\\WpBoilerplate\\WordPress\\Hook\\', $hookDir))->register();

            // Schedule the hourly sync via the typed cron API. The handler is a
            // closure that lazily resolves SyncWidgetsService from the container
            // when WP-Cron fires the event — typed interval, no magic strings.
            /** @var TranslatorInterface $translator */
            $translator = $container->get(TranslatorInterface::class);
            $cron = new CronRegistrar($context, $translator);
            $cron->addEvent(
                'mdgbp_sync',
                CronInterval::Hourly,
                CronHandler::dispatch($container, SyncWidgetsService::class, 'run'),
            );
            $cron->register();
        });

        // REST: the registrar resolves each controller from the container and
        // registers its routes under the derived namespace (middag-boilerplate/v1).
        add_action('rest_api_init', static function () use ($container, $context): void {
            $registrar = new RestRouteRegistrar($container, $context);
            $registrar->addController(WidgetController::class);
            $registrar->register();
        });

        if (is_admin()) {
            self::registerAdmin($container, $context, $assetsBaseUrl);
        }
    }

    /**
     * Wire the Inertia admin surface: one menu page whose routes dispatch to the
     * page controller, plus the asset enqueue gated on the page hook suffix.
     */
    private static function registerAdmin(ContainerBuilder $container, HostComponentContextInterface $context, string $assetsBaseUrl): void
    {
        $router = new Router();
        $router->get('/', [WidgetPageController::class, 'index']);
        $router->post('/widgets', [WidgetPageController::class, 'store']);

        $admin = new AdminRouteRegistrar(
            $context,
            $router,
            $container,
            static function (string $page, string $path): void {},
        );

        $menu = new MenuPage(
            'MIDDAG Boilerplate',
            'MIDDAG Boilerplate',
            WpCapability::ManageOptions,
            'dashicons-screenoptions',
        );

        add_action('admin_menu', static function () use ($admin, $menu): void {
            $admin->register($menu, []);
        });
        add_action('admin_init', [$admin, 'handleInertiaRequest']);
        add_action('admin_enqueue_scripts', static function (string $hookSuffix) use ($admin, $container, $assetsBaseUrl): void {
            if (!in_array($hookSuffix, $admin->pageHookSuffixes(), true)) {
                return;
            }

            /** @var InertiaAdapter $inertia */
            $inertia = $container->get(InertiaAdapter::class);
            $inertia->enqueueAssets(
                'middag-boilerplate',
                $assetsBaseUrl . 'app.js',
                $assetsBaseUrl . 'style.css',
            );
        });

        // Native (non-React) settings page under the SAME plugin menu — proves
        // the plugin is not 100% Inertia. Registered with a plain submenu whose
        // callback renders the WordPress Settings API form.
        $settings = new WidgetSettings();
        add_action('admin_init', [$settings, 'register']);
        add_action('admin_menu', static function () use ($admin, $settings): void {
            add_submenu_page(
                $admin->menuSlug(),
                __('MIDDAG Boilerplate Settings', 'middag-boilerplate'),
                __('Settings', 'middag-boilerplate'),
                WpCapability::ManageOptions->value,
                WidgetSettings::PAGE,
                [$settings, 'renderPage'],
            );
        });
    }

    /**
     * Plugin activation: create the widget table (idempotent) and flush rewrite
     * rules so the CPT's routes resolve. Runs independently of the container.
     */
    public static function activate(): void
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        (new SchemaInstaller())->install(new WpdbConnectionAdapter($wpdb));

        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        // Clear the scheduled sync so it does not survive deactivation.
        wp_clear_scheduled_hook('mdgbp_sync');

        flush_rewrite_rules();
    }
}
