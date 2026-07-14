<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\WordPress\Repository;

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\WpBoilerplate\Domain\Widget\Enum\WidgetStatus;
use Middag\WpBoilerplate\Domain\Widget\Widget;
use Middag\WpBoilerplate\WordPress\Persistence\WidgetMapper;
use Middag\WpBoilerplate\WordPress\Repository\WidgetRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The repository is a thin, domain-shaped seam over the framework's
 * data-mapper base and the host connection adapter (in production, the wpdb
 * adapter). These tests mock the connection + its dialect so we can assert the
 * exact SQL/records handed to the adapter and the entities mapped back — no
 * database required. The dialect is stubbed to prefix logical table names
 * ('mdgbp_widget' -> 'wp_mdgbp_widget'), mirroring how the wpdb dialect
 * resolves the physical, prefixed table name.
 */
#[CoversClass(WidgetRepository::class)]
final class WidgetRepositoryTest extends TestCase
{
    private const CREATED_AT = '2026-01-01 00:00:00';

    private const PHYSICAL_TABLE = 'wp_mdgbp_widget';

    public function testStoreInsertsANewWidgetViaTheConnectionAdapter(): void
    {
        $connection = $this->newConnection();

        // A null-id widget is an insert: the id is dropped so the auto-increment
        // column assigns it, and the record lands via the adapter's insert().
        $connection->expects(self::once())
            ->method('insert')
            ->with(
                self::PHYSICAL_TABLE,
                self::identicalTo([
                    'name' => 'Sensor',
                    'status' => 'draft',
                    'created_at' => self::CREATED_AT,
                ]),
            )
            ->willReturn(42)
        ;
        $connection->expects(self::never())->method('execute');

        $this->newRepository($connection)->store(
            new Widget(null, 'Sensor', WidgetStatus::Draft, self::CREATED_AT),
        );
    }

    public function testStoreUpdatesAnExistingWidgetByPrimaryKey(): void
    {
        $connection = $this->newConnection();

        // A widget carrying an id is an update: the base repository builds an
        // UPDATE ... WHERE id = ? against the physical table, binding every
        // record column followed by the primary key.
        $connection->expects(self::once())
            ->method('execute')
            ->with(
                'UPDATE ' . self::PHYSICAL_TABLE . ' SET id = ?, name = ?, status = ?, created_at = ? WHERE id = ?',
                [7, 'Sensor', 'active', self::CREATED_AT, 7],
            )
            ->willReturn(1)
        ;
        $connection->expects(self::never())->method('insert');

        $this->newRepository($connection)->store(
            new Widget(7, 'Sensor', WidgetStatus::Active, self::CREATED_AT),
        );
    }

    public function testOfIdReturnsTheHydratedWidgetWhenARowMatches(): void
    {
        $connection = $this->newConnection();
        $connection->expects(self::once())
            ->method('fetch')
            ->with(
                'SELECT * FROM ' . self::PHYSICAL_TABLE . ' WHERE id = ? LIMIT 1',
                [5],
            )
            ->willReturn(['id' => 5, 'name' => 'Sensor', 'status' => 'active', 'created_at' => self::CREATED_AT])
        ;

        $widget = $this->newRepository($connection)->ofId(5);

        self::assertInstanceOf(Widget::class, $widget);
        self::assertSame(5, $widget->getId());
        self::assertSame('Sensor', $widget->name());
        self::assertSame(WidgetStatus::Active, $widget->status());
    }

    public function testOfIdReturnsNullWhenNoRowMatches(): void
    {
        $connection = $this->newConnection();
        $connection->expects(self::once())
            ->method('fetch')
            ->willReturn(null)
        ;

        self::assertNull($this->newRepository($connection)->ofId(404));
    }

    public function testAllActiveReturnsOnlyActiveWidgetsOrderedByIdDesc(): void
    {
        $connection = $this->newConnection();
        $connection->expects(self::once())
            ->method('fetchAll')
            ->with(
                'SELECT * FROM ' . self::PHYSICAL_TABLE . ' WHERE status = ? ORDER BY id desc',
                ['active'],
            )
            ->willReturn([
                ['id' => 3, 'name' => 'Newer', 'status' => 'active', 'created_at' => self::CREATED_AT],
                ['id' => 1, 'name' => 'Older', 'status' => 'active', 'created_at' => self::CREATED_AT],
            ])
        ;

        $widgets = $this->newRepository($connection)->allActive();

        self::assertCount(2, $widgets);
        self::assertContainsOnlyInstancesOf(Widget::class, $widgets);
        self::assertSame([3, 1], array_map(static fn (Widget $w): ?int => $w->getId(), $widgets));
    }

    public function testAllActiveReturnsAnEmptyListWhenNoActiveWidgets(): void
    {
        $connection = $this->newConnection();
        $connection->expects(self::once())
            ->method('fetchAll')
            ->willReturn([])
        ;

        self::assertSame([], $this->newRepository($connection)->allActive());
    }

    private function newRepository(ConnectionAdapterInterface $connection): WidgetRepository
    {
        return new WidgetRepository($connection, new WidgetMapper());
    }

    /**
     * A connection mock wired to a dialect that prefixes logical table names,
     * the way the wpdb dialect resolves the physical, prefixed table name.
     *
     * @return ConnectionAdapterInterface&MockObject
     */
    private function newConnection(): ConnectionAdapterInterface
    {
        // Idempotent prefixing mirrors WpdbSqlDialect::table(): the repository's
        // table() resolves the physical name and the query builder re-applies the
        // dialect, so already-prefixed names must pass through unchanged.
        $dialect = $this->createMock(SqlDialectInterface::class);
        $dialect->method('table')->willReturnCallback(
            static fn (string $name): string => str_starts_with($name, 'wp_') ? $name : 'wp_' . $name,
        );
        $dialect->method('limitOffset')->willReturnCallback(
            static fn (?int $limit, ?int $offset): string => $limit === null ? '' : sprintf(' LIMIT %d', $limit),
        );

        $connection = $this->createMock(ConnectionAdapterInterface::class);
        $connection->method('dialect')->willReturn($dialect);

        return $connection;
    }
}
