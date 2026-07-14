<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\Tests\Unit\WordPress\Settings;

use Middag\WordPress\Settings\Enum\FieldType;
use Middag\WordPress\Settings\Field;
use Middag\WpBoilerplate\WordPress\Settings\WidgetSettings;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

#[CoversClass(WidgetSettings::class)]
final class WidgetSettingsTest extends TestCase
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

    /**
     * register() delegates to the wp-adapter registrar, which pushes every field
     * through the WordPress Settings API. The three declared fields register one
     * setting + one field each, all under a single section.
     */
    public function testRegisterWiresTheSettingsApiForEveryField(): void
    {
        $registered = [];

        expect('register_setting')
            ->times(3)
            ->with(WidgetSettings::OPTION_GROUP, Mockery::type('string'), Mockery::type('array'))
            ->andReturnUsing(static function (string $group, string $name) use (&$registered): void {
                $registered[] = $name;
            })
        ;
        expect('add_settings_section')
            ->once()
            ->with('mdgbp_general', 'General', Mockery::type('callable'), 'middag-boilerplate-settings-general', Mockery::type('array'))
        ;
        expect('add_settings_field')
            ->times(3)
            ->with(Mockery::type('string'), Mockery::type('string'), Mockery::type('callable'), 'middag-boilerplate-settings-general', 'mdgbp_general', Mockery::type('array'))
        ;

        (new WidgetSettings())->register();

        self::assertSame(['mdgbp_enabled', 'mdgbp_api_key', 'mdgbp_sync_interval'], $registered);
    }

    /**
     * renderPage() emits the standard options-form wrapper and defers the field
     * markup to the WordPress Settings API helpers, scoped to the general tab.
     */
    public function testRenderPageEmitsTheOptionsFormScopedToTheGeneralTab(): void
    {
        when('esc_html__')->returnArg(1);
        expect('settings_fields')->once()->with(WidgetSettings::OPTION_GROUP);
        expect('do_settings_sections')->once()->with('middag-boilerplate-settings-general');
        expect('submit_button')->once()->withNoArgs();

        $this->expectOutputString(
            '<div class="wrap">'
            . '<h1>MIDDAG Boilerplate Settings</h1>'
            . '<form method="post" action="options.php">'
            . '</form>'
            . '</div>'
        );

        (new WidgetSettings())->renderPage();
    }
}
