<?php
/**
 * Synkronoinnin hallinta
 */

namespace Sivustamo\Client\Sync;

class Sync_Manager {

    /**
     * Suorita synkronointi
     */
    public static function sync() {
        $api = new API_Client();

        if (!$api->is_configured()) {
            return new \WP_Error('not_configured', __('Sivustamo ei ole konfiguroitu', 'sivustamo'));
        }

        // Hae viimeisin synkronointi
        $last_sync = get_option('sivustamo_last_sync', 0);

        // Hae ohjeet masterilta
        $response = $api->get_ohjeet($last_sync);

        if (is_wp_error($response)) {
            self::log('Sync failed: ' . $response->get_error_message());
            return $response;
        }

        $synced_count = 0;
        $updated_count = 0;
        $errors = [];

        // Synkronoi kategoriat ensin
        if (!empty($response['kategoriat'])) {
            self::sync_kategoriat($response['kategoriat']);
        }

        // Synkronoi ohjeet
        if (!empty($response['ohjeet'])) {
            foreach ($response['ohjeet'] as $ohje_data) {
                $result = self::sync_ohje($ohje_data);

                if (is_wp_error($result)) {
                    $errors[] = $result->get_error_message();
                } elseif ($result === 'created') {
                    $synced_count++;
                } elseif ($result === 'updated') {
                    $updated_count++;
                }
            }
        }

        // Päivitä viimeisin synkronointi
        update_option('sivustamo_last_sync', time());

        self::log(sprintf(
            'Sync completed: %d new, %d updated, %d errors',
            $synced_count,
            $updated_count,
            count($errors)
        ));

        return [
            'success' => true,
            'synced' => $synced_count,
            'updated' => $updated_count,
            'errors' => $errors,
        ];
    }

    /**
     * Synkronoi yksittäinen ohje
     */
    private static function sync_ohje($ohje_data) {
        $master_id = $ohje_data['id'];

        // Etsi olemassa oleva ohje
        $existing = self::get_ohje_by_master_id($master_id);

        if ($existing) {
            // Tarkista onko muokattu paikallisesti
            $local_modified = get_post_meta($existing->ID, '_ohje_local_modified', true);

            if ($local_modified === '1') {
                // Älä päivitä, mutta merkitse uusi versio saataville
                update_post_meta($existing->ID, '_ohje_update_available', $ohje_data['version']);
                return 'skipped';
            }

            // Päivitä ohje
            return self::update_ohje($existing->ID, $ohje_data);
        }

        // Luo uusi ohje
        return self::create_ohje($ohje_data);
    }

