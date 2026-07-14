<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\Domain\Widget;

use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Widget::class)]
final class WidgetTest extends TestCase
{
    public function testFromArrayRoundTripsThroughToArray(): void
    {
        $row = ['id' => 5, 'name' => 'Sensor', 'status' => 'active', 'created_at' => '2026-01-01 00:00:00'];

        $widget = Widget::fromArray($row);

        self::assertSame(5, $widget->getId());
        self::assertSame('Sensor', $widget->name());
        self::assertSame(WidgetStatus::Active, $widget->status());
        self::assertSame($row, $widget->toArray());
    }

    public function testFromArrayDefaultsMissingStatusToDraft(): void
    {
        $widget = Widget::fromArray(['name' => 'Bare']);

        self::assertNull($widget->getId());
        self::assertSame(WidgetStatus::Draft, $widget->status());
    }

    public function testStatusTransitionsAndIdAssignment(): void
    {
        $widget = new Widget(null, 'Sensor', WidgetStatus::Draft, '2026-01-01 00:00:00');

        $widget->activate();
        self::assertTrue($widget->status()->isActive());

        $widget->archive();
        self::assertSame(WidgetStatus::Archived, $widget->status());

        $widget->assignId(9);
        self::assertSame(9, $widget->getId());
    }

    public function testJsonSerializeMatchesToArray(): void
    {
        $widget = new Widget(1, 'Sensor', WidgetStatus::Active, '2026-01-01 00:00:00');

        self::assertSame($widget->toArray(), $widget->jsonSerialize());
    }

    public function testCreatedAtReturnsConstructedTimestamp(): void
    {
        $widget = new Widget(1, 'Sensor', WidgetStatus::Active, '2026-01-01 00:00:00');

        self::assertSame('2026-01-01 00:00:00', $widget->createdAt());
    }
}
