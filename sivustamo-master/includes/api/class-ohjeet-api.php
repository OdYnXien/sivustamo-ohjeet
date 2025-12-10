<?php
/**
 * Ohjeet API
 */

namespace Sivustamo\Master\API;

use Sivustamo\Master\Helpers\Media_Handler;

class Ohjeet_API {

    /**
     * Hae kaikki ohjeet
     */
    public static function get_ohjeet(\WP_REST_Request $request) {
        $site_id = $request->get_param('_sivusto_id');

        if (!$site_id) {
            return new \WP_Error('not_authenticated', __('Autentikointi epäonnistui', 'sivustamo-master'), ['status' => 401]);
        }

        // Hae sivuston sallitut kategoriat ja ohjeet
        $allowed_kategoriat = get_post_meta($site_id, '_sivusto_kategoriat', true) ?: [];
        $allowed_ohjeet = get_post_meta($site_id, '_sivusto_ohjeet', true) ?: [];

        // Query-parametrit
        $modified_after = $request->get_param('modified_after');
        $kategoria_slugs = $request->get_param('kategoriat');

        // Rakenna query
        $args = [
            'post_type' => 'sivustamo_ohje',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => '_ohje_priority',
            'order' => 'ASC',
        ];

        // Suodata kategorioiden mukaan
        if (!empty($allowed_kategoriat) && empty($allowed_ohjeet)) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'sivustamo_kategoria',
                    'field' => 'term_id',
                    'terms' => $allowed_kategoriat,
                ]
            ];
        } elseif (!empty($allowed_ohjeet)) {
            $args['post__in'] = $allowed_ohjeet;
        }

        // Jos pyydetty tietty kategoria
        if ($kategoria_slugs) {
            $slugs = explode(',', $kategoria_slugs);
            if (!isset($args['tax_query'])) {
                $args['tax_query'] = [];
            }
            $args['tax_query'][] = [
                'taxonomy' => 'sivustamo_kategoria',
                'field' => 'slug',
                'terms' => $slugs,
            ];
        }

        // Jos haetaan vain muuttuneet
        if ($modified_after) {
            $args['date_query'] = [
                [
                    'column' => 'post_modified_gmt',
                    'after' => date('Y-m-d H:i:s', (int) $modified_after),
                ]
            ];
        }

        $posts = get_posts($args);
        $ohjeet = [];

        foreach ($posts as $post) {
            $ohjeet[] = self::format_ohje($post, true);  // true = sisällytä content
        }

        // Hae kategoriat
        $kategoriat = self::get_allowed_kategoriat($site_id);

        return rest_ensure_response([
            'ohjeet' => $ohjeet,
            'kategoriat' => $kategoriat,
            'total' => count($ohjeet),
            'synced_at' => current_time('mysql'),
        ]);
    }

    /**
     * Hae yksittäinen ohje
     */
    public static function get_ohje(\WP_REST_Request $request) {
        $site_id = $request->get_param('_sivusto_id');
        $ohje_id = (int) $request->get_param('id');

        if (!$site_id) {
            return new \WP_Error('not_authenticated', __('Autentikointi epäonnistui', 'sivustamo-master'), ['status' => 401]);
        }

        // Tarkista onko ohje sallittu tälle sivustolle
        if (!self::is_ohje_allowed($ohje_id, $site_id)) {
            return new \WP_Error('forbidden', __('Tämä ohje ei ole saatavilla', 'sivustamo-master'), ['status' => 403]);
        }

        $post = get_post($ohje_id);

        if (!$post || $post->post_type !== 'sivustamo_ohje' || $post->post_status !== 'publish') {
            return new \WP_Error('not_found', __('Ohjetta ei löytynyt', 'sivustamo-master'), ['status' => 404]);
        }

        return rest_ensure_response([
            'ohje' => self::format_ohje($post, true),
        ]);
    }

    /**
     * Hae kategoriat
     */
    public static function get_kategoriat(\WP_REST_Request $request) {
        $site_id = $request->get_param('_sivusto_id');

        if (!$site_id) {
            return new \WP_Error('not_authenticated', __('Autentikointi epäonnistui', 'sivustamo-master'), ['status' => 401]);
        }

        $kategoriat = self::get_allowed_kategoriat($site_id);

        return rest_ensure_response([
            'kategoriat' => $kategoriat,
        ]);
    }

    /**
     * Hae media
     */
    public static function get_media(\WP_REST_Request $request) {
        $site_id = $request->get_param('_sivusto_id');
        $media_id = (int) $request->get_param('id');
        $size = $request->get_param('size') ?: 'full';

        if (!$site_id) {
            return new \WP_Error('not_authenticated', __('Autentikointi epäonnistui', 'sivustamo-master'), ['status' => 401]);
        }

        // Lähetä tiedosto
        Media_Handler::serve_media($media_id, $size);
    }

    /**
     * Muotoile ohje API-vastaukseen
     */
    private static function format_ohje($post, $include_content = true) {
        $terms = get_the_terms($post->ID, 'sivustamo_kategoria');
        $kategoriat = [];

        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $kategoriat[] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'parent' => $term->parent,
                ];
            }
        }

        $ohje = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'excerpt' => $post->post_excerpt,
            'kategoriat' => $kategoriat,
            'roles' => get_post_meta($post->ID, '_ohje_roles', true) ?: ['administrator', 'editor', 'shop_manager'],
            'priority' => (int) get_post_meta($post->ID, '_ohje_priority', true),
            'version' => (int) get_post_meta($post->ID, '_ohje_version', true) ?: 1,
            'icon' => get_post_meta($post->ID, '_ohje_icon', true) ?: 'dashicons-book',
            'modified' => $post->post_modified_gmt,
            'modified_timestamp' => strtotime($post->post_modified_gmt),
        ];

        // Featured image
        $thumbnail_id = get_post_thumbnail_id($post->ID);
        if ($thumbnail_id) {
            $ohje['featured_image'] = [
                'id' => $thumbnail_id,
                'url' => rest_url('sivustamo/v1/media/' . $thumbnail_id),
                'sizes' => [
                    'thumbnail' => rest_url('sivustamo/v1/media/' . $thumbnail_id . '?size=thumbnail'),
                    'medium' => rest_url('sivustamo/v1/media/' . $thumbnail_id . '?size=medium'),
                    'large' => rest_url('sivustamo/v1/media/' . $thumbnail_id . '?size=large'),
                    'full' => rest_url('sivustamo/v1/media/' . $thumbnail_id . '?size=full'),
                ],
            ];
        }

        // Sisältö vain tarvittaessa
        if ($include_content) {
            // Korvaa median URL:t API-urleilla
            $content = $post->post_content;
            $content = Media_Handler::replace_media_urls_with_api($content);

            $ohje['content'] = $content;
            $ohje['content_rendered'] = apply_filters('the_content', $content);
            $ohje['media'] = Media_Handler::get_ohje_media($post->ID);
        }

        return $ohje;
    }

    /**
     * Tarkista onko ohje sallittu sivustolle
     */
    private static function is_ohje_allowed($ohje_id, $site_id) {
        $allowed_kategoriat = get_post_meta($site_id, '_sivusto_kategoriat', true) ?: [];
        $allowed_ohjeet = get_post_meta($site_id, '_sivusto_ohjeet', true) ?: [];

        // Jos ei rajoituksia, kaikki sallittu
        if (empty($allowed_kategoriat) && empty($allowed_ohjeet)) {
            return true;
        }

        // Tarkista yksittäiset ohjeet
        if (!empty($allowed_ohjeet) && in_array($ohje_id, $allowed_ohjeet)) {
            return true;
        }

        // Tarkista kategoriat
        if (!empty($allowed_kategoriat)) {
            $post_terms = wp_get_post_terms($ohje_id, 'sivustamo_kategoria', ['fields' => 'ids']);
            if (!is_wp_error($post_terms)) {
                foreach ($post_terms as $term_id) {
                    if (in_array($term_id, $allowed_kategoriat)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Hae sivuston sallitut kategoriat
     */
    private static function get_allowed_kategoriat($site_id) {
        $allowed_kategoriat = get_post_meta($site_id, '_sivusto_kategoriat', true) ?: [];
        $allowed_ohjeet = get_post_meta($site_id, '_sivusto_ohjeet', true) ?: [];

        // Jos sivustolle on määritelty yksittäisiä ohjeita, hae niiden kategoriat
        if (!empty($allowed_ohjeet)) {
            $kategoria_ids = [];
            foreach ($allowed_ohjeet as $ohje_id) {
                $terms = wp_get_post_terms($ohje_id, 'sivustamo_kategoria', ['fields' => 'ids']);
                if (!is_wp_error($terms)) {
                    $kategoria_ids = array_merge($kategoria_ids, $terms);
                }
            }
            // Yhdistä mahdollisesti erikseen valittuihin kategorioihin
            $allowed_kategoriat = array_unique(array_merge($allowed_kategoriat, $kategoria_ids));
        }

        // Jos ei ole mitään rajoituksia, palauta tyhjä lista
        // (ei palauteta kaikkia kategorioita oletuksena)
        if (empty($allowed_kategoriat) && empty($allowed_ohjeet)) {
            // Palauta kaikki vain jos mitään ei ole rajoitettu
            $args = [
                'taxonomy' => 'sivustamo_kategoria',
                'hide_empty' => true,
                'orderby' => 'meta_value_num',
                'meta_key' => '_kategoria_order',
                'order' => 'ASC',
            ];
        } else {
            // Suodata vain sallitut kategoriat
            $args = [
                'taxonomy' => 'sivustamo_kategoria',
                'hide_empty' => false,
                'orderby' => 'meta_value_num',
                'meta_key' => '_kategoria_order',
                'order' => 'ASC',
                'include' => $allowed_kategoriat,
            ];
        }

        $terms = get_terms($args);
        $kategoriat = [];

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $kategoriat[] = [
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                    'description' => $term->description,
                    'parent' => $term->parent,
                    'count' => $term->count,
                    'icon' => get_term_meta($term->term_id, '_kategoria_icon', true) ?: 'dashicons-category',
                    'order' => (int) get_term_meta($term->term_id, '_kategoria_order', true),
                    'roles' => get_term_meta($term->term_id, '_kategoria_roles', true) ?: ['administrator', 'editor', 'shop_manager'],
                ];
            }
        }

        return $kategoriat;
    }
}
