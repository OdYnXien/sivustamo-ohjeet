<?php
/**
 * Asetussivu
 */

namespace Sivustamo\Client\Admin;

use Sivustamo\Client\Sync\API_Client;
use Sivustamo\Client\Sync\Sync_Manager;

class Settings_Page {

    /**
     * Hae asetus - tarkistaa ensin ympäristömuuttujat, sitten optionit
     *
     * Tuetut ympäristömuuttujat (wp-config.php):
     * - SIVUSTAMO_MASTER_URL
     * - SIVUSTAMO_API_KEY
     * - SIVUSTAMO_SECRET
     *
     * @param string $key Asetuksen nimi (ilman sivustamo_ -etuliitettä)
     * @param mixed $default Oletusarvo
     * @return mixed
     */
    public static function get_setting($key, $default = '') {
        // Ympäristömuuttujien nimet
        $env_map = [
            'master_url' => 'SIVUSTAMO_MASTER_URL',
            'api_key' => 'SIVUSTAMO_API_KEY',
            'secret' => 'SIVUSTAMO_SECRET',
        ];

        // Tarkista ympäristömuuttuja
        if (isset($env_map[$key])) {
            $const_name = $env_map[$key];
            if (defined($const_name)) {
                return constant($const_name);
            }
        }

        // Fallback option-tietokantaan
        return get_option('sivustamo_' . $key, $default);
    }

    /**
     * Tarkista onko asetus lukittu ympäristömuuttujalla
     *
     * @param string $key
     * @return bool
     */
    public static function is_setting_locked($key) {
        $env_map = [
            'master_url' => 'SIVUSTAMO_MASTER_URL',
            'api_key' => 'SIVUSTAMO_API_KEY',
            'secret' => 'SIVUSTAMO_SECRET',
        ];

        if (isset($env_map[$key])) {
            return defined($env_map[$key]);
        }

        return false;
    }

    /**
     * Rekisteröi asetukset
     */
    public static function register_settings() {
        register_setting('sivustamo_settings', 'sivustamo_master_url');
        register_setting('sivustamo_settings', 'sivustamo_api_key');
        register_setting('sivustamo_settings', 'sivustamo_secret');
        register_setting('sivustamo_settings', 'sivustamo_base_slug');

        // AJAX handlers
        add_action('wp_ajax_sivustamo_test_connection', [__CLASS__, 'ajax_test_connection']);
        add_action('wp_ajax_sivustamo_sync_now', [__CLASS__, 'ajax_sync_now']);
        add_action('wp_ajax_sivustamo_force_sync', [__CLASS__, 'ajax_force_sync']);
    }

    /**
     * Lisää valikko
     */
    public static function add_menu() {
        add_submenu_page(
            'edit.php?post_type=sivustamo_ohje',
            __('Sivustamo Asetukset', 'sivustamo'),
            __('Asetukset', 'sivustamo'),
            'manage_options',
            'sivustamo-settings',
            [__CLASS__, 'render']
        );
    }

