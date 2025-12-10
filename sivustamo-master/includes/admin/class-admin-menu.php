<?php
/**
 * Admin-valikko
 */

namespace Sivustamo\Master\Admin;

class Admin_Menu {

    /**
     * Alusta
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
    }

    /**
     * Rekisteröi valikko
     */
    public static function register_menu() {
        // Päävalikko
        add_menu_page(
            __('Sivustamo Master', 'sivustamo-master'),
            __('Sivustamo', 'sivustamo-master'),
            'manage_sivustamo_ohjeet',
            'sivustamo-master',
            [__CLASS__, 'render_dashboard'],
            'dashicons-book-alt',
            30
        );

        // Dashboard (alivalikko)
        add_submenu_page(
            'sivustamo-master',
            __('Hallintapaneeli', 'sivustamo-master'),
            __('Hallintapaneeli', 'sivustamo-master'),
            'manage_sivustamo_ohjeet',
            'sivustamo-master',
            [__CLASS__, 'render_dashboard']
        );

        // Statistiikka
        add_submenu_page(
            'sivustamo-master',
            __('Statistiikka', 'sivustamo-master'),
            __('Statistiikka', 'sivustamo-master'),
            'manage_sivustamo_ohjeet',
            'sivustamo-stats',
            [Stats_Dashboard::class, 'render']
        );

        // Asetukset
        add_submenu_page(
            'sivustamo-master',
            __('Asetukset', 'sivustamo-master'),
            __('Asetukset', 'sivustamo-master'),
            'manage_sivustamo_ohjeet',
            'sivustamo-settings',
            [__CLASS__, 'render_settings']
        );
    }

