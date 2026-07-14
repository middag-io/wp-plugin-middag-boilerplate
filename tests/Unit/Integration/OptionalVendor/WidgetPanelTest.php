<?php

declare(strict_types=1);

/*
 * PEGADINHA #1 under test — the glue that couples to a base/registry provided
 * ONLY by an optional third-party plugin. On a real test host that vendor is
 * absent, so here we stand up minimal fakes for its two symbols
 * (`OptionalVendor\Widgets\PanelBase` and `...\PanelRegistry`) BEFORE the glue
 * autoloads. This mirrors what the runtime guard in OptionalVendorHooks proves
 * is present before ever instantiating the glue.
 */

namespace OptionalVendor\Widgets {
    if (!class_exists(PanelBase::class, false)) {
        /** Minimal fake of the vendor base the extension extends. */
        class PanelBase
        {
            public function __construct(public readonly string $slug) {}
        }
    }

    if (!class_exists(PanelRegistry::class, false)) {
        /** Minimal fake of the vendor registry, capturing registrations. */
        class PanelRegistry
        {
            /** @var list<PanelBase> */
            public static array $registered = [];

            public static function register(PanelBase $panel): void
            {
                self::$registered[] = $panel;
            }
        }
    }
}

namespace Middag\WpBoilerplate\Tests\Unit\Integration\OptionalVendor {
    use DateTimeImmutable;
    use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
    use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
    use Middag\WpBoilerplate\Domain\Widget\Widget;
    use Middag\WpBoilerplate\Domain\Widget\WidgetService;
    use Middag\WpBoilerplate\Integration\OptionalVendor\WidgetPanelExtension;
    use Middag\WpBoilerplate\Integration\OptionalVendor\WidgetPanelRegistrar;
    use OptionalVendor\Widgets\PanelBase;
    use OptionalVendor\Widgets\PanelRegistry;
    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\TestCase;
    use Psr\Clock\ClockInterface;

    #[CoversClass(WidgetPanelExtension::class)]
    #[CoversClass(WidgetPanelRegistrar::class)]
    final class WidgetPanelTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            PanelRegistry::$registered = [];
        }

        public function testExtensionPassesTheStableSlugToTheVendorBase(): void
        {
            $extension = new WidgetPanelExtension($this->widgetService());

            self::assertInstanceOf(PanelBase::class, $extension);
            self::assertSame('mdgbp_widgets', $extension->slug);
        }

        public function testContentReportsTheNumberOfActiveWidgets(): void
        {
            $extension = new WidgetPanelExtension($this->widgetService(
                new Widget(1, 'Alpha', WidgetStatus::Active, '2026-01-01 00:00:00'),
                new Widget(2, 'Beta', WidgetStatus::Active, '2026-01-02 00:00:00'),
            ));

            self::assertSame('Active widgets: 2', $extension->content());
        }

        public function testContentReportsZeroWhenNoWidgetsAreActive(): void
        {
            $extension = new WidgetPanelExtension($this->widgetService());

            self::assertSame('Active widgets: 0', $extension->content());
        }

        public function testRegistrarRegistersAConfiguredExtensionWithTheVendorRegistry(): void
        {
            $widgets = $this->widgetService(
                new Widget(1, 'Alpha', WidgetStatus::Active, '2026-01-01 00:00:00'),
            );

            (new WidgetPanelRegistrar($widgets))->register();

            self::assertCount(1, PanelRegistry::$registered);
            $panel = PanelRegistry::$registered[0];
            self::assertInstanceOf(WidgetPanelExtension::class, $panel);
            self::assertSame('mdgbp_widgets', $panel->slug);
            self::assertSame('Active widgets: 1', $panel->content());
        }

        private function widgetService(Widget ...$active): WidgetService
        {
            $repository = new class(array_values($active)) implements WidgetRepositoryInterface {
                /** @param list<Widget> $active */
                public function __construct(private readonly array $active) {}

                public function store(Widget $widget): void {}

                public function ofId(int $id): ?Widget
                {
                    return null;
                }

                public function allActive(): array
                {
                    return $this->active;
                }
            };

            $clock = new class implements ClockInterface {
                public function now(): DateTimeImmutable
                {
                    return new DateTimeImmutable('2026-07-14 12:00:00');
                }
            };

            return new WidgetService($repository, $clock);
        }
    }
}
