<?php
/**
 * Ohjeiden arkistonäkymä
 */

namespace Sivustamo\Client\Frontend;

use Sivustamo\Client\Helpers\Helpers;

class Archive {

    /**
     * Alusta
     */
    public static function init() {
        add_filter('template_include', [__CLASS__, 'template_include']);
        add_action('wp_head', [__CLASS__, 'add_meta_tags']);
    }

    /**
     * Template include
     */
    public static function template_include($template) {
        global $wp_query;

        if (!isset($wp_query->query_vars['sivustamo_archive'])) {
            return $template;
        }

        // Tarkista pääsy
        if (!Access_Control::check_access()) {
            Access_Control::show_access_denied();
            return;
        }

        // Määritä mitä näytetään
        $kategoria_slug = $wp_query->query_vars['sivustamo_kategoria'] ?? '';
        $ohje_slug = $wp_query->query_vars['sivustamo_ohje'] ?? '';

        if ($ohje_slug) {
            // Yksittäinen ohje
            return self::load_single_template($kategoria_slug, $ohje_slug);
        } elseif ($kategoria_slug) {
            // Kategorian ohjeet
            return self::load_kategoria_template($kategoria_slug);
        } else {
            // Arkiston etusivu
            return self::load_archive_template();
        }
    }

    /**
     * Lataa arkisto-template
     */
    private static function load_archive_template() {
        // Hae kategoriat
        $kategoriat = get_terms([
            'taxonomy' => 'sivustamo_kategoria',
            'hide_empty' => true,
            'orderby' => 'meta_value_num',
            'meta_key' => '_kategoria_order',
            'order' => 'ASC',
        ]);

        // Suodata käyttöoikeuksien mukaan
        $kategoriat = Access_Control::filter_kategoriat($kategoriat);

        // Aseta muuttujat templatelle
        set_query_var('sivustamo_kategoriat', $kategoriat);
        set_query_var('sivustamo_page_title', __('Ohjeet', 'sivustamo'));

        // Etsi template
        $template = locate_template('sivustamo/archive-ohjeet.php');

        if (!$template) {
            $template = SIVUSTAMO_PLUGIN_DIR . 'templates/archive-ohjeet.php';
        }

        return $template;
    }

    /**
     * Lataa kategoria-template
     */
    private static function load_kategoria_template($kategoria_slug) {
        $kategoria = get_term_by('slug', $kategoria_slug, 'sivustamo_kategoria');

        if (!$kategoria) {
            global $wp_query;
            $wp_query->set_404();
            return get_404_template();
        }

        // Tarkista pääsy
        if (!Access_Control::can_view_kategoria($kategoria->term_id)) {
            Access_Control::show_access_denied();
            return;
        }

        // Hae ohjeet
        $ohjeet = get_posts([
            'post_type' => 'sivustamo_ohje',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_ohje_priority',
            'order' => 'ASC',
            'tax_query' => [
                [
                    'taxonomy' => 'sivustamo_kategoria',
                    'field' => 'term_id',
                    'terms' => $kategoria->term_id,
                ]
            ],
        ]);

        // Suodata käyttöoikeuksien mukaan
        $ohjeet = Access_Control::filter_ohjeet($ohjeet);

        set_query_var('sivustamo_kategoria', $kategoria);
        set_query_var('sivustamo_ohjeet', $ohjeet);
        set_query_var('sivustamo_page_title', $kategoria->name);

        $template = locate_template('sivustamo/archive-kategoria.php');

        if (!$template) {
            $template = SIVUSTAMO_PLUGIN_DIR . 'templates/archive-ohjeet.php';
        }

        return $template;
    }

    /**
     * Lataa yksittäisen ohjeen template
     */
    private static function load_single_template($kategoria_slug, $ohje_slug) {
        // Hae ohje
        $ohjeet = get_posts([
            'post_type' => 'sivustamo_ohje',
            'post_status' => 'publish',
            'name' => $ohje_slug,
            'posts_per_page' => 1,
        ]);

        if (empty($ohjeet)) {
            global $wp_query;
            $wp_query->set_404();
            return get_404_template();
        }

        $ohje = $ohjeet[0];

        // Tarkista pääsy
        if (!Access_Control::can_view_ohje($ohje->ID)) {
            Access_Control::show_access_denied();
            return;
        }

        // Hae kategoria
        $kategoria = null;
        if ($kategoria_slug) {
            $kategoria = get_term_by('slug', $kategoria_slug, 'sivustamo_kategoria');
        }

        // Raportoi katselukerta
        self::report_view($ohje->ID);

        set_query_var('sivustamo_ohje', $ohje);
        set_query_var('sivustamo_kategoria', $kategoria);
        set_query_var('sivustamo_page_title', $ohje->post_title);

        $template = locate_template('sivustamo/single-ohje.php');

        if (!$template) {
            $template = SIVUSTAMO_PLUGIN_DIR . 'templates/single-ohje.php';
        }

        return $template;
    }

    /**
     * Raportoi katselukerta
     */
    private static function report_view($ohje_id) {
        // Tarkista onko master-synkronoitu ohje
        $master_id = get_post_meta($ohje_id, '_ohje_master_id', true);

        if (!$master_id) {
            return; // Paikallinen ohje, ei raportoida
        }

        // Lähetä masterille
        $api = new \Sivustamo\Client\Sync\API_Client();

        if (!$api->is_configured()) {
            return;
        }

        $user_role = Helpers::get_user_role_display();
        $api->record_view($master_id, $user_role);
    }

    /**
     * Lisää meta-tagit
     */
    public static function add_meta_tags() {
        global $wp_query;

        if (!isset($wp_query->query_vars['sivustamo_archive'])) {
            return;
        }

        // Estä indeksointi
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    }
}
