<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\WordPress\Schema;

use Middag\Framework\Database\Contract\ConnectionAdapterInterface;
use Middag\Framework\Database\Schema\MysqlSchemaBuilderAdapter;
use Middag\Framework\Database\Schema\SchemaBuilder;
use Middag\WpBoilerplate\Domain\Widget\WidgetSchema;

/**
 * Creates the widget table from the {@see WidgetSchema} attribute descriptor,
 * driving the framework's schema builder — no hand-written CREATE TABLE.
 *
 * The reader turns #[Table]/#[Column]/#[Key]/#[Index] into a descriptor; the
 * MySQL adapter emits the DDL and runs it through the connection. The logical
 * table name is resolved to its physical, prefixed form first (the wpdb adapter
 * works in physical names). Idempotent: skips creation when the table exists.
 */
final class SchemaInstaller
{
    private const LOGICAL_TABLE = 'mdgbp_widget';

    public function install(ConnectionAdapterInterface $connection): void
    {
        $descriptor = (new SchemaBuilder())
            ->registerClass(WidgetSchema::class)
            ->table(self::LOGICAL_TABLE)
        ;

        if ($descriptor === null) {
            return;
        }

        $descriptor['name'] = $connection->dialect()->table((string) $descriptor['name']);

        $adapter = new MysqlSchemaBuilderAdapter($connection);

        if (!$adapter->tableExists($descriptor['name'])) {
            $adapter->createTable($descriptor);
        }
    }
}
