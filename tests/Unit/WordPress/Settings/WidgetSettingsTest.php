<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\WordPress\Settings;

use Middag\WordPress\Settings\Enum\FieldType;
use Middag\WordPress\Settings\Field;
use Middag\WpBoilerplate\WordPress\Settings\WidgetSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WidgetSettings::class)]
final class WidgetSettingsTest extends TestCase
{
    public function testTabsDeclareTheGeneralFields(): void
    {
        $tabs = (new WidgetSettings())->tabs();

        self::assertCount(1, $tabs);
        self::assertSame('general', $tabs[0]->slug);

        $sections = $tabs[0]->sections;
        self::assertCount(1, $sections);

        $names = array_map(static fn (Field $field): string => $field->name, $sections[0]->fields);
        self::assertSame(['mdgbp_enabled', 'mdgbp_api_key', 'mdgbp_sync_interval'], $names);
    }

    public function testFieldTypesAndSelectOptions(): void
    {
        $fields = (new WidgetSettings())->tabs()[0]->sections[0]->fields;

        self::assertSame(FieldType::Checkbox, $fields[0]->type);
        self::assertSame(FieldType::Password, $fields[1]->type);
        self::assertSame(FieldType::Select, $fields[2]->type);
        self::assertArrayHasKey('hourly', $fields[2]->options);
    }

    public function testFieldsResolveASanitizerFromTheirType(): void
    {
        $field = (new WidgetSettings())->tabs()[0]->sections[0]->fields[0];

        self::assertIsCallable($field->resolveSanitizer());
    }
}
