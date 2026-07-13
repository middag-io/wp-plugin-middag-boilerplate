<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Integration\Core;

use Middag\Framework\Kernel\ContainerFactory;
use Middag\Framework\Kernel\Contract\HostComponentContextInterface;
use Middag\WpBoilerplate\Api\V1\WidgetController;
use Middag\WpBoilerplate\Core\AppBootstrap;
use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;
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
    public function test_container_compiles_and_discovers_the_plugin_services(): void
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
