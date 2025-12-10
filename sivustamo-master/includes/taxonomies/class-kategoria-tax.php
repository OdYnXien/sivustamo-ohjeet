<?php
/**
 * Ohjekategoriat Taxonomy
 */

namespace Sivustamo\Master\Taxonomies;

class Kategoria_Tax {

    /**
     * Taxonomy slug
     */
    const TAXONOMY = 'sivustamo_kategoria';

    /**
     * Rekisteröi taxonomy
     */
    public static function register() {
        // Rekisteröi taxonomy suoraan (kutsutaan jo init-hookista)
        self::register_taxonomy();

        // Lisää muut hookit
        add_action(self::TAXONOMY . '_add_form_fields', [__CLASS__, 'add_form_fields']);
        add_action(self::TAXONOMY . '_edit_form_fields', [__CLASS__, 'edit_form_fields'], 10, 2);
        add_action('created_' . self::TAXONOMY, [__CLASS__, 'save_term_meta'], 10, 2);
        add_action('edited_' . self::TAXONOMY, [__CLASS__, 'save_term_meta'], 10, 2);
        add_filter('manage_edit-' . self::TAXONOMY . '_columns', [__CLASS__, 'custom_columns']);
        add_filter('manage_' . self::TAXONOMY . '_custom_column', [__CLASS__, 'custom_column_content'], 10, 3);
    }

    /**
     * Rekisteröi taxonomy
     */
    public static function register_taxonomy() {
        $labels = [
            'name'                       => __('Kategoriat', 'sivustamo-master'),
            'singular_name'              => __('Kategoria', 'sivustamo-master'),
            'menu_name'                  => __('Kategoriat', 'sivustamo-master'),
            'all_items'                  => __('Kaikki kategoriat', 'sivustamo-master'),
            'edit_item'                  => __('Muokkaa kategoriaa', 'sivustamo-master'),
            'view_item'                  => __('Näytä kategoria', 'sivustamo-master'),
            'update_item'                => __('Päivitä kategoria', 'sivustamo-master'),
            'add_new_item'               => __('Lisää uusi kategoria', 'sivustamo-master'),
            'new_item_name'              => __('Uuden kategorian nimi', 'sivustamo-master'),
            'parent_item'                => __('Yläkategoria', 'sivustamo-master'),
            'parent_item_colon'          => __('Yläkategoria:', 'sivustamo-master'),
            'search_items'               => __('Etsi kategorioita', 'sivustamo-master'),
            'not_found'                  => __('Kategorioita ei löytynyt', 'sivustamo-master'),
        ];

        $args = [
            'labels'            => $labels,
            'public'            => false,
            'publicly_queryable' => false,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'rewrite'           => false,
            'query_var'         => false,
        ];

        register_taxonomy(self::TAXONOMY, ['sivustamo_ohje'], $args);
    }

    /**
     * Lisää kentät lisäyslomakkeeseen
     */
    public static function add_form_fields() {
        ?>
        <div class="form-field">
            <label for="kategoria_icon"><?php _e('Ikoni', 'sivustamo-master'); ?></label>
            <input type="text" name="kategoria_icon" id="kategoria_icon" value="" placeholder="dashicons-category">
            <p class="description"><?php _e('Dashicon-luokka (esim. dashicons-category, dashicons-admin-tools)', 'sivustamo-master'); ?></p>
        </div>

        <div class="form-field">
            <label for="kategoria_order"><?php _e('Järjestysnumero', 'sivustamo-master'); ?></label>
            <input type="number" name="kategoria_order" id="kategoria_order" value="0" min="0" step="1">
            <p class="description"><?php _e('Pienemmät numerot näytetään ensin', 'sivustamo-master'); ?></p>
        </div>

        <div class="form-field">
            <label for="kategoria_roles"><?php _e('Käyttöoikeudet', 'sivustamo-master'); ?></label>
            <?php
            $all_roles = wp_roles()->get_names();
            foreach ($all_roles as $role_slug => $role_name) :
                $checked = in_array($role_slug, ['administrator', 'editor', 'shop_manager']);
            ?>
                <label style="display: block; margin-bottom: 3px;">
                    <input type="checkbox" name="kategoria_roles[]"
                           value="<?php echo esc_attr($role_slug); ?>"
                           <?php checked($checked); ?>>
                    <?php echo esc_html(translate_user_role($role_name)); ?>
                </label>
            <?php endforeach; ?>
            <p class="description"><?php _e('Käyttäjäryhmät jotka voivat nähdä tämän kategorian ohjeet', 'sivustamo-master'); ?></p>
        </div>
        <?php
    }

