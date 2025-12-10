<?php
/**
 * Käyttöoikeusryhmät Custom Post Type
 */

namespace Sivustamo\Master\Post_Types;

class Ryhma_CPT {

    const POST_TYPE = 'sivustamo_ryhma';

    /**
     * Rekisteröi post type
     */
    public static function register() {
        self::register_post_type();

        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [__CLASS__, 'save_meta'], 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [__CLASS__, 'custom_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [__CLASS__, 'custom_column_content'], 10, 2);
    }

    /**
     * Rekisteröi post type
     */
    public static function register_post_type() {
        $labels = [
            'name'                  => __('Käyttöoikeusryhmät', 'sivustamo-master'),
            'singular_name'         => __('Käyttöoikeusryhmä', 'sivustamo-master'),
            'menu_name'             => __('Ryhmät', 'sivustamo-master'),
            'add_new'               => __('Lisää ryhmä', 'sivustamo-master'),
            'add_new_item'          => __('Lisää uusi ryhmä', 'sivustamo-master'),
            'edit_item'             => __('Muokkaa ryhmää', 'sivustamo-master'),
            'new_item'              => __('Uusi ryhmä', 'sivustamo-master'),
            'view_item'             => __('Näytä ryhmä', 'sivustamo-master'),
            'search_items'          => __('Etsi ryhmiä', 'sivustamo-master'),
            'not_found'             => __('Ryhmiä ei löytynyt', 'sivustamo-master'),
            'not_found_in_trash'    => __('Ryhmiä ei löytynyt roskakorista', 'sivustamo-master'),
            'all_items'             => __('Kaikki ryhmät', 'sivustamo-master'),
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
            'show_in_rest'        => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Lisää metaboxit
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'sivustamo_ryhma_settings',
            __('Ryhmän asetukset', 'sivustamo-master'),
            [__CLASS__, 'render_settings_metabox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'sivustamo_ryhma_sivustot',
            __('Ryhmän sivustot', 'sivustamo-master'),
            [__CLASS__, 'render_sivustot_metabox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        add_meta_box(
            'sivustamo_ryhma_stats',
            __('Tilastot', 'sivustamo-master'),
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
        wp_nonce_field('sivustamo_ryhma_settings', 'sivustamo_ryhma_nonce');

        $description = get_post_meta($post->ID, '_ryhma_description', true);
        $is_default = get_post_meta($post->ID, '_ryhma_is_default', true);
        $slug = get_post_meta($post->ID, '_ryhma_slug', true);

        // Generoi slug jos ei ole
        if (empty($slug) && $post->post_status === 'auto-draft') {
            $slug = sanitize_title($post->post_title);
        }

        ?>
        <table class="form-table">
            <tr>
                <th><label for="ryhma_slug"><?php _e('Tunniste (slug)', 'sivustamo-master'); ?></label></th>
                <td>
                    <input type="text" id="ryhma_slug" name="ryhma_slug"
                           value="<?php echo esc_attr($slug); ?>"
                           class="regular-text" pattern="[a-z0-9-]+" placeholder="esim. woocommerce-sivustot">
                    <p class="description"><?php _e('Uniikki tunniste ryhmälle (pienet kirjaimet, numerot, viivat). Käytetään CSV-tuonnissa.', 'sivustamo-master'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ryhma_description"><?php _e('Kuvaus', 'sivustamo-master'); ?></label></th>
                <td>
                    <textarea id="ryhma_description" name="ryhma_description"
                              class="large-text" rows="3"><?php echo esc_textarea($description); ?></textarea>
                    <p class="description"><?php _e('Sisäinen kuvaus ryhmän käyttötarkoituksesta.', 'sivustamo-master'); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php _e('Oletusryhmä', 'sivustamo-master'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="ryhma_is_default" value="1"
                               <?php checked($is_default, '1'); ?>>
                        <?php _e('Tämä on oletusryhmä', 'sivustamo-master'); ?>
                    </label>
                    <p class="description"><?php _e('Kaikki uudet sivustot lisätään automaattisesti oletusryhmään. Vain yksi ryhmä voi olla oletusryhmä.', 'sivustamo-master'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Renderöi sivustot-metabox (vain näyttö)
     */
    public static function render_sivustot_metabox($post) {
        // Hae sivustot jotka kuuluvat tähän ryhmään
        $sivustot = get_posts([
            'post_type' => 'sivustamo_sivusto',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_sivusto_ryhmat',
                    'value' => $post->ID,
                    'compare' => 'LIKE',
                ]
            ],
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        if (empty($sivustot)) {
            echo '<p>' . __('Ei sivustoja tässä ryhmässä.', 'sivustamo-master') . '</p>';
            return;
        }

        echo '<ul style="margin: 0; padding: 0; list-style: none;">';
        foreach ($sivustot as $sivusto) {
            $domain = get_post_meta($sivusto->ID, '_sivusto_domain', true);
            $active = get_post_meta($sivusto->ID, '_sivusto_active', true);
            $status_color = $active === '1' ? 'green' : '#999';
            $status_icon = $active === '1' ? '●' : '○';

            printf(
                '<li style="padding: 5px 0; border-bottom: 1px solid #eee;">
                    <span style="color: %s;">%s</span>
                    <a href="%s">%s</a>
                    <span style="color: #666;">(%s)</span>
                </li>',
                $status_color,
                $status_icon,
                get_edit_post_link($sivusto->ID),
                esc_html($sivusto->post_title),
                esc_html($domain)
            );
        }
        echo '</ul>';
    }

    /**
     * Renderöi tilastot-metabox
     */
    public static function render_stats_metabox($post) {
        // Laske sivustojen määrä
        $sivustot = get_posts([
            'post_type' => 'sivustamo_sivusto',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_sivusto_ryhmat',
                    'value' => $post->ID,
                    'compare' => 'LIKE',
                ]
            ],
        ]);

        $sivusto_count = count($sivustot);

        // Laske aktiiviset
        $active_count = 0;
        foreach ($sivustot as $sivusto_id) {
            if (get_post_meta($sivusto_id, '_sivusto_active', true) === '1') {
                $active_count++;
            }
        }

        ?>
        <p>
            <strong><?php _e('Sivustoja:', 'sivustamo-master'); ?></strong>
            <?php echo intval($sivusto_count); ?>
        </p>
        <p>
            <strong><?php _e('Aktiivisia:', 'sivustamo-master'); ?></strong>
            <?php echo intval($active_count); ?>
        </p>
        <?php
    }

    /**
     * Tallenna meta
     */
    public static function save_meta($post_id, $post) {
        if (!isset($_POST['sivustamo_ryhma_nonce']) ||
            !wp_verify_nonce($_POST['sivustamo_ryhma_nonce'], 'sivustamo_ryhma_settings')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Slug
        if (isset($_POST['ryhma_slug'])) {
            $slug = sanitize_title($_POST['ryhma_slug']);
            update_post_meta($post_id, '_ryhma_slug', $slug);
        }

        // Kuvaus
        if (isset($_POST['ryhma_description'])) {
            update_post_meta($post_id, '_ryhma_description', sanitize_textarea_field($_POST['ryhma_description']));
        }

        // Oletusryhmä
        $is_default = isset($_POST['ryhma_is_default']) ? '1' : '0';

        // Jos tämä asetetaan oletukseksi, poista muilta
        if ($is_default === '1') {
            $other_defaults = get_posts([
                'post_type' => self::POST_TYPE,
                'post_status' => 'any',
                'posts_per_page' => -1,
                'post__not_in' => [$post_id],
                'meta_key' => '_ryhma_is_default',
                'meta_value' => '1',
                'fields' => 'ids',
            ]);

            foreach ($other_defaults as $other_id) {
                update_post_meta($other_id, '_ryhma_is_default', '0');
            }
        }

        update_post_meta($post_id, '_ryhma_is_default', $is_default);
    }

    /**
     * Mukautetut sarakkeet
     */
    public static function custom_columns($columns) {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['slug'] = __('Tunniste', 'sivustamo-master');
                $new_columns['sivustot'] = __('Sivustoja', 'sivustamo-master');
                $new_columns['is_default'] = __('Oletus', 'sivustamo-master');
            }
        }

        unset($new_columns['date']);
        return $new_columns;
    }

    /**
     * Mukautettujen sarakkeiden sisältö
     */
    public static function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'slug':
                $slug = get_post_meta($post_id, '_ryhma_slug', true);
                echo '<code>' . esc_html($slug ?: '-') . '</code>';
                break;

            case 'sivustot':
                $sivustot = get_posts([
                    'post_type' => 'sivustamo_sivusto',
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                    'meta_query' => [
                        [
                            'key' => '_sivusto_ryhmat',
                            'value' => $post_id,
                            'compare' => 'LIKE',
                        ]
                    ],
                ]);
                echo count($sivustot);
                break;

            case 'is_default':
                $is_default = get_post_meta($post_id, '_ryhma_is_default', true);
                if ($is_default === '1') {
                    echo '<span style="color: green;">✓ ' . __('Oletus', 'sivustamo-master') . '</span>';
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;
        }
    }

    /**
     * Hae oletusryhmä
     */
    public static function get_default_group() {
        $defaults = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_key' => '_ryhma_is_default',
            'meta_value' => '1',
        ]);

        return !empty($defaults) ? $defaults[0] : null;
    }

    /**
     * Hae ryhmä slugin perusteella
     */
    public static function get_group_by_slug($slug) {
        $groups = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_key' => '_ryhma_slug',
            'meta_value' => $slug,
        ]);

        return !empty($groups) ? $groups[0] : null;
    }

    /**
     * Hae kaikki ryhmät
     */
    public static function get_all_groups() {
        return get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
    }
}