    /**
     * Renderöi hallintapaneeli
     */
    public static function render_dashboard() {
        global $wpdb;

        // Statistiikat - käytetään turvallisia tapoja
        $ohjeet_counts = wp_count_posts('sivustamo_ohje');
        $total_ohjeet = isset($ohjeet_counts->publish) ? $ohjeet_counts->publish : 0;

        $sivustot_counts = wp_count_posts('sivustamo_sivusto');
        $total_sivustot = isset($sivustot_counts->publish) ? $sivustot_counts->publish : 0;

        $kategoriat_count = wp_count_terms(['taxonomy' => 'sivustamo_kategoria', 'hide_empty' => false]);
        $total_kategoriat = is_wp_error($kategoriat_count) ? 0 : $kategoriat_count;

        $views_table = $wpdb->prefix . 'sivustamo_views';
        $feedback_table = $wpdb->prefix . 'sivustamo_feedback';

        $total_views = $wpdb->get_var("SELECT COUNT(*) FROM $views_table");
        $total_feedback = $wpdb->get_var("SELECT COUNT(*) FROM $feedback_table");

        // Viimeisimmät tapahtumat
        $recent_views = $wpdb->get_results(
            "SELECT v.*, p.post_title as ohje_title, s.post_title as sivusto_name
             FROM $views_table v
             LEFT JOIN {$wpdb->posts} p ON v.ohje_id = p.ID
             LEFT JOIN {$wpdb->posts} s ON v.sivusto_id = s.ID
             ORDER BY v.viewed_at DESC
             LIMIT 10"
        );

        $recent_feedback = $wpdb->get_results(
            "SELECT f.*, p.post_title as ohje_title, s.post_title as sivusto_name
             FROM $feedback_table f
             LEFT JOIN {$wpdb->posts} p ON f.ohje_id = p.ID
             LEFT JOIN {$wpdb->posts} s ON f.sivusto_id = s.ID
             ORDER BY f.created_at DESC
             LIMIT 10"
        );

        ?>
        <div class="wrap">
            <h1><?php _e('Sivustamo Master - Hallintapaneeli', 'sivustamo-master'); ?></h1>

            <div class="sivustamo-dashboard-grid">
                <!-- Yhteenveto -->
                <div class="sivustamo-card">
                    <h2><?php _e('Yhteenveto', 'sivustamo-master'); ?></h2>
                    <div class="sivustamo-stats-grid">
                        <div class="sivustamo-stat">
                            <span class="sivustamo-stat-number"><?php echo intval($total_ohjeet); ?></span>
                            <span class="sivustamo-stat-label"><?php _e('Ohjetta', 'sivustamo-master'); ?></span>
                        </div>
                        <div class="sivustamo-stat">
                            <span class="sivustamo-stat-number"><?php echo intval($total_sivustot); ?></span>
                            <span class="sivustamo-stat-label"><?php _e('Sivustoa', 'sivustamo-master'); ?></span>
                        </div>
                        <div class="sivustamo-stat">
                            <span class="sivustamo-stat-number"><?php echo intval($total_kategoriat); ?></span>
                            <span class="sivustamo-stat-label"><?php _e('Kategoriaa', 'sivustamo-master'); ?></span>
                        </div>
                        <div class="sivustamo-stat">
                            <span class="sivustamo-stat-number"><?php echo intval($total_views); ?></span>
                            <span class="sivustamo-stat-label"><?php _e('Katselukertaa', 'sivustamo-master'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Pikavalinnat -->
                <div class="sivustamo-card">
                    <h2><?php _e('Pikavalinnat', 'sivustamo-master'); ?></h2>
                    <p>
                        <a href="<?php echo admin_url('post-new.php?post_type=sivustamo_ohje'); ?>" class="button button-primary">
                            <?php _e('Lisää uusi ohje', 'sivustamo-master'); ?>
                        </a>
                        <a href="<?php echo admin_url('post-new.php?post_type=sivustamo_sivusto'); ?>" class="button">
                            <?php _e('Lisää uusi sivusto', 'sivustamo-master'); ?>
                        </a>
                        <a href="<?php echo admin_url('edit-tags.php?taxonomy=sivustamo_kategoria&post_type=sivustamo_ohje'); ?>" class="button">
                            <?php _e('Hallitse kategorioita', 'sivustamo-master'); ?>
                        </a>
                    </p>
                </div>

                <!-- Viimeisimmät katselut -->
                <div class="sivustamo-card">
                    <h2><?php _e('Viimeisimmät katselut', 'sivustamo-master'); ?></h2>
                    <?php if ($recent_views) : ?>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Ohje', 'sivustamo-master'); ?></th>
                                    <th><?php _e('Sivusto', 'sivustamo-master'); ?></th>
                                    <th><?php _e('Aika', 'sivustamo-master'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_views as $view) : ?>
                                    <tr>
                                        <td><?php echo esc_html($view->ohje_title ?: '-'); ?></td>
                                        <td><?php echo esc_html($view->sivusto_name ?: '-'); ?></td>
                                        <td><?php echo esc_html(human_time_diff(strtotime($view->viewed_at), current_time('timestamp')) . ' ' . __('sitten', 'sivustamo-master')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php _e('Ei katseluita vielä.', 'sivustamo-master'); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Viimeisimmät palautteet -->
                <div class="sivustamo-card">
                    <h2><?php _e('Viimeisimmät palautteet', 'sivustamo-master'); ?></h2>
                    <?php if ($recent_feedback) : ?>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Ohje', 'sivustamo-master'); ?></th>
                                    <th><?php _e('Arvio', 'sivustamo-master'); ?></th>
                                    <th><?php _e('Kommentti', 'sivustamo-master'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_feedback as $fb) : ?>
                                    <tr>
                                        <td><?php echo esc_html($fb->ohje_title ?: '-'); ?></td>
                                        <td>
                                            <?php if ($fb->thumbs === 'up') : ?>
                                                <span style="color: green;">&#x1F44D;</span>
                                            <?php else : ?>
                                                <span style="color: red;">&#x1F44E;</span>
                                            <?php endif; ?>
                                            <?php if ($fb->stars) : ?>
                                                (<?php echo intval($fb->stars); ?>/5)
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(wp_trim_words($fb->comment, 10) ?: '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php _e('Ei palautteita vielä.', 'sivustamo-master'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
            .sivustamo-dashboard-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .sivustamo-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
            }
            .sivustamo-card h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .sivustamo-stats-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            .sivustamo-stat {
                text-align: center;
                padding: 15px;
                background: #f8f9fa;
                border-radius: 4px;
            }
            .sivustamo-stat-number {
                display: block;
                font-size: 32px;
                font-weight: bold;
                color: #2271b1;
            }
            .sivustamo-stat-label {
                color: #666;
            }
        </style>
        <?php
    }

    /**
     * Renderöi asetukset
     */
    public static function render_settings() {
        // Tallenna asetukset
        if (isset($_POST['sivustamo_save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'sivustamo_settings')) {
            update_option('sivustamo_master_api_enabled', isset($_POST['api_enabled']) ? '1' : '0');
            update_option('sivustamo_master_require_signature', isset($_POST['require_signature']) ? '1' : '0');

            echo '<div class="notice notice-success"><p>' . __('Asetukset tallennettu.', 'sivustamo-master') . '</p></div>';
        }

        $api_enabled = get_option('sivustamo_master_api_enabled', '1');
        $require_signature = get_option('sivustamo_master_require_signature', '1');

        ?>
        <div class="wrap">
            <h1><?php _e('Sivustamo Master - Asetukset', 'sivustamo-master'); ?></h1>

            <form method="post">
                <?php wp_nonce_field('sivustamo_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('API-tila', 'sivustamo-master'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="api_enabled" value="1" <?php checked($api_enabled, '1'); ?>>
                                <?php _e('API käytössä', 'sivustamo-master'); ?>
                            </label>
                            <p class="description"><?php _e('Salli client-sivustojen hakea ohjeita API:n kautta.', 'sivustamo-master'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Tietoturva', 'sivustamo-master'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_signature" value="1" <?php checked($require_signature, '1'); ?>>
                                <?php _e('Vaadi allekirjoitus', 'sivustamo-master'); ?>
                            </label>
                            <p class="description"><?php _e('Vaadi HMAC-allekirjoitus kaikissa API-pyynnöissä (suositeltu).', 'sivustamo-master'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('API-osoite', 'sivustamo-master'); ?></th>
                        <td>
                            <code><?php echo rest_url('sivustamo/v1/'); ?></code>
                            <p class="description"><?php _e('Tämä on API:n perusosoite.', 'sivustamo-master'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="sivustamo_save_settings" class="button button-primary" value="<?php _e('Tallenna asetukset', 'sivustamo-master'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}

// Alusta
Admin_Menu::init();
