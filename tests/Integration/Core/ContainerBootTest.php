<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Integration\Core;

use Middag\Framework\Kernel\ContainerFactory;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WpBoilerplate\Api\V1\WidgetController;
use Middag\WpBoilerplate\Core\AppBootstrap;
use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;
use Middag\WpBoilerplate\Integration\RemoteCatalog\RemoteCatalogClientInterface;
use Middag\WpBoilerplate\Integration\RemoteCatalog\RemoteCatalogConfigResolver;
use Middag\WpBoilerplate\Integration\RemoteCatalog\RemoteCatalogSyncService;
use Middag\WpBoilerplate\UI\WidgetPageController;
use Middag\WpBoilerplate\WordPress\Hook\OptionalVendorHooks;
use Middag\WpBoilerplate\WordPress\Repository\WidgetRepository;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Compiles the real composition root and asserts the container graph is valid.
 *
 * This is the verification phpstan cannot do: autowiring, convention discovery
 * and single-interface auto-aliasing only resolve at compile time. No WordPress
 * runtime is needed — the container is built (compiled) but DB-backed services
 * are not resolved, so the wpdb synthetic can be a stub.
 */
final class ContainerBootTest extends TestCase
{
    public function testContainerCompilesAndDiscoversThePluginServices(): void
    {
        $projectRoot = dirname(__DIR__, 3);

        $factory = new ContainerFactory();
        $factory->addSynthetic('wordpress.wpdb', new stdClass());
        $factory->addSynthetic(HostComponentContextInterface::class, $this->context());

        $container = $factory->build(new AppBootstrap($projectRoot), [], $projectRoot);

        // Convention discovery found the domain service and the repository.
        self::assertTrue($container->has(WidgetService::class), 'WidgetService discovered');
        self::assertTrue($container->has(WidgetRepository::class), 'WidgetRepository discovered');

        // Single-interface auto-alias wired the domain contract to the concrete repo.
        self::assertTrue($container->has(WidgetRepositoryInterface::class), 'repository auto-aliased');

        // The REST controller autowired its authenticator + domain service.
        self::assertTrue($container->has(WidgetController::class), 'WidgetController discovered + autowired');

        // The admin page controller autowired the Inertia adapter + domain service.
        self::assertTrue($container->has(WidgetPageController::class), 'WidgetPageController discovered + autowired');

        // Integration layer: the discovered *Resolver + *Service, and the
        // hand-wired *Client aliased to its seam (a Client suffix never
        // auto-registers, so this proves the manual binding in AppServiceProvider).
        self::assertTrue($container->has(RemoteCatalogConfigResolver::class), 'RemoteCatalogConfigResolver discovered');
        self::assertTrue($container->has(RemoteCatalogSyncService::class), 'RemoteCatalogSyncService discovered + autowired');
        self::assertTrue($container->has(RemoteCatalogClientInterface::class), 'RemoteCatalog client hand-aliased to its interface');

        // The vendor-guarded hook is discovered (its OptionalVendor/ glue is NOT,
        // proving IGNORE_DIRS kept the stub-less extension out of the container).
        self::assertTrue($container->has(OptionalVendorHooks::class), 'OptionalVendorHooks discovered');
    }

    public function testExposesBootstrapMetadata(): void
    {
        $projectRoot = dirname(__DIR__, 3);

        $bootstrap = new AppBootstrap($projectRoot);

        // The bootstrap declares itself as the WordPress platform seam.
        self::assertSame('wordpress', $bootstrap->platform());

        // The project root passed to the constructor is echoed back for the
        // ServiceProvider scan.
        self::assertSame($projectRoot, $bootstrap->getProjectRoot());

        // No kernel options are contributed by this composition root.
        self::assertSame([], $bootstrap->getOptions());
    }

    private function context(): HostComponentContextInterface
    {
        return new class implements HostComponentContextInterface {
            public function componentName(): string
            {
                return 'middag-boilerplate';
            }

            public function assetVersion(): string
            {
                return '0.1.0';
            }

            public function basePath(): ?string
            {
                return null;
            }
        };
    }
}
