<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\Domain\Widget;

use DateTimeImmutable;
use Middag\WpBoilerplate\Domain\Widget\Contract\WidgetRepositoryInterface;
use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use Middag\WpBoilerplate\Domain\Widget\WidgetData;
use Middag\WpBoilerplate\Domain\Widget\WidgetService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(WidgetService::class)]
final class WidgetServiceTest extends TestCase
{
    public function test_create_stamps_created_at_from_clock_and_persists(): void
    {
        $repository = $this->inMemoryRepository();
        $clock = $this->fixedClock('2026-07-13 10:00:00');
        $service = new WidgetService($repository, $clock);

        $widget = $service->create(new WidgetData('Sensor', WidgetStatus::Active));

        self::assertSame('Sensor', $widget->name());
        self::assertSame(WidgetStatus::Active, $widget->status());
        self::assertSame('2026-07-13 10:00:00', $widget->createdAt());
        self::assertCount(1, $repository->stored);
        self::assertSame($widget, $repository->stored[0]);
    }

    public function test_list_active_delegates_to_repository(): void
    {
        $repository = $this->inMemoryRepository();
        $repository->active = [new Widget(1, 'A', WidgetStatus::Active, '2026-01-01 00:00:00')];
        $service = new WidgetService($repository, $this->fixedClock('2026-07-13 10:00:00'));

        self::assertSame($repository->active, $service->listActive());
    }

    private function fixedClock(string $moment): ClockInterface
    {
        return new class($moment) implements ClockInterface {
            public function __construct(private readonly string $moment) {}

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->moment);
            }
        };
    }

    private function inMemoryRepository(): WidgetRepositoryInterface
    {
        return new class implements WidgetRepositoryInterface {
            /** @var list<Widget> */
            public array $stored = [];

            /** @var list<Widget> */
            public array $active = [];

            public function store(Widget $widget): void
            {
                $this->stored[] = $widget;
            }

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
}
