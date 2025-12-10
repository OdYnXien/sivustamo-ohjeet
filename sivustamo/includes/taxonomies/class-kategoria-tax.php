<?php
/**
 * Kategoriat Taxonomy (Client)
 */

namespace Sivustamo\Client\Taxonomies;

class Kategoria_Tax {

    const TAXONOMY = 'sivustamo_kategoria';

    /**
     * Rekisteröi
     */
    public static function register() {
        add_action('init', [__CLASS__, 'register_taxonomy']);
    }

    /**
     * Rekisteröi taxonomy
     */
    public static function register_taxonomy() {
        $labels = [
            'name'              => __('Kategoriat', 'sivustamo'),
            'singular_name'     => __('Kategoria', 'sivustamo'),
            'search_items'      => __('Etsi kategorioita', 'sivustamo'),
            'all_items'         => __('Kaikki kategoriat', 'sivustamo'),
            'parent_item'       => __('Yläkategoria', 'sivustamo'),
            'parent_item_colon' => __('Yläkategoria:', 'sivustamo'),
            'edit_item'         => __('Muokkaa kategoriaa', 'sivustamo'),
            'update_item'       => __('Päivitä kategoria', 'sivustamo'),
            'add_new_item'      => __('Lisää kategoria', 'sivustamo'),
            'new_item_name'     => __('Uuden kategorian nimi', 'sivustamo'),
            'menu_name'         => __('Kategoriat', 'sivustamo'),
        ];

        $args = [
            'labels'            => $labels,
            'public'            => false,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud'     => false,
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'rewrite'           => false,
            'query_var'         => false,
        ];

        register_taxonomy(self::TAXONOMY, ['sivustamo_ohje'], $args);
    }
}
