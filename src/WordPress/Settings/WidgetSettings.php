<?php

declare(strict_types=1);

namespace Middag\WpBoilerplate\WordPress\Settings;

use Middag\WordPress\Security\Enum\WpCapability;
use Middag\WordPress\Settings\Enum\FieldType;
use Middag\WordPress\Settings\Field;
use Middag\WordPress\Settings\Section;
use Middag\WordPress\Settings\SettingsPageRegistrar;
use Middag\WordPress\Settings\SettingsRegistrar;
use Middag\WordPress\Settings\Tab;

/**
 * A native WordPress settings page — server-rendered, ZERO React.
 *
 * Proves the boilerplate is NOT 100% React: the wp-adapter's Settings API layer
 * (Tab → Section → Field + FieldType) wires register_setting/add_settings_section/
 * add_settings_field and renders fields through the lib's FieldRenderer. It lives
 * under the SAME plugin menu as the Inertia "Widgets" page — one plugin, two
 * admin idioms.
 *
 * Fields declare the persisted option name, control type and (for Select) the
 * choices; the type's default sanitizer runs on save, capability-guarded.
 */
final class WidgetSettings
{
    public const PAGE = 'middag-boilerplate-settings';

    public const OPTION_GROUP = 'middag_boilerplate';

    private const TAB = 'general';

    /**
     * @return list<Tab>
     */
    public function tabs(): array
    {
        return [
            new Tab(self::TAB, 'General', [
                new Section('mdgbp_general', 'General', [
                    new Field('mdgbp_enabled', 'Enable widgets', FieldType::Checkbox, default: '0'),
                    new Field('mdgbp_api_key', 'API key', FieldType::Password),
                    new Field('mdgbp_sync_interval', 'Sync interval', FieldType::Select, default: 'hourly', options: [
                        'hourly' => 'Hourly',
                        'twicedaily' => 'Twice daily',
                        'daily' => 'Daily',
                    ]),
                ]),
            ]),
        ];
    }

    /**
     * Register the settings + fields with WordPress. Wire on admin_init.
     */
    public function register(): void
    {
        (new SettingsPageRegistrar(new SettingsRegistrar()))
            ->register(self::PAGE, self::OPTION_GROUP, $this->tabs(), WpCapability::ManageOptions);
    }

    /**
     * The submenu page callback: the standard WordPress options form. The
     * registrar already added the sections/fields under the tab page slug.
     */
    public function renderPage(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('MIDDAG Boilerplate Settings', 'middag-boilerplate') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION_GROUP);
        do_settings_sections(self::PAGE . '-' . self::TAB);
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}
