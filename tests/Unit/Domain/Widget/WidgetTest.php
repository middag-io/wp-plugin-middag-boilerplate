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
    public function test_from_array_round_trips_through_to_array(): void
    {
        $row = ['id' => 5, 'name' => 'Sensor', 'status' => 'active', 'created_at' => '2026-01-01 00:00:00'];

        $widget = Widget::fromArray($row);

        self::assertSame(5, $widget->getId());
        self::assertSame('Sensor', $widget->name());
        self::assertSame(WidgetStatus::Active, $widget->status());
        self::assertSame($row, $widget->toArray());
    }

    public function test_from_array_defaults_missing_status_to_draft(): void
    {
        $widget = Widget::fromArray(['name' => 'Bare']);

        self::assertNull($widget->getId());
        self::assertSame(WidgetStatus::Draft, $widget->status());
    }

    public function test_status_transitions_and_id_assignment(): void
    {
        $widget = new Widget(null, 'Sensor', WidgetStatus::Draft, '2026-01-01 00:00:00');

        $widget->activate();
        self::assertTrue($widget->status()->isActive());

        $widget->archive();
        self::assertSame(WidgetStatus::Archived, $widget->status());

        $widget->assignId(9);
        self::assertSame(9, $widget->getId());
    }

    public function test_json_serialize_matches_to_array(): void
    {
        $widget = new Widget(1, 'Sensor', WidgetStatus::Active, '2026-01-01 00:00:00');

        self::assertSame($widget->toArray(), $widget->jsonSerialize());
    }
}
