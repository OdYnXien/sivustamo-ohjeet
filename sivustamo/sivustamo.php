<?php
/**
 * Plugin Name: Sivustamo
 * Plugin URI: https://sivustamo.fi
 * Description: Sivustamon ohjeet ja oppaat - synkronoidut ohjeet keskitetystä hallinnasta
 * Version: 1.0.0
 * Author: Esko Junnila / Sivustamo Oy
 * Author URI: https://sivustamo.fi
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: sivustamo
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

namespace Sivustamo\Client;

// Estetään suora pääsy
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-vakiot
define('SIVUSTAMO_VERSION', '1.0.0');
define('SIVUSTAMO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIVUSTAMO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SIVUSTAMO_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Pääluokka
 */
final class Sivustamo {

    /**
     * Singleton-instanssi
     */
    private static $instance = null;

    /**
     * Hae instanssi
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktori
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Lataa riippuvuudet
     */
    private function load_dependencies() {
        // Helpers
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/helpers/class-helpers.php';

        // Post Types & Taxonomies
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/post-types/class-ohje-cpt.php';
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/taxonomies/class-kategoria-tax.php';

        // Sync
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/sync/class-api-client.php';
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/sync/class-sync-manager.php';
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/sync/class-media-proxy.php';

        // Frontend
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/frontend/class-access-control.php';
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/frontend/class-archive.php';
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/frontend/class-single.php';
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/frontend/class-shortcodes.php';

        // Admin
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/admin/class-dashboard-widget.php';
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/admin/class-ohjeet-admin.php';

        // Activator & Deactivator
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/class-activator.php';
        require_once SIVUSTAMO_PLUGIN_DIR . 'includes/class-deactivator.php';
    }

    /**
     * Alusta hookit
     */
    private function init_hooks() {
        // Aktivointi/deaktivointi
        register_activation_hook(__FILE__, [Activator::class, 'activate']);
        register_deactivation_hook(__FILE__, [Deactivator::class, 'deactivate']);

        // Init
        add_action('init', [$this, 'init']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Admin
        if (is_admin()) {
            add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
            add_action('admin_init', [Admin\Settings_Page::class, 'register_settings']);
            add_action('admin_menu', [Admin\Settings_Page::class, 'add_menu']);
            add_action('wp_dashboard_setup', [Admin\Dashboard_Widget::class, 'register']);
        }

        // Frontend
        add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);

        // Cron
        add_action('sivustamo_sync_cron', [Sync\Sync_Manager::class, 'sync']);

        // AJAX handlers
        add_action('wp_ajax_sivustamo_submit_feedback', [$this, 'ajax_submit_feedback']);
    }

