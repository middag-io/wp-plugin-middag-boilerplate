<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Core;

use Middag\Framework\Kernel\ContainerFactory;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\Framework\Kernel\HostContext;
use Middag\WordPress\Runtime\WpComponentContext;
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
        // Subsystem wiring is added by the WordPress/Api/UI layers.
    }
}
