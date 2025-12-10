<?php
/**
 * Sivustamo Uninstall
 *
 * Suoritetaan kun lisäosa poistetaan WordPressin hallintapaneelista.
 * Poistaa kaikki lisäosan luomat tiedot:
 * - Ohjeet (post type)
 * - Kategoriat (taxonomy)
 * - Asetukset (options)
 * - Post meta
 * - Term meta
 */

// Estetään suora pääsy
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Poista kaikki ohjeet
$ohjeet = get_posts([
    'post_type' => 'sivustamo_ohje',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
]);

foreach ($ohjeet as $ohje_id) {
    wp_delete_post($ohje_id, true); // true = ohita roskakori
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

// Poista asetukset
delete_option('sivustamo_master_url');
delete_option('sivustamo_api_key');
delete_option('sivustamo_secret');
delete_option('sivustamo_base_slug');
delete_option('sivustamo_last_sync');
delete_option('sivustamo_version');
delete_option('sivustamo_rewrite_flushed');

// Poista mahdolliset transientit
delete_transient('sivustamo_connection_test');

// Siivoa rewrite rules
flush_rewrite_rules();