    /**
     * AJAX: Lähetä palaute
     */
    public function ajax_submit_feedback() {
        check_ajax_referer('sivustamo_frontend_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Kirjaudu sisään', 'sivustamo')]);
        }

        $ohje_id = intval($_POST['ohje_id']);
        $thumbs = sanitize_text_field($_POST['thumbs']);
        $stars = intval($_POST['stars']);
        $comment = sanitize_textarea_field($_POST['comment']);

        if (!$ohje_id || !in_array($thumbs, ['up', 'down'])) {
            wp_send_json_error(['message' => __('Virheelliset tiedot', 'sivustamo')]);
        }

        $api = new Sync\API_Client();

        if (!$api->is_configured()) {
            wp_send_json_error(['message' => __('Yhteys ei ole konfiguroitu', 'sivustamo')]);
        }

        $result = $api->record_feedback($ohje_id, $thumbs, $stars ?: null, $comment);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Kiitos palautteesta!', 'sivustamo')]);
    }

    /**
     * Alustus
     */
    public function init() {
        // Varmista oikeudet (tarvitaan jos lisäosa oli jo aktiivinen ennen päivitystä)
        $this->ensure_capabilities();

        // Rekisteröi post typet ja taxonomiat
        Post_Types\Ohje_CPT::register();
        Taxonomies\Kategoria_Tax::register();

        // Frontend routing
        Frontend\Archive::init();
        Frontend\Single::init();
        Frontend\Shortcodes::register();

        // Rewrite rules
        $this->add_rewrite_rules();
    }

    /**
     * Varmista että oikeudet on asetettu
     * Käytetään nyt WordPressin standardeja oikeuksia (edit_posts, manage_options)
     * joten tätä ei enää tarvita, mutta pidetään tyhjänä yhteensopivuuden vuoksi
     */
    private function ensure_capabilities() {
        // Käytetään nyt WordPressin standardeja capability_type => 'post'
        // Administrator ja Editor voivat muokata, koska heillä on edit_posts
        // Asetukset vaativat manage_options (vain Administrator)
    }

    /**
     * Lisää rewrite-säännöt
     */
    private function add_rewrite_rules() {
        $base = $this->get_base_slug();

        add_rewrite_rule(
            '^' . $base . '/?$',
            'index.php?sivustamo_archive=1',
            'top'
        );

        add_rewrite_rule(
            '^' . $base . '/([^/]+)/?$',
            'index.php?sivustamo_archive=1&sivustamo_kategoria=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^' . $base . '/([^/]+)/([^/]+)/?$',
            'index.php?sivustamo_archive=1&sivustamo_kategoria=$matches[1]&sivustamo_ohje=$matches[2]',
            'top'
        );

        add_rewrite_tag('%sivustamo_archive%', '([0-9]+)');
        add_rewrite_tag('%sivustamo_kategoria%', '([^/]+)');
        add_rewrite_tag('%sivustamo_ohje%', '([^/]+)');
    }

    /**
     * Hae base slug
     */
    public function get_base_slug() {
        return get_option('sivustamo_base_slug', 'sivustamo-ohjeet');
    }

    /**
     * Lataa käännökset
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'sivustamo',
            false,
            dirname(SIVUSTAMO_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Admin-skriptit ja tyylit
     */
    public function admin_scripts($hook) {
        // Lataa aina dashboard widgetin tyylit
        if ($hook === 'index.php') {
            wp_enqueue_style(
                'sivustamo-admin',
                SIVUSTAMO_PLUGIN_URL . 'assets/css/admin.css',
                [],
                SIVUSTAMO_VERSION
            );
        }

        // Lataa asetussivulla ja ohjeiden hallinnassa
        $screen = get_current_screen();
        if (strpos($hook, 'sivustamo') !== false ||
            ($screen && $screen->post_type === 'sivustamo_ohje')) {

            wp_enqueue_style(
                'sivustamo-admin',
                SIVUSTAMO_PLUGIN_URL . 'assets/css/admin.css',
                [],
                SIVUSTAMO_VERSION
            );

            wp_enqueue_script(
                'sivustamo-admin',
                SIVUSTAMO_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                SIVUSTAMO_VERSION,
                true
            );

            wp_localize_script('sivustamo-admin', 'sivustamo', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sivustamo_nonce'),
                'strings' => [
                    'syncing' => __('Synkronoidaan...', 'sivustamo'),
                    'syncComplete' => __('Synkronointi valmis!', 'sivustamo'),
                    'syncError' => __('Synkronointi epäonnistui', 'sivustamo'),
                    'confirmSync' => __('Haluatko synkronoida ohjeet nyt?', 'sivustamo'),
                ]
            ]);
        }
    }

    /**
     * Frontend-skriptit ja tyylit
     */
    public function frontend_scripts() {
        // Lataa vain ohjeet-sivuilla
        if (!$this->is_ohjeet_page()) {
            return;
        }

        wp_enqueue_style(
            'sivustamo-frontend',
            SIVUSTAMO_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            SIVUSTAMO_VERSION
        );

        wp_enqueue_script(
            'sivustamo-frontend',
            SIVUSTAMO_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            SIVUSTAMO_VERSION,
            true
        );

        wp_localize_script('sivustamo-frontend', 'sivustamoFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sivustamo_frontend_nonce'),
            'strings' => [
                'feedbackThanks' => __('Kiitos palautteesta!', 'sivustamo'),
                'feedbackError' => __('Palautteen lähetys epäonnistui', 'sivustamo'),
            ]
        ]);
    }

    /**
     * Tarkista onko ohjeet-sivu
     */
    public function is_ohjeet_page() {
        global $wp_query;
        return isset($wp_query->query_vars['sivustamo_archive']) && $wp_query->query_vars['sivustamo_archive'];
    }

    /**
     * Tarkista onko lisäosa konfiguroitu
     */
    public static function is_configured() {
        $api_key = get_option('sivustamo_api_key');
        $secret = get_option('sivustamo_secret');
        return !empty($api_key) && !empty($secret);
    }
}

// Käynnistä plugin
function sivustamo() {
    return Sivustamo::get_instance();
}

sivustamo();
