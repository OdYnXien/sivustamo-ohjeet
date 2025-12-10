<?php
/**
 * Sivustot Custom Post Type (Lisenssit)
 */

namespace Sivustamo\Master\Post_Types;

use Sivustamo\Master\Helpers\License_Generator;

class Sivusto_CPT {

    /**
     * Post type slug
     */
    const POST_TYPE = 'sivustamo_sivusto';

    /**
     * Rekisteröi post type
     */
    public static function register() {
        // Rekisteröi post type suoraan (kutsutaan jo init-hookista)
        self::register_post_type();

        // Lisää muut hookit
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_meta'], 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [__CLASS__, 'custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [__CLASS__, 'custom_column_content'], 10, 2);
        add_action('wp_ajax_sivustamo_regenerate_keys', [__CLASS__, 'ajax_regenerate_keys']);
    }

    /**
     * Rekisteröi post type
     */
    public static function register_post_type() {
        $labels = [
            'name'                  => __('Sivustot', 'sivustamo-master'),
            'singular_name'         => __('Sivusto', 'sivustamo-master'),
            'menu_name'             => __('Sivustot', 'sivustamo-master'),
            'add_new'               => __('Lisää uusi', 'sivustamo-master'),
            'add_new_item'          => __('Lisää uusi sivusto', 'sivustamo-master'),
            'edit_item'             => __('Muokkaa sivustoa', 'sivustamo-master'),
            'new_item'              => __('Uusi sivusto', 'sivustamo-master'),
            'view_item'             => __('Näytä sivusto', 'sivustamo-master'),
            'search_items'          => __('Etsi sivustoja', 'sivustamo-master'),
            'not_found'             => __('Sivustoja ei löytynyt', 'sivustamo-master'),
            'not_found_in_trash'    => __('Sivustoja ei löytynyt roskakorista', 'sivustamo-master'),
            'all_items'             => __('Kaikki sivustot', 'sivustamo-master'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => 'sivustamo-master',
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => ['title'],
            'show_in_rest'        => false,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Lisää metaboxit
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'sivustamo_sivusto_settings',
            __('Sivuston asetukset', 'sivustamo-master'),
            [__CLASS__, 'render_settings_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'sivustamo_sivusto_api',
            __('API-avaimet', 'sivustamo-master'),
            [__CLASS__, 'render_api_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'sivustamo_sivusto_access',
            __('Ohjeiden käyttöoikeudet', 'sivustamo-master'),
            [__CLASS__, 'render_access_metabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'sivustamo_sivusto_stats',
            __('Statistiikka', 'sivustamo-master'),
            [__CLASS__, 'render_stats_metabox'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Renderöi asetukset-metabox
     */
    public static function render_settings_metabox($post) {
        wp_nonce_field('sivustamo_sivusto_settings', 'sivustamo_sivusto_nonce');

        $domain = get_post_meta($post->ID, '_sivusto_domain', true);
        $dev_domain = get_post_meta($post->ID, '_sivusto_dev_domain', true);
        $active = get_post_meta($post->ID, '_sivusto_active', true);
        $created = get_post_meta($post->ID, '_sivusto_created', true);
        $last_sync = get_post_meta($post->ID, '_sivusto_last_sync', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="sivusto_domain"><?php _e('Domain', 'sivustamo-master'); ?></label></th>
                <td>
                    <input type="text" id="sivusto_domain" name="sivusto_domain"
                           value="<?php echo esc_attr($domain); ?>"
                           class="regular-text" placeholder="esimerkki.fi">
                    <p class="description"><?php _e('Sivuston tuotanto-domain ilman protokollaa (esim. asiakas.fi)', 'sivustamo-master'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="sivusto_dev_domain"><?php _e('Dev-domain', 'sivustamo-master'); ?></label></th>
                <td>
                    <input type="text" id="sivusto_dev_domain" name="sivusto_dev_domain"
                           value="<?php echo esc_attr($dev_domain); ?>"
                           class="regular-text" placeholder="asiakas.sivustamo.dev">
                    <p class="description"><?php _e('Valinnainen kehitysympäristön domain (esim. asiakas.sivustamo.dev)', 'sivustamo-master'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php _e('Tila', 'sivustamo-master'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="sivusto_active" value="1"
                               <?php checked($active, '1'); ?>>
                        <?php _e('Aktiivinen', 'sivustamo-master'); ?>
                    </label>
                    <p class="description"><?php _e('Vain aktiiviset sivustot voivat synkronoida ohjeita', 'sivustamo-master'); ?></p>
                </td>
            </tr>
            <?php if ($created) : ?>
            <tr>
                <th><?php _e('Luotu', 'sivustamo-master'); ?></th>
                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($created))); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($last_sync) : ?>
            <tr>
                <th><?php _e('Viimeisin synkronointi', 'sivustamo-master'); ?></th>
                <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync))); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Renderöi API-avaimet-metabox
     */
    public static function render_api_metabox($post) {
        $api_key = get_post_meta($post->ID, '_sivusto_api_key', true);
        $secret = get_post_meta($post->ID, '_sivusto_secret', true);

        // Generoi avaimet jos puuttuu
        if (!$api_key || !$secret) {
            $api_key = License_Generator::generate_api_key();
            $secret = License_Generator::generate_secret();

            if ($post->ID) {
                update_post_meta($post->ID, '_sivusto_api_key', $api_key);
                update_post_meta($post->ID, '_sivusto_secret', $secret);
            }
        }

        ?>
        <table class="form-table">
            <tr>
                <th><?php _e('API-avain', 'sivustamo-master'); ?></th>
                <td>
                    <code id="api-key-display" style="font-size: 14px; padding: 5px 10px; background: #f0f0f0; display: inline-block;">
                        <?php echo esc_html($api_key); ?>
                    </code>
                    <button type="button" class="button sivustamo-copy-btn" data-target="api-key-display">
                        <?php _e('Kopioi', 'sivustamo-master'); ?>
                    </button>
                </td>
            </tr>
            <tr>
                <th><?php _e('Salaisuus (Secret)', 'sivustamo-master'); ?></th>
                <td>
                    <code id="secret-display" style="font-size: 14px; padding: 5px 10px; background: #f0f0f0; display: inline-block;">
                        <?php echo esc_html($secret); ?>
                    </code>
                    <button type="button" class="button sivustamo-copy-btn" data-target="secret-display">
                        <?php _e('Kopioi', 'sivustamo-master'); ?>
                    </button>
                </td>
            </tr>
            <tr>
                <th></th>
                <td>
                    <button type="button" class="button button-secondary" id="regenerate-keys-btn"
                            data-post-id="<?php echo $post->ID; ?>">
                        <?php _e('Generoi uudet avaimet', 'sivustamo-master'); ?>
                    </button>
                    <p class="description" style="color: #d63638;">
                        <?php _e('Varoitus: Uusien avainten generointi katkaisee sivuston yhteyden kunnes uudet avaimet on päivitetty.', 'sivustamo-master'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Renderöi käyttöoikeudet-metabox
     */
    public static function render_access_metabox($post) {
        $allowed_kategoriat = get_post_meta($post->ID, '_sivusto_kategoriat', true) ?: [];
        $allowed_ohjeet = get_post_meta($post->ID, '_sivusto_ohjeet', true) ?: [];

        // Hae kaikki kategoriat
        $kategoriat = get_terms([
            'taxonomy' => 'sivustamo_kategoria',
            'hide_empty' => false,
        ]);

        // Hae kaikki ohjeet
        $ohjeet = get_posts([
            'post_type' => 'sivustamo_ohje',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        ?>
        <h4><?php _e('Sallitut kategoriat', 'sivustamo-master'); ?></h4>
        <p class="description"><?php _e('Valitse kategoriat joiden ohjeet sivusto saa. Jos mikään ei ole valittuna, kaikki ohjeet ovat saatavilla.', 'sivustamo-master'); ?></p>

        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-bottom: 20px;">
            <?php if (!empty($kategoriat) && !is_wp_error($kategoriat)) : ?>
                <?php foreach ($kategoriat as $kategoria) : ?>
                    <label style="display: block; margin-bottom: 5px;">
                        <input type="checkbox" name="sivusto_kategoriat[]"
                               value="<?php echo esc_attr($kategoria->term_id); ?>"
                               <?php checked(in_array($kategoria->term_id, (array)$allowed_kategoriat)); ?>>
                        <?php echo esc_html($kategoria->name); ?>
                        <span style="color: #666;">(<?php echo $kategoria->count; ?> ohjetta)</span>
                    </label>
                <?php endforeach; ?>
            <?php else : ?>
                <p><?php _e('Ei kategorioita. Luo ensin kategorioita.', 'sivustamo-master'); ?></p>
            <?php endif; ?>
        </div>

        <h4><?php _e('Tai valitse yksittäiset ohjeet', 'sivustamo-master'); ?></h4>
        <p class="description"><?php _e('Voit myös valita yksittäisiä ohjeita kategorioiden lisäksi tai sijaan.', 'sivustamo-master'); ?></p>

        <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
            <?php if (!empty($ohjeet)) : ?>
                <?php foreach ($ohjeet as $ohje) : ?>
                    <?php
                    $terms = get_the_terms($ohje->ID, 'sivustamo_kategoria');
                    $term_names = $terms && !is_wp_error($terms) ? implode(', ', wp_list_pluck($terms, 'name')) : '';
                    ?>
                    <label style="display: block; margin-bottom: 5px;">
                        <input type="checkbox" name="sivusto_ohjeet[]"
                               value="<?php echo esc_attr($ohje->ID); ?>"
                               <?php checked(in_array($ohje->ID, (array)$allowed_ohjeet)); ?>>
                        <?php echo esc_html($ohje->post_title); ?>
                        <?php if ($term_names) : ?>
                            <span style="color: #666;">(<?php echo esc_html($term_names); ?>)</span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            <?php else : ?>
                <p><?php _e('Ei ohjeita. Luo ensin ohjeita.', 'sivustamo-master'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderöi statistiikka-metabox
     */
    public static function render_stats_metabox($post) {
        global $wpdb;

        $views_table = $wpdb->prefix . 'sivustamo_views';
        $feedback_table = $wpdb->prefix . 'sivustamo_feedback';

        $total_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $views_table WHERE sivusto_id = %d",
            $post->ID
        ));

        $unique_ohjeet = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ohje_id) FROM $views_table WHERE sivusto_id = %d",
            $post->ID
        ));

        $feedback_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $feedback_table WHERE sivusto_id = %d",
            $post->ID
        ));

        ?>
        <p>
            <strong><?php _e('Katselukerrat:', 'sivustamo-master'); ?></strong>
            <?php echo intval($total_views); ?>
        </p>
        <p>
            <strong><?php _e('Eri ohjeita luettu:', 'sivustamo-master'); ?></strong>
            <?php echo intval($unique_ohjeet); ?>
        </p>
        <p>
            <strong><?php _e('Palautteita:', 'sivustamo-master'); ?></strong>
            <?php echo intval($feedback_count); ?>
        </p>
        <?php
    }

    /**
     * Tallenna meta-tiedot
     */
    public static function save_meta($post_id, $post) {
        // Tarkista nonce
        if (!isset($_POST['sivustamo_sivusto_nonce']) ||
            !wp_verify_nonce($_POST['sivustamo_sivusto_nonce'], 'sivustamo_sivusto_settings')) {
            return;
        }

        // Tarkista autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Tarkista oikeudet
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Domain
        if (isset($_POST['sivusto_domain'])) {
            $domain = sanitize_text_field($_POST['sivusto_domain']);
            $domain = preg_replace('#^https?://#', '', $domain); // Poista protokolla
            $domain = rtrim($domain, '/'); // Poista lopun /
            update_post_meta($post_id, '_sivusto_domain', $domain);
        }

        // Dev-domain
        if (isset($_POST['sivusto_dev_domain'])) {
            $dev_domain = sanitize_text_field($_POST['sivusto_dev_domain']);
            $dev_domain = preg_replace('#^https?://#', '', $dev_domain);
            $dev_domain = rtrim($dev_domain, '/');
            update_post_meta($post_id, '_sivusto_dev_domain', $dev_domain);
        }

        // Aktiivinen
        $active = isset($_POST['sivusto_active']) ? '1' : '0';
        update_post_meta($post_id, '_sivusto_active', $active);

        // Kategoriat
        if (isset($_POST['sivusto_kategoriat']) && is_array($_POST['sivusto_kategoriat'])) {
            $kategoriat = array_map('intval', $_POST['sivusto_kategoriat']);
            update_post_meta($post_id, '_sivusto_kategoriat', $kategoriat);
        } else {
            update_post_meta($post_id, '_sivusto_kategoriat', []);
        }

        // Yksittäiset ohjeet
        if (isset($_POST['sivusto_ohjeet']) && is_array($_POST['sivusto_ohjeet'])) {
            $ohjeet = array_map('intval', $_POST['sivusto_ohjeet']);
            update_post_meta($post_id, '_sivusto_ohjeet', $ohjeet);
        } else {
            update_post_meta($post_id, '_sivusto_ohjeet', []);
        }

        // Luontipäivä (vain ensimmäisellä kerralla)
        if (!get_post_meta($post_id, '_sivusto_created', true)) {
            update_post_meta($post_id, '_sivusto_created', current_time('mysql'));
        }

        // Generoi avaimet jos puuttuu
        if (!get_post_meta($post_id, '_sivusto_api_key', true)) {
            update_post_meta($post_id, '_sivusto_api_key', License_Generator::generate_api_key());
        }
        if (!get_post_meta($post_id, '_sivusto_secret', true)) {
            update_post_meta($post_id, '_sivusto_secret', License_Generator::generate_secret());
        }
    }

    /**
     * AJAX: Generoi uudet avaimet
     */
    public static function ajax_regenerate_keys() {
        check_ajax_referer('sivustamo_master_nonce', 'nonce');

        if (!current_user_can('manage_sivustamo_sivustot')) {
            wp_send_json_error(['message' => __('Ei oikeuksia', 'sivustamo-master')]);
        }

        $post_id = intval($_POST['post_id']);

        if (!$post_id || get_post_type($post_id) !== self::POST_TYPE) {
            wp_send_json_error(['message' => __('Virheellinen sivusto', 'sivustamo-master')]);
        }

        $api_key = License_Generator::generate_api_key();
        $secret = License_Generator::generate_secret();

        update_post_meta($post_id, '_sivusto_api_key', $api_key);
        update_post_meta($post_id, '_sivusto_secret', $secret);

        wp_send_json_success([
            'api_key' => $api_key,
            'secret' => $secret,
        ]);
    }

    /**
     * Mukautetut sarakkeet
     */
    public static function custom_columns($columns) {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['domain'] = __('Domain', 'sivustamo-master');
                $new_columns['status'] = __('Tila', 'sivustamo-master');
                $new_columns['last_sync'] = __('Viimeisin synkronointi', 'sivustamo-master');
                $new_columns['views'] = __('Katselut', 'sivustamo-master');
            } elseif ($key !== 'date') {
                $new_columns[$key] = $value;
            }
        }

        return $new_columns;
    }

    /**
     * Mukautettujen sarakkeiden sisältö
     */
    public static function custom_column_content($column, $post_id) {
        global $wpdb;

        switch ($column) {
            case 'domain':
                $domain = get_post_meta($post_id, '_sivusto_domain', true);
                if ($domain) {
                    echo '<a href="https://' . esc_attr($domain) . '" target="_blank">' . esc_html($domain) . '</a>';
                } else {
                    echo '—';
                }
                break;

            case 'status':
                $active = get_post_meta($post_id, '_sivusto_active', true);
                if ($active === '1') {
                    echo '<span style="color: green; font-weight: bold;">● ' . __('Aktiivinen', 'sivustamo-master') . '</span>';
                } else {
                    echo '<span style="color: #999;">○ ' . __('Ei aktiivinen', 'sivustamo-master') . '</span>';
                }
                break;

            case 'last_sync':
                $last_sync = get_post_meta($post_id, '_sivusto_last_sync', true);
                if ($last_sync) {
                    echo esc_html(human_time_diff(strtotime($last_sync), current_time('timestamp'))) . ' ' . __('sitten', 'sivustamo-master');
                } else {
                    echo __('Ei koskaan', 'sivustamo-master');
                }
                break;

            case 'views':
                $views_table = $wpdb->prefix . 'sivustamo_views';
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $views_table WHERE sivusto_id = %d",
                    $post_id
                ));
                echo intval($count);
                break;
        }
    }
}
