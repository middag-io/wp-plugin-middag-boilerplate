<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\WordPress\Persistence;

use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use Middag\WpBoilerplate\WordPress\Persistence\WidgetMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WidgetMapper::class)]
final class WidgetMapperTest extends TestCase
{
    public function test_db_to_domain_hydrates_a_widget(): void
    {
        $widget = (new WidgetMapper())->dbToDomain(
            ['id' => 3, 'name' => 'Sensor', 'status' => 'active', 'created_at' => '2026-01-01 00:00:00'],
            [],
        );

        self::assertInstanceOf(Widget::class, $widget);
        self::assertSame(3, $widget->getId());
        self::assertSame('active', $widget->toArray()['status']);
    }

    public function test_domain_to_db_drops_null_id_so_auto_increment_assigns_it(): void
    {
        $record = (new WidgetMapper())->domainToDb(
            new Widget(null, 'Sensor', WidgetStatus::Draft, '2026-01-01 00:00:00'),
        );

        self::assertArrayNotHasKey('id', $record);
        self::assertSame('Sensor', $record['name']);
        self::assertSame('draft', $record['status']);
    }

    public function test_domain_to_db_keeps_id_for_update(): void
    {
        $record = (new WidgetMapper())->domainToDb(
            new Widget(7, 'Sensor', WidgetStatus::Active, '2026-01-01 00:00:00'),
        );

        self::assertSame(7, $record['id']);
    }
}
