<?php
/**
 * Aktivointi-luokka
 */

namespace Sivustamo\Master;

class Activator {

    /**
     * Aktivoi plugin
     */
    public static function activate() {
        self::create_tables();
        self::add_capabilities();
        self::create_pages();

        // Flush rewrite rules
        flush_rewrite_rules();

        // Merkitse versio
        update_option('sivustamo_master_version', SIVUSTAMO_MASTER_VERSION);
    }

    /**
     * Luo tietokantataulut
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Katselukerrat
        $table_views = $wpdb->prefix . 'sivustamo_views';
        $sql_views = "CREATE TABLE $table_views (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ohje_id BIGINT(20) UNSIGNED NOT NULL,
            sivusto_id BIGINT(20) UNSIGNED NOT NULL,
            user_role VARCHAR(50) DEFAULT NULL,
            viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ohje (ohje_id),
            KEY idx_sivusto (sivusto_id),
            KEY idx_viewed (viewed_at)
        ) $charset_collate;";

        // Palautteet
        $table_feedback = $wpdb->prefix . 'sivustamo_feedback';
        $sql_feedback = "CREATE TABLE $table_feedback (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ohje_id BIGINT(20) UNSIGNED NOT NULL,
            sivusto_id BIGINT(20) UNSIGNED NOT NULL,
            thumbs ENUM('up', 'down') NOT NULL,
            stars TINYINT(1) DEFAULT NULL,
            comment TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ohje (ohje_id),
            KEY idx_sivusto (sivusto_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_views);
        dbDelta($sql_feedback);
    }

    /**
     * Lisää oikeudet
     */
    private static function add_capabilities() {
        $admin = get_role('administrator');

        if ($admin) {
            $admin->add_cap('manage_sivustamo_ohjeet');
            $admin->add_cap('edit_sivustamo_ohjeet');
            $admin->add_cap('delete_sivustamo_ohjeet');
            $admin->add_cap('publish_sivustamo_ohjeet');
            $admin->add_cap('manage_sivustamo_sivustot');
        }
    }

    /**
     * Luo tarvittavat sivut
     */
    private static function create_pages() {
        // Ei tarvita master-puolella
    }
}