    /**
     * Renderöi asetussivu
     */
    public static function render() {
        // Tarkista lukitukset
        $locked_master_url = self::is_setting_locked('master_url');
        $locked_api_key = self::is_setting_locked('api_key');
        $locked_secret = self::is_setting_locked('secret');

        // Tallenna jos POST (vain ei-lukitut kentät)
        if (isset($_POST['sivustamo_save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'sivustamo_settings-options')) {
            if (!$locked_master_url) {
                update_option('sivustamo_master_url', esc_url_raw($_POST['sivustamo_master_url']));
            }
            if (!$locked_api_key) {
                update_option('sivustamo_api_key', sanitize_text_field($_POST['sivustamo_api_key']));
            }
            if (!$locked_secret) {
                update_option('sivustamo_secret', sanitize_text_field($_POST['sivustamo_secret']));
            }
            update_option('sivustamo_base_slug', sanitize_title($_POST['sivustamo_base_slug']));

            // Flush rewrite rules
            flush_rewrite_rules();

            echo '<div class="notice notice-success"><p>' . __('Asetukset tallennettu.', 'sivustamo') . '</p></div>';
        }

        // Hae asetukset (huomioi ympäristömuuttujat)
        $master_url = self::get_setting('master_url', 'https://sivustamo.dev');
        $api_key = self::get_setting('api_key', '');
        $secret = self::get_setting('secret', '');
        $base_slug = get_option('sivustamo_base_slug', 'sivustamo-ohjeet');
        $last_sync = get_option('sivustamo_last_sync', 0);

        // Näytä huomautus jos jokin on lukittu
        $any_locked = $locked_master_url || $locked_api_key || $locked_secret;
        if ($any_locked) {
            echo '<div class="notice notice-info"><p>';
            echo '<strong>' . __('Osa asetuksista on määritelty wp-config.php -tiedostossa.', 'sivustamo') . '</strong><br>';
            echo __('Nämä asetukset näytetään lukittuina eikä niitä voi muuttaa tästä.', 'sivustamo');
            echo '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Sivustamo Asetukset', 'sivustamo'); ?></h1>

            <form method="post">
                <?php wp_nonce_field('sivustamo_settings-options'); ?>

                <h2><?php _e('API-yhteys', 'sivustamo'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sivustamo_master_url"><?php _e('Master-sivuston URL', 'sivustamo'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="sivustamo_master_url" id="sivustamo_master_url"
                                   value="<?php echo esc_attr($master_url); ?>" class="regular-text"
                                   autocomplete="off" <?php echo $locked_master_url ? 'readonly disabled' : ''; ?>>
                            <?php if ($locked_master_url) : ?>
                                <span class="dashicons dashicons-lock" title="<?php esc_attr_e('Määritelty wp-config.php:ssä', 'sivustamo'); ?>"></span>
                                <code>SIVUSTAMO_MASTER_URL</code>
                            <?php else : ?>
                                <p class="description"><?php _e('Ohjeiden hallintasivuston osoite', 'sivustamo'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sivustamo_api_key"><?php _e('API-avain', 'sivustamo'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="sivustamo_api_key" id="sivustamo_api_key"
                                   value="<?php echo esc_attr($api_key); ?>" class="regular-text"
                                   autocomplete="new-password" data-lpignore="true" data-1p-ignore="true"
                                   <?php echo $locked_api_key ? 'readonly disabled' : ''; ?>>
                            <?php if ($locked_api_key) : ?>
                                <span class="dashicons dashicons-lock" title="<?php esc_attr_e('Määritelty wp-config.php:ssä', 'sivustamo'); ?>"></span>
                                <code>SIVUSTAMO_API_KEY</code>
                            <?php else : ?>
                                <p class="description"><?php _e('Kopioi masterilta: Sivustamo → Sivustot → [sivusto] → API-avaimet', 'sivustamo'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sivustamo_secret"><?php _e('Salaisuus (Secret)', 'sivustamo'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="sivustamo_secret" id="sivustamo_secret"
                                   value="<?php echo esc_attr($locked_secret ? '••••••••••••••••' : $secret); ?>" class="regular-text code"
                                   autocomplete="new-password" data-lpignore="true" data-1p-ignore="true"
                                   style="font-family: monospace;" <?php echo $locked_secret ? 'readonly disabled' : ''; ?>>
                            <?php if ($locked_secret) : ?>
                                <span class="dashicons dashicons-lock" title="<?php esc_attr_e('Määritelty wp-config.php:ssä', 'sivustamo'); ?>"></span>
                                <code>SIVUSTAMO_SECRET</code>
                            <?php else : ?>
                                <p class="description"><?php _e('Kopioi masterilta: Sivustamo → Sivustot → [sivusto] → API-avaimet', 'sivustamo'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Testaa yhteys', 'sivustamo'); ?></th>
                        <td>
                            <button type="button" class="button" id="sivustamo-test-connection">
                                <?php _e('Testaa', 'sivustamo'); ?>
                            </button>
                            <span id="sivustamo-connection-status"></span>
                        </td>
                    </tr>
                </table>

                <h2><?php _e('Näyttöasetukset', 'sivustamo'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="sivustamo_base_slug"><?php _e('URL-polku', 'sivustamo'); ?></label>
                        </th>
                        <td>
                            <code><?php echo home_url('/'); ?></code>
                            <input type="text" name="sivustamo_base_slug" id="sivustamo_base_slug"
                                   value="<?php echo esc_attr($base_slug); ?>" style="width: 200px;">
                            <code>/</code>
                            <p class="description"><?php _e('Ohjeiden URL-polku (oletus: sivustamo-ohjeet)', 'sivustamo'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php _e('Synkronointi', 'sivustamo'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Viimeisin synkronointi', 'sivustamo'); ?></th>
                        <td>
                            <?php if ($last_sync) : ?>
                                <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_sync); ?>
                                (<?php echo human_time_diff($last_sync, current_time('timestamp')) . ' ' . __('sitten', 'sivustamo'); ?>)
                            <?php else : ?>
                                <?php _e('Ei koskaan', 'sivustamo'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Synkronoi nyt', 'sivustamo'); ?></th>
                        <td>
                            <button type="button" class="button button-primary" id="sivustamo-sync-now">
                                <?php _e('Synkronoi ohjeet', 'sivustamo'); ?>
                            </button>
                            <span id="sivustamo-sync-status"></span>
                            <p class="description">
                                <?php _e('Automaattinen synkronointi tapahtuu kerran päivässä.', 'sivustamo'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="sivustamo_save_settings" class="button button-primary"
                           value="<?php _e('Tallenna asetukset', 'sivustamo'); ?>">
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Testaa yhteys
            $('#sivustamo-test-connection').on('click', function() {
                var $btn = $(this);
                var $status = $('#sivustamo-connection-status');

                $btn.prop('disabled', true);
                $status.html('<span class="spinner is-active" style="float:none;"></span>');

                $.ajax({
                    url: sivustamo.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sivustamo_test_connection',
                        nonce: sivustamo.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                        } else {
                            $status.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $status.html('<span style="color:red;">✗ <?php _e('Yhteysvirhe', 'sivustamo'); ?></span>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Synkronoi nyt
            $('#sivustamo-sync-now').on('click', function() {
                var $btn = $(this);
                var $status = $('#sivustamo-sync-status');

                $btn.prop('disabled', true);
                $status.html('<span class="spinner is-active" style="float:none;"></span> <?php _e('Synkronoidaan...', 'sivustamo'); ?>');

                $.ajax({
                    url: sivustamo.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'sivustamo_sync_now',
                        nonce: sivustamo.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<span style="color:green;">✓ ' + response.data.message + '</span>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $status.html('<span style="color:red;">✗ ' + response.data.message + '</span>');
                        }
                    },
                    error: function() {
                        $status.html('<span style="color:red;">✗ <?php _e('Synkronointi epäonnistui', 'sivustamo'); ?></span>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Testaa yhteys
     */
    public static function ajax_test_connection() {
        check_ajax_referer('sivustamo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Ei oikeuksia', 'sivustamo')]);
        }

        $api = new API_Client();

        if (!$api->is_configured()) {
            wp_send_json_error(['message' => __('API-asetukset puuttuvat', 'sivustamo')]);
        }

        $result = $api->verify();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Yhteys OK! Sivusto: %s, Ohjeita saatavilla: %d', 'sivustamo'),
                $result['site_name'],
                $result['ohje_count']
            )
        ]);
    }

    /**
     * AJAX: Synkronoi nyt
     */
    public static function ajax_sync_now() {
        check_ajax_referer('sivustamo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Ei oikeuksia', 'sivustamo')]);
        }

        // Manuaalinen synkronointi hakee aina kaikki ohjeet (full_sync = true)
        $result = Sync_Manager::sync(true);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Synkronoitu! %d uutta, %d päivitettyä ohjetta.', 'sivustamo'),
                $result['synced'],
                $result['updated']
            )
        ]);
    }

    /**
     * AJAX: Pakota synkronointi yksittäiselle ohjeelle
     */
    public static function ajax_force_sync() {
        check_ajax_referer('sivustamo_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Ei oikeuksia', 'sivustamo')]);
        }

        $post_id = intval($_POST['post_id']);

        if (!$post_id) {
            wp_send_json_error(['message' => __('Virheellinen ohje', 'sivustamo')]);
        }

        $result = Sync_Manager::force_sync_ohje($post_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Ohje synkronoitu!', 'sivustamo')]);
    }
}
