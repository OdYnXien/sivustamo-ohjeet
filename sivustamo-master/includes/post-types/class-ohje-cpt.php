<?php
/**
 * Ohjeet Custom Post Type
 */

namespace Sivustamo\Master\Post_Types;

class Ohje_CPT {

    /**
     * Post type slug
     */
    const POST_TYPE = 'sivustamo_ohje';

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
    }

    /**
     * Rekisteröi post type
     */
    public static function register_post_type() {
        $labels = [
            'name'                  => __('Ohjeet', 'sivustamo-master'),
            'singular_name'         => __('Ohje', 'sivustamo-master'),
            'menu_name'             => __('Ohjeet', 'sivustamo-master'),
            'add_new'               => __('Lisää uusi', 'sivustamo-master'),
            'add_new_item'          => __('Lisää uusi ohje', 'sivustamo-master'),
            'edit_item'             => __('Muokkaa ohjetta', 'sivustamo-master'),
            'new_item'              => __('Uusi ohje', 'sivustamo-master'),
            'view_item'             => __('Näytä ohje', 'sivustamo-master'),
            'search_items'          => __('Etsi ohjeita', 'sivustamo-master'),
            'not_found'             => __('Ohjeita ei löytynyt', 'sivustamo-master'),
            'not_found_in_trash'    => __('Ohjeita ei löytynyt roskakorista', 'sivustamo-master'),
            'all_items'             => __('Kaikki ohjeet', 'sivustamo-master'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => true,  // Tarvitaan API:n get_posts() -kutsuihin
            'show_ui'             => true,
            'show_in_menu'        => 'sivustamo-master',
            'query_var'           => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions'],
            'show_in_rest'        => true,
            'taxonomies'          => ['sivustamo_kategoria'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Lisää metaboxit
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'sivustamo_ohje_settings',
            __('Ohjeen asetukset', 'sivustamo-master'),
            [__CLASS__, 'render_settings_metabox'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'sivustamo_ohje_ryhmat',
            __('Näkyvyys (ryhmät)', 'sivustamo-master'),
            [__CLASS__, 'render_ryhmat_metabox'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'sivustamo_ohje_roles',
            __('Käyttäjäroolit', 'sivustamo-master'),
            [__CLASS__, 'render_roles_metabox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'sivustamo_ohje_stats',
            __('Statistiikka', 'sivustamo-master'),
            [__CLASS__, 'render_stats_metabox'],
            self::POST_TYPE,
            'side',
            'low'
        );
    }

    /**
     * Renderöi asetukset-metabox
     */
    public static function render_settings_metabox($post) {
        wp_nonce_field('sivustamo_ohje_settings', 'sivustamo_ohje_nonce');

        $priority = get_post_meta($post->ID, '_ohje_priority', true);
        $version = get_post_meta($post->ID, '_ohje_version', true) ?: 1;
        $icon = get_post_meta($post->ID, '_ohje_icon', true);

        ?>
        <p>
            <label for="ohje_priority"><?php _e('Järjestysnumero:', 'sivustamo-master'); ?></label>
            <input type="number" id="ohje_priority" name="ohje_priority"
                   value="<?php echo esc_attr($priority); ?>"
                   class="widefat" min="0" step="1">
        </p>

        <p>
            <label for="ohje_icon"><?php _e('Ikoni (dashicon):', 'sivustamo-master'); ?></label>
            <input type="text" id="ohje_icon" name="ohje_icon"
                   value="<?php echo esc_attr($icon); ?>"
                   class="widefat" placeholder="dashicons-book">
            <span class="description"><?php _e('Esim: dashicons-book, dashicons-info', 'sivustamo-master'); ?></span>
        </p>

        <p>
            <strong><?php _e('Versio:', 'sivustamo-master'); ?></strong>
            <?php echo esc_html($version); ?>
        </p>
        <?php
    }

    /**
     * Renderöi ryhmät-metabox
     */
    public static function render_ryhmat_metabox($post) {
        $saved_ryhmat = get_post_meta($post->ID, '_ohje_ryhmat', true);
        if (!is_array($saved_ryhmat)) {
            $saved_ryhmat = [];
        }

        $all_ryhmat = Ryhma_CPT::get_all_groups();

        if (empty($all_ryhmat)) {
            echo '<p>' . __('Ei ryhmiä luotu. ', 'sivustamo-master');
            echo '<a href="' . admin_url('post-new.php?post_type=sivustamo_ryhma') . '">' . __('Luo ensimmäinen ryhmä', 'sivustamo-master') . '</a></p>';
            return;
        }

        ?>
        <p><?php _e('Valitse ryhmät joille tämä ohje näytetään:', 'sivustamo-master'); ?></p>
        <?php
        foreach ($all_ryhmat as $ryhma) {
            $is_default = get_post_meta($ryhma->ID, '_ryhma_is_default', true) === '1';
            // Tyhjä = näytetään kaikille (oletusryhmä riittää)
            $checked = empty($saved_ryhmat) || in_array($ryhma->ID, $saved_ryhmat);
            $label = $ryhma->post_title;
            if ($is_default) {
                $label .= ' <small>(' . __('oletus', 'sivustamo-master') . ')</small>';
            }
            ?>
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="ohje_ryhmat[]"
                       value="<?php echo esc_attr($ryhma->ID); ?>"
                       <?php checked($checked); ?>>
                <?php echo $label; ?>
            </label>
            <?php
        }
        ?>
        <p class="description" style="margin-top: 10px;">
            <?php _e('Jos kaikki on valittu, ohje näkyy kaikille sivustoille. Valitse vain tietyt ryhmät rajoittaaksesi näkyvyyttä.', 'sivustamo-master'); ?>
        </p>
        <?php
    }

    /**
     * Renderöi käyttäjäroolit-metabox
     */
    public static function render_roles_metabox($post) {
        $saved_roles = get_post_meta($post->ID, '_ohje_roles', true);
        if (!is_array($saved_roles)) {
            $saved_roles = ['administrator', 'editor', 'shop_manager'];
        }

        $all_roles = wp_roles()->get_names();

        ?>
        <p><?php _e('Client-sivuston käyttäjäroolit jotka näkevät ohjeen:', 'sivustamo-master'); ?></p>
        <?php foreach ($all_roles as $role_slug => $role_name) : ?>
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="ohje_roles[]"
                       value="<?php echo esc_attr($role_slug); ?>"
                       <?php checked(in_array($role_slug, $saved_roles)); ?>>
                <?php echo esc_html(translate_user_role($role_name)); ?>
            </label>
        <?php endforeach; ?>
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
            "SELECT COUNT(*) FROM $views_table WHERE ohje_id = %d",
            $post->ID
        ));

        $thumbs_up = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $feedback_table WHERE ohje_id = %d AND thumbs = 'up'",
            $post->ID
        ));

        $thumbs_down = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $feedback_table WHERE ohje_id = %d AND thumbs = 'down'",
            $post->ID
        ));

        $avg_stars = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(stars) FROM $feedback_table WHERE ohje_id = %d AND stars IS NOT NULL",
            $post->ID
        ));

        ?>
        <p>
            <strong><?php _e('Katselukerrat:', 'sivustamo-master'); ?></strong>
            <?php echo intval($total_views); ?>
        </p>
        <p>
            <strong><?php _e('Palautteet:', 'sivustamo-master'); ?></strong><br>
            <span style="color: green;">&#x1F44D; <?php echo intval($thumbs_up); ?></span> /
            <span style="color: red;">&#x1F44E; <?php echo intval($thumbs_down); ?></span>
        </p>
        <?php if ($avg_stars) : ?>
        <p>
            <strong><?php _e('Keskiarvo:', 'sivustamo-master'); ?></strong>
            <?php echo number_format($avg_stars, 1); ?> / 5
        </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Tallenna meta-tiedot
     */
    public static function save_meta($post_id, $post) {
        // Tarkista nonce
        if (!isset($_POST['sivustamo_ohje_nonce']) ||
            !wp_verify_nonce($_POST['sivustamo_ohje_nonce'], 'sivustamo_ohje_settings')) {
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

        // Tallenna järjestysnumero
        if (isset($_POST['ohje_priority'])) {
            update_post_meta($post_id, '_ohje_priority', intval($_POST['ohje_priority']));
        }

        // Tallenna ikoni
        if (isset($_POST['ohje_icon'])) {
            update_post_meta($post_id, '_ohje_icon', sanitize_text_field($_POST['ohje_icon']));
        }

        // Tallenna käyttöoikeudet
        if (isset($_POST['ohje_roles']) && is_array($_POST['ohje_roles'])) {
            $roles = array_map('sanitize_text_field', $_POST['ohje_roles']);
            update_post_meta($post_id, '_ohje_roles', $roles);
        } else {
            update_post_meta($post_id, '_ohje_roles', []);
        }

        // Tallenna ryhmät
        if (isset($_POST['ohje_ryhmat']) && is_array($_POST['ohje_ryhmat'])) {
            $ryhmat = array_map('intval', $_POST['ohje_ryhmat']);
            update_post_meta($post_id, '_ohje_ryhmat', $ryhmat);
        } else {
            // Tyhjä = kaikille
            update_post_meta($post_id, '_ohje_ryhmat', []);
        }

        // Päivitä versio jos sisältö muuttui
        $current_version = get_post_meta($post_id, '_ohje_version', true) ?: 0;
        update_post_meta($post_id, '_ohje_version', $current_version + 1);

        // Tallenna muutoshistoriaan
        $changelog = get_post_meta($post_id, '_ohje_changelog', true) ?: [];
        $changelog[] = [
            'version' => $current_version + 1,
            'date' => current_time('mysql'),
            'user' => get_current_user_id(),
        ];
        // Pidä vain viimeiset 50 merkintää
        $changelog = array_slice($changelog, -50);
        update_post_meta($post_id, '_ohje_changelog', $changelog);
    }

    /**
     * Mukautetut sarakkeet
     */
    public static function custom_columns($columns) {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['kategoria'] = __('Kategoria', 'sivustamo-master');
                $new_columns['roles'] = __('Käyttöoikeudet', 'sivustamo-master');
                $new_columns['views'] = __('Katselut', 'sivustamo-master');
                $new_columns['version'] = __('Versio', 'sivustamo-master');
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
            case 'kategoria':
                $terms = get_the_terms($post_id, 'sivustamo_kategoria');
                if ($terms && !is_wp_error($terms)) {
                    echo implode(', ', wp_list_pluck($terms, 'name'));
                } else {
                    echo '—';
                }
                break;

            case 'roles':
                $roles = get_post_meta($post_id, '_ohje_roles', true);
                if (is_array($roles) && !empty($roles)) {
                    echo esc_html(implode(', ', $roles));
                } else {
                    echo '—';
                }
                break;

            case 'views':
                $views_table = $wpdb->prefix . 'sivustamo_views';
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $views_table WHERE ohje_id = %d",
                    $post_id
                ));
                echo intval($count);
                break;

            case 'version':
                $version = get_post_meta($post_id, '_ohje_version', true) ?: 1;
                echo 'v' . intval($version);
                break;
        }
    }
}
