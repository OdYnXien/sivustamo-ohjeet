<?php
/**
 * Helper-funktiot
 */

namespace Sivustamo\Client\Helpers;

class Helpers {

    /**
     * Hae ohjeen URL
     */
    public static function get_ohje_url($ohje_id) {
        $base = get_option('sivustamo_base_slug', 'sivustamo-ohjeet');
        $ohje = get_post($ohje_id);

        if (!$ohje) {
            return '';
        }

        // Hae kategoria
        $terms = get_the_terms($ohje_id, 'sivustamo_kategoria');
        $kategoria_slug = '';

        if ($terms && !is_wp_error($terms)) {
            // Käytä ensimmäistä kategoriaa
            $kategoria_slug = $terms[0]->slug;
        }

        if ($kategoria_slug) {
            return home_url("/{$base}/{$kategoria_slug}/{$ohje->post_name}/");
        }

        return home_url("/{$base}/{$ohje->post_name}/");
    }

    /**
     * Hae kategorian URL
     */
    public static function get_kategoria_url($term) {
        $base = get_option('sivustamo_base_slug', 'sivustamo-ohjeet');

        if (is_int($term)) {
            $term = get_term($term, 'sivustamo_kategoria');
        }

        if (!$term || is_wp_error($term)) {
            return '';
        }

        return home_url("/{$base}/{$term->slug}/");
    }

    /**
     * Hae ohjeiden arkiston URL
     */
    public static function get_archive_url() {
        $base = get_option('sivustamo_base_slug', 'sivustamo-ohjeet');
        return home_url("/{$base}/");
    }

    /**
     * Tarkista voiko käyttäjä nähdä ohjeen
     */
    public static function can_view_ohje($ohje_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        $allowed_roles = get_post_meta($ohje_id, '_ohje_roles', true);

        if (!is_array($allowed_roles) || empty($allowed_roles)) {
            // Oletusroolit
            $allowed_roles = ['administrator', 'editor', 'shop_manager'];
        }

        foreach ($user->roles as $role) {
            if (in_array($role, $allowed_roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tarkista voiko käyttäjä nähdä kategorian
     */
    public static function can_view_kategoria($term_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        $allowed_roles = get_term_meta($term_id, '_kategoria_roles', true);

        if (!is_array($allowed_roles) || empty($allowed_roles)) {
            // Oletusroolit
            $allowed_roles = ['administrator', 'editor', 'shop_manager'];
        }

        foreach ($user->roles as $role) {
            if (in_array($role, $allowed_roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hae käyttäjän rooli näytettäväksi
     */
    public static function get_user_role_display() {
        if (!is_user_logged_in()) {
            return '';
        }

        $user = wp_get_current_user();

        if (empty($user->roles)) {
            return '';
        }

        return $user->roles[0];
    }

    /**
     * Muunna aikaleima luettavaan muotoon
     */
    public static function time_ago($timestamp) {
        if (!is_numeric($timestamp)) {
            $timestamp = strtotime($timestamp);
        }

        return human_time_diff($timestamp, current_time('timestamp')) . ' ' . __('sitten', 'sivustamo');
    }

    /**
     * Hae ohjeen synkronointitila
     */
    public static function get_sync_status($ohje_id) {
        $source = get_post_meta($ohje_id, '_ohje_source', true);
        $local_modified = get_post_meta($ohje_id, '_ohje_local_modified', true);
        $master_version = get_post_meta($ohje_id, '_ohje_master_version', true);

        if ($source === 'local') {
            return [
                'status' => 'local',
                'label' => __('Paikallinen', 'sivustamo'),
                'class' => 'sivustamo-status-local',
            ];
        }

        if ($local_modified === '1') {
            return [
                'status' => 'modified',
                'label' => __('Muokattu paikallisesti', 'sivustamo'),
                'class' => 'sivustamo-status-modified',
            ];
        }

        return [
            'status' => 'synced',
            'label' => __('Synkronoitu (v' . $master_version . ')', 'sivustamo'),
            'class' => 'sivustamo-status-synced',
        ];
    }

    /**
     * Lyhennä teksti
     */
    public static function truncate($text, $length = 150, $suffix = '...') {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . $suffix;
    }
}
