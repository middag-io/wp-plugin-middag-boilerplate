<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Core;

use Middag\Framework\Kernel\ContainerFactory;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\Framework\Kernel\HostContext;
use Middag\WordPress\Database\WpdbConnectionAdapter;
use Middag\WordPress\Definition\DefinitionRegistrar;
use Middag\WordPress\Definition\PostTypeDefinition;
use Middag\WordPress\Hook\HookRegistrar;
use Middag\WordPress\Http\Routing\RestRouteRegistrar;
use Middag\WordPress\Runtime\WpComponentContext;
use Middag\WpBoilerplate\Api\V1\WidgetController;
use Middag\WpBoilerplate\WordPress\Schema\SchemaInstaller;
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

        self::registerHooks(self::$container, $context);
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
    private static function registerHooks(ContainerBuilder $container, HostComponentContextInterface $context): void
    {
        $hookDir = rtrim((string) $context->basePath(), '/\\') . '/src/WordPress/Hook';

        add_action('init', static function () use ($container, $hookDir): void {
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
        });

        // REST: the registrar resolves each controller from the container and
        // registers its routes under the derived namespace (middag-boilerplate/v1).
        add_action('rest_api_init', static function () use ($container, $context): void {
            $registrar = new RestRouteRegistrar($container, $context);
            $registrar->addController(WidgetController::class);
            $registrar->register();
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
        flush_rewrite_rules();
    }
}
