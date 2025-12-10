<?php
/**
 * Aktivointi-luokka
 */

namespace Sivustamo\Client;

class Activator {

    /**
     * Aktivoi plugin
     */
    public static function activate() {
        self::add_capabilities();
        self::schedule_cron();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Merkitse versio
        update_option('sivustamo_version', SIVUSTAMO_VERSION);
    }

    /**
     * Lisää oikeudet
     */
    private static function add_capabilities() {
        // Admin
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('view_sivustamo_ohjeet');
            $admin->add_cap('edit_sivustamo_ohjeet');
            $admin->add_cap('manage_sivustamo_settings');
        }

        // Editor
        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('view_sivustamo_ohjeet');
            $editor->add_cap('edit_sivustamo_ohjeet');
        }

        // Shop Manager (WooCommerce)
        $shop_manager = get_role('shop_manager');
        if ($shop_manager) {
            $shop_manager->add_cap('view_sivustamo_ohjeet');
        }
    }

    /**
     * Ajasta cron
     */
    private static function schedule_cron() {
        if (!wp_next_scheduled('sivustamo_sync_cron')) {
            wp_schedule_event(time(), 'daily', 'sivustamo_sync_cron');
        }
    }
}