    /**
     * Luo uusi ohje
     */
    private static function create_ohje($ohje_data) {
        $post_data = [
            'post_type' => 'sivustamo_ohje',
            'post_status' => 'publish',
            'post_title' => $ohje_data['title'],
            'post_name' => $ohje_data['slug'],
            'post_content' => $ohje_data['content'] ?? '',
            'post_excerpt' => $ohje_data['excerpt'] ?? '',
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        // Tallenna meta
        update_post_meta($post_id, '_ohje_source', 'master');
        update_post_meta($post_id, '_ohje_master_id', $ohje_data['id']);
        update_post_meta($post_id, '_ohje_master_version', $ohje_data['version']);
        update_post_meta($post_id, '_ohje_local_modified', '0');
        update_post_meta($post_id, '_ohje_priority', $ohje_data['priority'] ?? 0);
        update_post_meta($post_id, '_ohje_icon', $ohje_data['icon'] ?? 'dashicons-book');
        update_post_meta($post_id, '_ohje_roles', $ohje_data['roles'] ?? ['administrator', 'editor', 'shop_manager']);

        // Liitä kategoriat
        if (!empty($ohje_data['kategoriat'])) {
            self::attach_kategoriat($post_id, $ohje_data['kategoriat']);
        }

        return 'created';
    }

    /**
     * Päivitä ohje
     */
    private static function update_ohje($post_id, $ohje_data) {
        $post_data = [
            'ID' => $post_id,
            'post_title' => $ohje_data['title'],
            'post_name' => $ohje_data['slug'],
            'post_content' => $ohje_data['content'] ?? '',
            'post_excerpt' => $ohje_data['excerpt'] ?? '',
        ];

        $result = wp_update_post($post_data, true);

        if (is_wp_error($result)) {
            return $result;
        }

        // Päivitä meta
        update_post_meta($post_id, '_ohje_master_version', $ohje_data['version']);
        update_post_meta($post_id, '_ohje_priority', $ohje_data['priority'] ?? 0);
        update_post_meta($post_id, '_ohje_icon', $ohje_data['icon'] ?? 'dashicons-book');
        update_post_meta($post_id, '_ohje_roles', $ohje_data['roles'] ?? ['administrator', 'editor', 'shop_manager']);
        delete_post_meta($post_id, '_ohje_update_available');

        // Päivitä kategoriat
        if (!empty($ohje_data['kategoriat'])) {
            self::attach_kategoriat($post_id, $ohje_data['kategoriat']);
        }

        return 'updated';
    }

    /**
     * Synkronoi kategoriat
     */
    private static function sync_kategoriat($kategoriat) {
        foreach ($kategoriat as $kategoria) {
            self::sync_kategoria($kategoria);
        }
    }

    /**
     * Synkronoi yksittäinen kategoria
     */
    private static function sync_kategoria($kategoria_data) {
        // Etsi olemassa oleva
        $existing = get_term_by('slug', $kategoria_data['slug'], 'sivustamo_kategoria');

        if ($existing) {
            // Päivitä
            wp_update_term($existing->term_id, 'sivustamo_kategoria', [
                'name' => $kategoria_data['name'],
                'description' => $kategoria_data['description'] ?? '',
            ]);

            $term_id = $existing->term_id;
        } else {
            // Luo uusi
            $result = wp_insert_term($kategoria_data['name'], 'sivustamo_kategoria', [
                'slug' => $kategoria_data['slug'],
                'description' => $kategoria_data['description'] ?? '',
            ]);

            if (is_wp_error($result)) {
                return $result;
            }

            $term_id = $result['term_id'];
        }

        // Tallenna meta
        update_term_meta($term_id, '_kategoria_master_id', $kategoria_data['id']);
        update_term_meta($term_id, '_kategoria_icon', $kategoria_data['icon'] ?? 'dashicons-category');
        update_term_meta($term_id, '_kategoria_order', $kategoria_data['order'] ?? 0);
        update_term_meta($term_id, '_kategoria_roles', $kategoria_data['roles'] ?? ['administrator', 'editor', 'shop_manager']);

        return $term_id;
    }

    /**
     * Liitä kategoriat ohjeeseen
     */
    private static function attach_kategoriat($post_id, $kategoriat) {
        $term_ids = [];

        foreach ($kategoriat as $kategoria) {
            $term = get_term_by('slug', $kategoria['slug'], 'sivustamo_kategoria');
            if ($term) {
                $term_ids[] = $term->term_id;
            }
        }

        if (!empty($term_ids)) {
            wp_set_object_terms($post_id, $term_ids, 'sivustamo_kategoria');
        }
    }

    /**
     * Hae ohje master-ID:n perusteella
     */
    private static function get_ohje_by_master_id($master_id) {
        $posts = get_posts([
            'post_type' => 'sivustamo_ohje',
            'post_status' => 'any',
            'meta_key' => '_ohje_master_id',
            'meta_value' => $master_id,
            'posts_per_page' => 1,
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Pakota synkronointi yksittäiselle ohjeelle
     */
    public static function force_sync_ohje($post_id) {
        $master_id = get_post_meta($post_id, '_ohje_master_id', true);

        if (!$master_id) {
            return new \WP_Error('not_synced', __('Tämä ohje ei ole synkronoitu', 'sivustamo'));
        }

        $api = new API_Client();
        $response = $api->get_ohje($master_id);

        if (is_wp_error($response)) {
            return $response;
        }

        // Nollaa local_modified ja päivitä
        update_post_meta($post_id, '_ohje_local_modified', '0');

        return self::update_ohje($post_id, $response['ohje']);
    }

    /**
     * Kirjaa loki
     */
    private static function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Sivustamo Sync] ' . $message);
        }
    }
}
