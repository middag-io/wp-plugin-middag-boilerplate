<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Domain\Widget;

use Middag\Framework\Database\Attribute\Column;
use Middag\Framework\Database\Attribute\Index;
use Middag\Framework\Database\Attribute\Key;
use Middag\Framework\Database\Attribute\Table;

/**
 * Declarative schema descriptor for the widget table — separate from the
 * {@see Widget} entity, which stays a persistence-ignorant POPO.
 *
 * Mirrors the Moodle sibling (moodle-local_middagboilerplate's schema class) in
 * spirit: the shape of the table lives in attributes, read by the framework's
 * SchemaAttributeReader → SchemaBuilder. The table name is the LOGICAL name;
 * the wpdb connection adapter prefixes it ($wpdb->prefix) at build time.
 */
#[Table('mdgbp_widget')]
#[Column('id', 'bigint', notnull: true, sequence: true)]
#[Column('name', 'char', length: 191, notnull: true)]
#[Column('status', 'char', length: 20, notnull: true, default: 'draft')]
#[Column('created_at', 'datetime', notnull: true)]
#[Key('primary', ['id'], 'primary')]
#[Index('widget_status', ['status'])]
final class WidgetSchema {}
