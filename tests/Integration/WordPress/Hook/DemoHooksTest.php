<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Integration\WordPress\Hook;

use DateTimeImmutable;
use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;
use Middag\WpBoilerplate\WordPress\Hook\DemoHooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(DemoHooks::class)]
final class DemoHooksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        setUp();
    }

    protected function tearDown(): void
    {
        tearDown();
        parent::tearDown();
    }

    public function testRegisterWiresTheActiveWidgetCountFilter(): void
    {
        $hooks = $this->hooks();

        /** @var list<array{0: string, 1: callable}> $registered */
        $registered = [];
        when('add_filter')->alias(function (string $hook, callable $callback) use (&$registered): void {
            $registered[] = [$hook, $callback];
        });

        $hooks->register();

        self::assertSame([['middag_boilerplate/active_widget_count', [$hooks, 'activeWidgetCount']]], $registered);
    }

    public function testActiveWidgetCountReturnsTheNumberOfActiveWidgets(): void
    {
        $hooks = $this->hooks(
            $this->widget('Alpha'),
            $this->widget('Beta'),
        );

        self::assertSame(2, $hooks->activeWidgetCount());
    }

    public function testActiveWidgetCountIsZeroWhenNoneAreActive(): void
    {
        self::assertSame(0, $this->hooks()->activeWidgetCount());
    }

    public function testActiveWidgetCountIgnoresTheIncomingFilterValue(): void
    {
        $hooks = $this->hooks($this->widget('Alpha'));

        // WordPress passes the current filter value; the hook derives the count
        // from the domain service and never trusts the incoming argument.
        self::assertSame(1, $hooks->activeWidgetCount(99));
    }

    private function hooks(Widget ...$active): DemoHooks
    {
        return new DemoHooks(new WidgetService($this->repository($active), $this->clock()));
    }

    private function widget(string $name): Widget
    {
        return new Widget(null, $name, WidgetStatus::Active, '2026-01-01 00:00:00');
    }

    /**
     * @param list<Widget> $active
     */
    private function repository(array $active): WidgetRepositoryInterface
    {
        return new class($active) implements WidgetRepositoryInterface {
            /**
             * @param list<Widget> $active
             */
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
    }

    private function clock(): ClockInterface
    {
        return new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-01-01 00:00:00');
            }
        };
    }
}
