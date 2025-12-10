<?php
/**
 * Plugin Name: Sivustamo Master
 * Plugin URI: https://sivustamo.fi
 * Description: Keskitetty ohjeiden hallintajärjestelmä - Master-lisäosa
 * Version: 1.0.0
 * Author: Esko Junnila / Sivustamo Oy
 * Author URI: https://sivustamo.fi
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: sivustamo-master
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

namespace Sivustamo\Master;

// Estetään suora pääsy
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-vakiot
define('SIVUSTAMO_MASTER_VERSION', '1.0.0');
define('SIVUSTAMO_MASTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SIVUSTAMO_MASTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SIVUSTAMO_MASTER_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Pääluokka
 */
final class Sivustamo_Master {

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
        // Post Types
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/post-types/class-ohje-cpt.php';
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/post-types/class-sivusto-cpt.php';

        // Taxonomies
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/taxonomies/class-kategoria-tax.php';

        // Helpers
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/helpers/class-license-generator.php';
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/helpers/class-media-handler.php';

        // Admin
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/admin/class-sivustot-admin.php';
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/admin/class-stats-dashboard.php';
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/admin/class-license-manager.php';

        // API
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/api/class-rest-controller.php';
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/api/class-ohjeet-api.php';
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/api/class-auth-api.php';
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/api/class-stats-api.php';

        // Activator & Deactivator
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/class-activator.php';
        require_once SIVUSTAMO_MASTER_PLUGIN_DIR . 'includes/class-deactivator.php';
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
            add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        }

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    /**
     * Alustus
     */
    public function init() {
        // Rekisteröi post typet ja taxonomiat
        Post_Types\Ohje_CPT::register();
        Post_Types\Sivusto_CPT::register();
        Taxonomies\Kategoria_Tax::register();
    }

    /**
     * Lataa käännökset
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'sivustamo-master',
            false,
            dirname(SIVUSTAMO_MASTER_PLUGIN_BASENAME) . '/languages/'
        );
    }

    /**
     * Admin-skriptit ja tyylit
     */
    public function admin_scripts($hook) {
        $screen = get_current_screen();

        // Lataa vain sivustamo-sivuilla
        if (strpos($screen->id, 'sivustamo') === false &&
            $screen->post_type !== 'sivustamo_ohje' &&
            $screen->post_type !== 'sivustamo_sivusto') {
            return;
        }

        wp_enqueue_style(
            'sivustamo-master-admin',
            SIVUSTAMO_MASTER_PLUGIN_URL . 'admin/css/admin.css',
            [],
            SIVUSTAMO_MASTER_VERSION
        );

        wp_enqueue_script(
            'sivustamo-master-admin',
            SIVUSTAMO_MASTER_PLUGIN_URL . 'admin/js/admin.js',
            ['jquery'],
            SIVUSTAMO_MASTER_VERSION,
            true
        );

        wp_localize_script('sivustamo-master-admin', 'sivustamoMaster', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sivustamo_master_nonce'),
            'strings' => [
                'confirmDelete' => __('Haluatko varmasti poistaa tämän?', 'sivustamo-master'),
                'copied' => __('Kopioitu leikepöydälle!', 'sivustamo-master'),
            ]
        ]);
    }

    /**
     * Lisää dashboard widget
     */
    public function add_dashboard_widget() {
        if (!current_user_can('manage_sivustamo_ohjeet')) {
            return;
        }

        wp_add_dashboard_widget(
            'sivustamo_master_widget',
            __('Sivustamo Master - Yhteenveto', 'sivustamo-master'),
            [$this, 'render_dashboard_widget']
        );
    }

    /**
     * Renderöi dashboard widget
     */
    public function render_dashboard_widget() {
        global $wpdb;

        $total_ohjeet = wp_count_posts('sivustamo_ohje')->publish;
        $total_sivustot = wp_count_posts('sivustamo_sivusto')->publish;

        $views_table = $wpdb->prefix . 'sivustamo_views';
        $total_views = $wpdb->get_var("SELECT COUNT(*) FROM $views_table");

        $recent_views = $wpdb->get_results(
            "SELECT v.*, p.post_title as ohje_title
             FROM $views_table v
             LEFT JOIN {$wpdb->posts} p ON v.ohje_id = p.ID
             ORDER BY v.viewed_at DESC
             LIMIT 5"
        );

        ?>
        <div class="sivustamo-master-widget">
            <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                <div style="text-align: center;">
                    <strong style="font-size: 24px; color: #2271b1;"><?php echo intval($total_ohjeet); ?></strong><br>
                    <span style="color: #666;"><?php _e('Ohjetta', 'sivustamo-master'); ?></span>
                </div>
                <div style="text-align: center;">
                    <strong style="font-size: 24px; color: #2271b1;"><?php echo intval($total_sivustot); ?></strong><br>
                    <span style="color: #666;"><?php _e('Sivustoa', 'sivustamo-master'); ?></span>
                </div>
                <div style="text-align: center;">
                    <strong style="font-size: 24px; color: #2271b1;"><?php echo intval($total_views); ?></strong><br>
                    <span style="color: #666;"><?php _e('Katselua', 'sivustamo-master'); ?></span>
                </div>
            </div>

            <?php if ($recent_views) : ?>
            <h4 style="margin: 0 0 10px;"><?php _e('Viimeisimmät katselut', 'sivustamo-master'); ?></h4>
            <ul style="margin: 0; padding: 0; list-style: none;">
                <?php foreach ($recent_views as $view) : ?>
                <li style="padding: 5px 0; border-bottom: 1px solid #eee;">
                    <?php echo esc_html($view->ohje_title ?: '-'); ?>
                    <span style="color: #999; font-size: 12px; float: right;">
                        <?php echo human_time_diff(strtotime($view->viewed_at), current_time('timestamp')) . ' ' . __('sitten', 'sivustamo-master'); ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <p style="margin: 15px 0 0;">
                <a href="<?php echo admin_url('admin.php?page=sivustamo-master'); ?>" class="button">
                    <?php _e('Avaa hallintapaneeli', 'sivustamo-master'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Rekisteröi REST-reitit
     */
    public function register_rest_routes() {
        $rest_controller = new API\REST_Controller();
        $rest_controller->register_routes();
    }
}

// Käynnistä plugin
function sivustamo_master() {
    return Sivustamo_Master::get_instance();
}

sivustamo_master();