    /**
     * Lisää kentät muokkauslomakkeeseen
     */
    public static function edit_form_fields($term, $taxonomy) {
        $icon = get_term_meta($term->term_id, '_kategoria_icon', true);
        $order = get_term_meta($term->term_id, '_kategoria_order', true);
        $roles = get_term_meta($term->term_id, '_kategoria_roles', true);

        if (!is_array($roles)) {
            $roles = ['administrator', 'editor', 'shop_manager'];
        }

        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="kategoria_icon"><?php _e('Ikoni', 'sivustamo-master'); ?></label>
            </th>
            <td>
                <input type="text" name="kategoria_icon" id="kategoria_icon"
                       value="<?php echo esc_attr($icon); ?>" placeholder="dashicons-category">
                <p class="description"><?php _e('Dashicon-luokka (esim. dashicons-category, dashicons-admin-tools)', 'sivustamo-master'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row">
                <label for="kategoria_order"><?php _e('Järjestysnumero', 'sivustamo-master'); ?></label>
            </th>
            <td>
                <input type="number" name="kategoria_order" id="kategoria_order"
                       value="<?php echo esc_attr($order); ?>" min="0" step="1">
                <p class="description"><?php _e('Pienemmät numerot näytetään ensin', 'sivustamo-master'); ?></p>
            </td>
        </tr>

        <tr class="form-field">
            <th scope="row"><?php _e('Käyttöoikeudet', 'sivustamo-master'); ?></th>
            <td>
                <?php
                $all_roles = wp_roles()->get_names();
                foreach ($all_roles as $role_slug => $role_name) :
                ?>
                    <label style="display: block; margin-bottom: 3px;">
                        <input type="checkbox" name="kategoria_roles[]"
                               value="<?php echo esc_attr($role_slug); ?>"
                               <?php checked(in_array($role_slug, $roles)); ?>>
                        <?php echo esc_html(translate_user_role($role_name)); ?>
                    </label>
                <?php endforeach; ?>
                <p class="description"><?php _e('Käyttäjäryhmät jotka voivat nähdä tämän kategorian ohjeet', 'sivustamo-master'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Tallenna term meta
     */
    public static function save_term_meta($term_id, $tt_id) {
        // Ikoni
        if (isset($_POST['kategoria_icon'])) {
            update_term_meta($term_id, '_kategoria_icon', sanitize_text_field($_POST['kategoria_icon']));
        }

        // Järjestys
        if (isset($_POST['kategoria_order'])) {
            update_term_meta($term_id, '_kategoria_order', intval($_POST['kategoria_order']));
        }

        // Käyttöoikeudet
        if (isset($_POST['kategoria_roles']) && is_array($_POST['kategoria_roles'])) {
            $roles = array_map('sanitize_text_field', $_POST['kategoria_roles']);
            update_term_meta($term_id, '_kategoria_roles', $roles);
        } else {
            update_term_meta($term_id, '_kategoria_roles', []);
        }
    }

    /**
     * Mukautetut sarakkeet
     */
    public static function custom_columns($columns) {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'name') {
                $new_columns['icon'] = __('Ikoni', 'sivustamo-master');
                $new_columns['order'] = __('Järjestys', 'sivustamo-master');
            }
        }

        return $new_columns;
    }

    /**
     * Mukautettujen sarakkeiden sisältö
     */
    public static function custom_column_content($content, $column_name, $term_id) {
        switch ($column_name) {
            case 'icon':
                $icon = get_term_meta($term_id, '_kategoria_icon', true);
                if ($icon) {
                    return '<span class="dashicons ' . esc_attr($icon) . '"></span> ' . esc_html($icon);
                }
                return '—';

            case 'order':
                $order = get_term_meta($term_id, '_kategoria_order', true);
                return $order !== '' ? intval($order) : '0';
        }

        return $content;
    }
}
