<?php
/**
 * Ohjeet Custom Post Type (Client)
 */

namespace Sivustamo\Client\Post_Types;

class Ohje_CPT {

    const POST_TYPE = 'sivustamo_ohje';

    /**
     * Rekisteröi
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
            'name'               => __('Ohjeet', 'sivustamo'),
            'singular_name'      => __('Ohje', 'sivustamo'),
            'menu_name'          => __('Sivustamo Ohjeet', 'sivustamo'),
            'add_new'            => __('Lisää paikallinen ohje', 'sivustamo'),
            'add_new_item'       => __('Lisää uusi paikallinen ohje', 'sivustamo'),
            'edit_item'          => __('Muokkaa ohjetta', 'sivustamo'),
            'new_item'           => __('Uusi ohje', 'sivustamo'),
            'view_item'          => __('Näytä ohje', 'sivustamo'),
            'search_items'       => __('Etsi ohjeita', 'sivustamo'),
            'not_found'          => __('Ohjeita ei löytynyt', 'sivustamo'),
            'not_found_in_trash' => __('Ohjeita ei löytynyt roskakorista', 'sivustamo'),
        ];

        $args = [
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => true,  // Tarvitaan frontendin hakuihin
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-book-alt',
            'query_var'           => false,
            'rewrite'             => false,  // Ei omia rewrite-sääntöjä, käytetään custom routing
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 30,
            'supports'            => ['title', 'editor', 'excerpt', 'thumbnail'],
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
            'sivustamo_ohje_sync',
            __('Synkronointitila', 'sivustamo'),
            [__CLASS__, 'render_sync_metabox'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'sivustamo_ohje_settings',
            __('Ohjeen asetukset', 'sivustamo'),
            [__CLASS__, 'render_settings_metabox'],
            self::POST_TYPE,
            'side',
            'default'
        );

        add_meta_box(
            'sivustamo_ohje_roles',
            __('Käyttöoikeudet', 'sivustamo'),
            [__CLASS__, 'render_roles_metabox'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Renderöi synkronointi-metabox
     */
    public static function render_sync_metabox($post) {
        $source = get_post_meta($post->ID, '_ohje_source', true) ?: 'local';
        $master_id = get_post_meta($post->ID, '_ohje_master_id', true);
        $master_version = get_post_meta($post->ID, '_ohje_master_version', true);
        $local_modified = get_post_meta($post->ID, '_ohje_local_modified', true);

        wp_nonce_field('sivustamo_ohje_meta', 'sivustamo_ohje_nonce');

        ?>
        <div class="sivustamo-sync-info">
            <?php if ($source === 'master') : ?>
                <p>
                    <strong><?php _e('Lähde:', 'sivustamo'); ?></strong>
                    <?php _e('Synkronoitu masterilta', 'sivustamo'); ?>
                </p>
                <p>
                    <strong><?php _e('Master ID:', 'sivustamo'); ?></strong>
                    <?php echo intval($master_id); ?>
                </p>
                <p>
                    <strong><?php _e('Versio:', 'sivustamo'); ?></strong>
                    <?php echo intval($master_version); ?>
                </p>

                <?php if ($local_modified === '1') : ?>
                    <div class="notice notice-warning inline">
                        <p><?php _e('Tätä ohjetta on muokattu paikallisesti. Se ei päivity automaattisesti.', 'sivustamo'); ?></p>
                    </div>
                    <p>
                        <button type="button" class="button sivustamo-sync-reset" data-post-id="<?php echo $post->ID; ?>">
                            <?php _e('Synkronoi lähteen kanssa', 'sivustamo'); ?>
                        </button>
                    </p>
                    <p class="description">
                        <?php _e('Tämä korvaa paikalliset muutokset master-versiolla.', 'sivustamo'); ?>
                    </p>
                <?php else : ?>
                    <div class="notice notice-success inline">
                        <p><?php _e('Ohje on ajan tasalla.', 'sivustamo'); ?></p>
                    </div>
                <?php endif; ?>

            <?php else : ?>
                <div class="notice notice-info inline">
                    <p><?php _e('Tämä on paikallinen ohje, joka ei synkronoidu.', 'sivustamo'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renderöi asetukset-metabox
     */
    public static function render_settings_metabox($post) {
        $priority = get_post_meta($post->ID, '_ohje_priority', true);
        $icon = get_post_meta($post->ID, '_ohje_icon', true);

        ?>
        <p>
            <label for="ohje_priority"><?php _e('Järjestysnumero:', 'sivustamo'); ?></label>
            <input type="number" id="ohje_priority" name="ohje_priority"
                   value="<?php echo esc_attr($priority); ?>"
                   class="widefat" min="0" step="1">
        </p>

        <p>
            <label for="ohje_icon"><?php _e('Ikoni:', 'sivustamo'); ?></label>
            <input type="text" id="ohje_icon" name="ohje_icon"
                   value="<?php echo esc_attr($icon); ?>"
                   class="widefat" placeholder="dashicons-book">
        </p>
        <?php
    }

    /**
     * Renderöi käyttöoikeudet-metabox
     */
    public static function render_roles_metabox($post) {
        $saved_roles = get_post_meta($post->ID, '_ohje_roles', true);
        if (!is_array($saved_roles)) {
            $saved_roles = ['administrator', 'editor', 'shop_manager'];
        }

        $all_roles = wp_roles()->get_names();

        ?>
        <p><?php _e('Käyttäjäryhmät jotka voivat nähdä tämän ohjeen:', 'sivustamo'); ?></p>
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
     * Tallenna meta
     */
    public static function save_meta($post_id, $post) {
        if (!isset($_POST['sivustamo_ohje_nonce']) ||
            !wp_verify_nonce($_POST['sivustamo_ohje_nonce'], 'sivustamo_ohje_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Tallenna asetukset
        if (isset($_POST['ohje_priority'])) {
            update_post_meta($post_id, '_ohje_priority', intval($_POST['ohje_priority']));
        }

        if (isset($_POST['ohje_icon'])) {
            update_post_meta($post_id, '_ohje_icon', sanitize_text_field($_POST['ohje_icon']));
        }

        if (isset($_POST['ohje_roles'])) {
            $roles = array_map('sanitize_text_field', $_POST['ohje_roles']);
            update_post_meta($post_id, '_ohje_roles', $roles);
        } else {
            update_post_meta($post_id, '_ohje_roles', []);
        }

        // Merkitse paikallisesti muokatuksi jos synkronoitu ohje
        $source = get_post_meta($post_id, '_ohje_source', true);
        if ($source === 'master') {
            update_post_meta($post_id, '_ohje_local_modified', '1');
        }

        // Jos uusi ohje, merkitse paikalliseksi
        if (empty($source)) {
            update_post_meta($post_id, '_ohje_source', 'local');
        }
    }

    /**
     * Mukautetut sarakkeet
     */
    public static function custom_columns($columns) {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['kategoria'] = __('Kategoria', 'sivustamo');
                $new_columns['source'] = __('Lähde', 'sivustamo');
                $new_columns['roles'] = __('Käyttöoikeudet', 'sivustamo');
            }
        }

        return $new_columns;
    }

    /**
     * Mukautettujen sarakkeiden sisältö
     */
    public static function custom_column_content($column, $post_id) {
        switch ($column) {
            case 'kategoria':
                $terms = get_the_terms($post_id, 'sivustamo_kategoria');
                if ($terms && !is_wp_error($terms)) {
                    echo implode(', ', wp_list_pluck($terms, 'name'));
                } else {
                    echo '—';
                }
                break;

            case 'source':
                $source = get_post_meta($post_id, '_ohje_source', true);
                $local_modified = get_post_meta($post_id, '_ohje_local_modified', true);

                if ($source === 'master') {
                    if ($local_modified === '1') {
                        echo '<span style="color: #dba617;">● ' . __('Muokattu', 'sivustamo') . '</span>';
                    } else {
                        echo '<span style="color: green;">● ' . __('Synkronoitu', 'sivustamo') . '</span>';
                    }
                } else {
                    echo '<span style="color: #2271b1;">○ ' . __('Paikallinen', 'sivustamo') . '</span>';
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
        }
    }
}
