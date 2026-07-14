<?php

/**
 * Plugin Name:       MIDDAG Boilerplate
 * Plugin URI:        https://github.com/middag-io/wp-plugin-middag-boilerplate
 * Description:       MIDDAG's reference architecture for a WordPress plugin — composes the OSS framework, WordPress adapter, and UI libraries end to end.
 * x-release-please-start-version
 * Version:           0.1.0
 * x-release-please-end
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            MIDDAG
 * Author URI:        https://middag.io
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       middag-boilerplate
 * Domain Path:       /languages
 *
 * @package Middag\WpBoilerplate
 */

declare(strict_types=1);

use Middag\WpBoilerplate\Core\Plugin;

// Block direct file access — WordPress defines ABSPATH when it loads a plugin.
if (!defined('ABSPATH')) {
    exit;
}

const MIDDAG_BOILERPLATE_VERSION = '0.1.0'; // x-release-please-version

define('MIDDAG_BOILERPLATE_FILE', __FILE__);
define('MIDDAG_BOILERPLATE_PATH', plugin_dir_path(__FILE__));
define('MIDDAG_BOILERPLATE_URL', plugin_dir_url(__FILE__));

// Composer autoloader. On a distributed build this is always present; guard it
// so a source checkout without `composer install` fails loud, not fatal-silent.
$middagBoilerplateAutoload = MIDDAG_BOILERPLATE_PATH . 'vendor/autoload.php';
if (!is_file($middagBoilerplateAutoload)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('MIDDAG Boilerplate: run "composer install" in the plugin directory.', 'middag-boilerplate');
        echo '</p></div>';
    });

    return;
}

require $middagBoilerplateAutoload;

// Lifecycle: create the widget table on activation, clean up on deactivation.
register_activation_hook(__FILE__, [Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);

// Boot on plugins_loaded (never at file scope) so the container is built after
// WordPress and every other plugin's classes are available.
add_action('plugins_loaded', static function (): void {
    Plugin::boot(MIDDAG_BOILERPLATE_FILE);
});
