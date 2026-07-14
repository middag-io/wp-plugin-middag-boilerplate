<?php

/**
 * Uninstall routine — runs when the plugin is DELETED (not merely deactivated).
 *
 * PRESERVATION-FIRST: by default this removes only DERIVED state (scheduled
 * events, caches) that regenerates on its own. Production DATA — the widget
 * table, the CPT content, the settings and the custom capability — is destroyed
 * ONLY when the site owner opts in with, in wp-config.php:
 *
 *     define('MDGBP_UNINSTALL_PURGE', true);
 *
 * Runtime contract: WordPress loads this file WITHOUT bootstrapping the plugin
 * and WITHOUT the Composer autoloader, so it uses only WordPress core APIs and
 * HARDCODED literals (no class constants — the classes are not loaded here). Any
 * literal below must stay in sync with its source of truth in `src/`.
 *
 * @package Middag\WpBoilerplate
 */

declare(strict_types=1);

// Only ever run in WordPress's uninstall context.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up a single site. Called once per site (multisite loops below).
 */
function mdgbp_uninstall_cleanup(): void
{
    global $wpdb;

    // --- Always safe: derived/scheduled state (regenerates on reinstall). ---
    wp_clear_scheduled_hook('mdgbp_sync');
    // A plugin that cached remote data would delete its transients here too,
    // e.g. delete_transient('mdgbp_remote_catalog').

    // --- Opt-in only: destroy production data. ---
    if (!defined('MDGBP_UNINSTALL_PURGE') || !MDGBP_UNINSTALL_PURGE) {
        return;
    }

    // Settings options (each Settings API field persists its own option).
    foreach (['mdgbp_enabled', 'mdgbp_api_key', 'mdgbp_sync_interval'] as $option) {
        delete_option($option);
    }

    // The custom Widget table (logical name mdgbp_widget, wpdb-prefixed).
    $table = $wpdb->prefix . 'mdgbp_widget';
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");

    // The Note CPT content (post meta is removed with each post).
    $noteIds = get_posts([
        'post_type' => 'mdgbp_note',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
    ]);
    foreach ($noteIds as $noteId) {
        wp_delete_post((int) $noteId, true);
    }

    // Revoke the custom capability (the lib registrar is not autoloaded here).
    $administrator = get_role('administrator');
    if ($administrator instanceof WP_Role) {
        $administrator->remove_cap('mdgbp_manage');
    }
}

// Multisite: clean up every site in the network; otherwise just this one.
if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids']) as $blogId) {
        switch_to_blog((int) $blogId);
        mdgbp_uninstall_cleanup();
        restore_current_blog();
    }
} else {
    mdgbp_uninstall_cleanup();
}
