<?php
/**
 * Sivustamo Master Uninstall
 *
 * Suoritetaan kun lisäosa poistetaan WordPressin hallintapaneelista.
 * Poistaa kaikki lisäosan luomat tiedot:
 * - Ohjeet (post type)
 * - Sivustot/lisenssit (post type)
 * - Kategoriat (taxonomy)
 * - Tietokantataulut (views, feedback)
 * - Asetukset (options)
 * - Käyttöoikeudet (capabilities)
 */

// Estetään suora pääsy
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Poista kaikki ohjeet
$ohjeet = get_posts([
    'post_type' => 'sivustamo_ohje',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
]);

foreach ($ohjeet as $ohje_id) {
    wp_delete_post($ohje_id, true);
}

// Poista kaikki sivustot/lisenssit
$sivustot = get_posts([
    'post_type' => 'sivustamo_sivusto',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
]);

foreach ($sivustot as $sivusto_id) {
    wp_delete_post($sivusto_id, true);
}

// Poista kaikki kategoriat
$kategoriat = get_terms([
    'taxonomy' => 'sivustamo_kategoria',
    'hide_empty' => false,
    'fields' => 'ids',
]);

if (!is_wp_error($kategoriat)) {
    foreach ($kategoriat as $term_id) {
        wp_delete_term($term_id, 'sivustamo_kategoria');
    }
}

// Poista tietokantataulut
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sivustamo_views");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}sivustamo_feedback");

// Poista asetukset
delete_option('sivustamo_master_version');
delete_option('sivustamo_master_api_enabled');
delete_option('sivustamo_master_require_signature');

// Poista capabilities
$admin = get_role('administrator');
if ($admin) {
    $admin->remove_cap('manage_sivustamo_ohjeet');
    $admin->remove_cap('edit_sivustamo_ohjeet');
    $admin->remove_cap('delete_sivustamo_ohjeet');
    $admin->remove_cap('publish_sivustamo_ohjeet');
    $admin->remove_cap('manage_sivustamo_sivustot');
}

// Siivoa rewrite rules
flush_rewrite_rules();
