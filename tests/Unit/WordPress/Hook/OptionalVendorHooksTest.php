<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\WordPress\Hook;

use DateTimeImmutable;
use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;
use Middag\WpBoilerplate\Integration\OptionalVendor\WidgetPanelExtension;
use Middag\WpBoilerplate\WordPress\Hook\OptionalVendorHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/**
 * The guard is a language builtin (`class_exists` on a STRING vendor name), not
 * a WordPress function, so the two branches are exercised by controlling the
 * REAL presence of the fictional vendor base — exactly as a live site would:
 *
 *  - absent  → the vendor plugin was never loaded, so the hook short-circuits
 *              before ever touching the vendor-coupled glue;
 *  - present → the vendor stubs exist first (mirroring the optional plugin being
 *              active), so the registrar wires the panel into the vendor registry.
 *
 * BOTH cases run {@see RunInSeparateProcess}: the sibling WidgetPanelTest defines
 * `OptionalVendor\Widgets\PanelBase`/`PanelRegistry` at file-load time, so in the
 * shared suite process the vendor is ALWAYS present. Isolation is what lets this
 * test observe the "absent" branch, and keeps the stubs it defines from leaking.
 */
#[CoversClass(OptionalVendorHooks::class)]
final class OptionalVendorHooksTest extends TestCase
{
    /**
     * Spy for panels the stubbed vendor registry receives. Static so the eval'd
     * registry can push into it by FQCN without this test naming a vendor symbol.
     *
     * @var list<object>
     */
    public static array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::$captured = [];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegisterShortCircuitsWhenTheVendorBaseIsAbsent(): void
    {
        // Precondition: on a plain site the optional plugin is not loaded.
        self::assertFalse(
            class_exists('OptionalVendor\Widgets\PanelBase', false),
            'The vendor base must be absent for the short-circuit branch.'
        );

        (new OptionalVendorHooks($this->widgetService(0)))->register();

        // The guard returned before the registrar ran, so the vendor-coupled
        // registry was never even autoloaded.
        self::assertFalse(
            class_exists('OptionalVendor\Widgets\PanelRegistry', false),
            'The guard must short-circuit before the registrar touches the vendor.'
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRegisterWiresThePanelWhenTheVendorBaseIsPresent(): void
    {
        // Stand in for the optional third-party plugin being active: the base
        // the glue extends and the registry it registers into now both exist.
        // The registry captures panels into this test's static spy so nothing
        // outside the stub definition itself has to name a vendor symbol.
        eval(<<<'PHP'
            namespace OptionalVendor\Widgets;

            class PanelBase
            {
                public function __construct(public readonly string $slug) {}
            }

            class PanelRegistry
            {
                public static function register(object $panel): void
                {
                    \Middag\WpBoilerplate\Tests\Unit\WordPress\Hook\OptionalVendorHooksTest::$captured[] = $panel;
                }
            }
            PHP);

        (new OptionalVendorHooks($this->widgetService(2)))->register();

        $registered = self::$captured;
        self::assertCount(1, $registered);

        $panel = $registered[0];
        self::assertInstanceOf(WidgetPanelExtension::class, $panel);
        // The container-resolved WidgetService was threaded all the way through
        // the registrar into the hand-instantiated vendor glue.
        self::assertSame('Active widgets: 2', $panel->content());
    }

    private function widgetService(int $activeCount): WidgetService
    {
        $active = [];
        for ($i = 1; $i <= $activeCount; ++$i) {
            $active[] = new Widget($i, 'Widget ' . $i, WidgetStatus::Active, '2026-01-01 00:00:00');
        }

        $repository = new class($active) implements WidgetRepositoryInterface {
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
                return new DateTimeImmutable('2026-01-01 00:00:00');
            }
        };

        return new WidgetService($repository, $clock);
    }
}
