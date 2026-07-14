<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\WordPress\Schema;

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Contract\SqlDialectInterface;
use Middag\WpBoilerplate\WordPress\Schema\SchemaInstaller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The installer drives the framework's real MySQL schema adapter against a
 * mocked connection: it must resolve the logical table name to its physical
 * (prefixed) form, probe existence, and only emit CREATE TABLE when absent.
 * We assert the DDL the adapter produces from the {@see WidgetSchema} attributes.
 */
#[CoversClass(SchemaInstaller::class)]
final class SchemaInstallerTest extends TestCase
{
    public function testInstallCreatesTheTableWhenAbsentEmittingSchemaDdl(): void
    {
        // Existence probe (information_schema) returns null → table is absent.
        $connection = $this->createMock(ConnectionAdapterInterface::class);
        $connection->method('dialect')->willReturn($this->prefixingDialect());
        $connection->method('fetch')->willReturn(null);

        $ddl = null;
        $connection->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (string $sql) use (&$ddl): int {
                $ddl = $sql;

                return 0;
            })
        ;

        (new SchemaInstaller())->install($connection);

        self::assertIsString($ddl);
        // Logical 'mdgbp_widget' resolved to the physical, prefixed name.
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS wp_mdgbp_widget', $ddl);
        // Columns mapped from the WidgetSchema attribute descriptor.
        self::assertStringContainsString('id BIGINT NOT NULL AUTO_INCREMENT', $ddl);
        self::assertStringContainsString('name VARCHAR(191) NOT NULL', $ddl);
        self::assertStringContainsString('status VARCHAR(20) NOT NULL', $ddl);
        self::assertStringContainsString("DEFAULT 'draft'", $ddl);
        self::assertStringContainsString('created_at DATETIME NOT NULL', $ddl);
        // Sequence column owns the primary key; the declared index is emitted.
        self::assertStringContainsString('PRIMARY KEY (id)', $ddl);
        self::assertStringContainsString('INDEX widget_status (status)', $ddl);
    }

    public function testInstallIsIdempotentAndSkipsCreationWhenTableExists(): void
    {
        // Existence probe returns a row → table already present.
        $connection = $this->createMock(ConnectionAdapterInterface::class);
        $connection->method('dialect')->willReturn($this->prefixingDialect());
        $connection->method('fetch')->willReturn(['1' => 1]);

        // No DDL is executed when the table already exists.
        $connection->expects(self::never())->method('execute');

        (new SchemaInstaller())->install($connection);
    }

    public function testInstallResolvesTheLogicalTableNameThroughTheDialect(): void
    {
        $dialect = $this->createMock(SqlDialectInterface::class);
        $dialect->expects(self::once())
            ->method('table')
            ->with('mdgbp_widget')
            ->willReturn('wp_mdgbp_widget')
        ;

        $connection = $this->createMock(ConnectionAdapterInterface::class);
        $connection->method('dialect')->willReturn($dialect);
        // The existence probe receives the resolved physical name as a bind param.
        $connection->expects(self::once())
            ->method('fetch')
            ->with(self::anything(), ['wp_mdgbp_widget'])
            ->willReturn(['1' => 1])
        ;

        (new SchemaInstaller())->install($connection);
    }

    private function prefixingDialect(): SqlDialectInterface
    {
        $dialect = $this->createMock(SqlDialectInterface::class);
        $dialect->method('table')->willReturnCallback(
            static fn (string $logicalName): string => 'wp_' . $logicalName,
        );

        return $dialect;
    }
}
