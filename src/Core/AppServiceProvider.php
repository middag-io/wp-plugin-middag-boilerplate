<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Core;

use Middag\Framework\Kernel\ServiceProvider;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;
use Middag\WpBoilerplate\Integration\RemoteCatalog\RemoteCatalogClient;
use Middag\WpBoilerplate\Integration\RemoteCatalog\RemoteCatalogClientInterface;
use Middag\WpBoilerplate\Integration\RemoteCatalog\RemoteCatalogSyncService;
use Middag\WpBoilerplate\WordPress\Hook\OptionalVendorHooks;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Convention-based service discovery for the plugin.
 *
 * Extends the framework {@see ServiceProvider} with this plugin's namespace and
 * the layer directories to scan. Discovery registers every class whose short
 * name ends in a framework REGISTER_SUFFIX (Service, Repository, Controller,
 * Hooks, ...) and auto-aliases single-interface implementations — so a
 * {@see WidgetService} and its interface
 * are wired with zero manual container calls.
 *
 * Load-bearing contract (see ServiceProvider::register): SCAN_DIRS resolve
 * relative to the project root and MUST include the `src/` segment, and
 * ROOT_NAMESPACE MUST map to the PSR-4 root of that src/ tree. Core/ is
 * intentionally excluded — the composition root is wired by hand, not
 * discovered.
 */
final class AppServiceProvider extends ServiceProvider
{
    protected const ROOT_NAMESPACE = 'Middag\WpBoilerplate';

    protected const SCAN_DIRS = [
        'src/Domain',
        'src/WordPress',
        'src/Api',
        'src/UI',
        'src/Integration',
    ];

    /**
     * The framework's ignore list PLUS the plugin's vendor-coupled subtree.
     *
     * Overriding the const REPLACES the parent's, so the framework defaults are
     * repeated here. `OptionalVendor` holds classes that `extends` a base only
     * present when a third-party plugin is active; scanning it would fatal at
     * container-build time on a site without that plugin, so discovery skips it
     * and those classes are instantiated by hand behind a guard (see
     * {@see OptionalVendorHooks}).
     */
    protected const IGNORE_DIRS = [
        'Contract', 'Entity', 'Enum', 'Exception', 'Trait',
        'ValueObject', 'DTO', 'Dto',
        'OptionalVendor',
    ];

    public static function register(ContainerBuilder $container, string $basePath): void
    {
        parent::register($container, $basePath);

        self::registerRemoteCatalog($container);
    }

    /**
     * Hand-wire the Integration `*Client` and alias its seam.
     *
     * The `Client` short name is not a discovery suffix, so it never
     * auto-registers; bind the concrete (its {@see RemoteCatalogConfigResolver}
     * collaborator is autowired) and alias the interface so consumers like
     * {@see RemoteCatalogSyncService} resolve the seam, not the concrete. This
     * is the same recipe a product uses to bind a proprietary Stripe/PSP client.
     */
    private static function registerRemoteCatalog(ContainerBuilder $container): void
    {
        $container->register(RemoteCatalogClient::class, RemoteCatalogClient::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        $container->setAlias(RemoteCatalogClientInterface::class, RemoteCatalogClient::class)
            ->setPublic(true)
        ;
    }
}
