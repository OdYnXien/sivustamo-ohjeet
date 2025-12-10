<?php
/**
 * Auth API
 */

namespace Sivustamo\Master\API;

class Auth_API {

    /**
     * Tarkista lisenssi
     */
    public static function verify(\WP_REST_Request $request) {
        $site_id = $request->get_param('_sivusto_id');

        if (!$site_id) {
            return new \WP_Error(
                'not_authenticated',
                __('Autentikointi epäonnistui', 'sivustamo-master'),
                ['status' => 401]
            );
        }

        $site = get_post($site_id);
        $domain = get_post_meta($site_id, '_sivusto_domain', true);
        $allowed_kategoriat = get_post_meta($site_id, '_sivusto_kategoriat', true) ?: [];
        $allowed_ohjeet = get_post_meta($site_id, '_sivusto_ohjeet', true) ?: [];

        // Hae kategorioiden tiedot
        $kategoriat = [];
        if (!empty($allowed_kategoriat)) {
            $terms = get_terms([
                'taxonomy' => 'sivustamo_kategoria',
                'include' => $allowed_kategoriat,
                'hide_empty' => false,
            ]);

            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $kategoriat[] = [
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    ];
                }
            }
        }

        // Laske sallittujen ohjeiden määrä
        $ohje_count = 0;
        if (!empty($allowed_kategoriat) || !empty($allowed_ohjeet)) {
            $args = [
                'post_type' => 'sivustamo_ohje',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
            ];

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

            $ohjeet = get_posts($args);
            $ohje_count = count($ohjeet);
        } else {
            // Kaikki ohjeet sallittu
            $ohje_count = wp_count_posts('sivustamo_ohje')->publish;
        }

        return rest_ensure_response([
            'valid' => true,
            'site_id' => $site_id,
            'site_name' => $site->post_title,
            'domain' => $domain,
            'kategoriat' => $kategoriat,
            'ohje_count' => $ohje_count,
            'all_access' => empty($allowed_kategoriat) && empty($allowed_ohjeet),
        ]);
    }
}
