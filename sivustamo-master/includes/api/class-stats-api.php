<?php
/**
 * Stats API
 */

namespace Sivustamo\Master\API;

class Stats_API {

    /**
     * Tallenna katselukerta
     */
    public static function record_view(\WP_REST_Request $request) {
        global $wpdb;

        $site_id = $request->get_param('_sivusto_id');

        if (!$site_id) {
            return new \WP_Error('not_authenticated', __('Autentikointi epäonnistui', 'sivustamo-master'), ['status' => 401]);
        }

        $ohje_id = (int) $request->get_param('ohje_id');
        $user_role = sanitize_text_field($request->get_param('user_role'));

        if (!$ohje_id) {
            return new \WP_Error('missing_ohje_id', __('Ohjeen ID puuttuu', 'sivustamo-master'), ['status' => 400]);
        }

        // Tarkista ohje olemassaolo
        $ohje = get_post($ohje_id);
        if (!$ohje || $ohje->post_type !== 'sivustamo_ohje') {
            return new \WP_Error('invalid_ohje', __('Virheellinen ohje', 'sivustamo-master'), ['status' => 400]);
        }

        // Tallenna katselukerta
        $table = $wpdb->prefix . 'sivustamo_views';

        $result = $wpdb->insert(
            $table,
            [
                'ohje_id' => $ohje_id,
                'sivusto_id' => $site_id,
                'user_role' => $user_role ?: null,
                'viewed_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s']
        );

        if ($result === false) {
            return new \WP_Error('db_error', __('Tietokantavirhe', 'sivustamo-master'), ['status' => 500]);
        }

        // Päivitä sivuston viimeisin synkronointi
        update_post_meta($site_id, '_sivusto_last_sync', current_time('mysql'));

        return rest_ensure_response([
            'success' => true,
            'view_id' => $wpdb->insert_id,
        ]);
    }

    /**
     * Tallenna palaute
     */
    public static function record_feedback(\WP_REST_Request $request) {
        global $wpdb;

        $site_id = $request->get_param('_sivusto_id');

        if (!$site_id) {
            return new \WP_Error('not_authenticated', __('Autentikointi epäonnistui', 'sivustamo-master'), ['status' => 401]);
        }

        $ohje_id = (int) $request->get_param('ohje_id');
        $thumbs = sanitize_text_field($request->get_param('thumbs'));
        $stars = (int) $request->get_param('stars');
        $comment = sanitize_textarea_field($request->get_param('comment'));

        if (!$ohje_id) {
            return new \WP_Error('missing_ohje_id', __('Ohjeen ID puuttuu', 'sivustamo-master'), ['status' => 400]);
        }

        if (!in_array($thumbs, ['up', 'down'])) {
            return new \WP_Error('invalid_thumbs', __('Virheellinen arvio', 'sivustamo-master'), ['status' => 400]);
        }

        // Tarkista ohje olemassaolo
        $ohje = get_post($ohje_id);
        if (!$ohje || $ohje->post_type !== 'sivustamo_ohje') {
            return new \WP_Error('invalid_ohje', __('Virheellinen ohje', 'sivustamo-master'), ['status' => 400]);
        }

        // Validoi tähdet
        if ($stars < 0 || $stars > 5) {
            $stars = null;
        }

        // Tallenna palaute
        $table = $wpdb->prefix . 'sivustamo_feedback';

        $result = $wpdb->insert(
            $table,
            [
                'ohje_id' => $ohje_id,
                'sivusto_id' => $site_id,
                'thumbs' => $thumbs,
                'stars' => $stars ?: null,
                'comment' => $comment ?: null,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%d', '%s', '%s']
        );

        if ($result === false) {
            return new \WP_Error('db_error', __('Tietokantavirhe', 'sivustamo-master'), ['status' => 500]);
        }

        return rest_ensure_response([
            'success' => true,
            'feedback_id' => $wpdb->insert_id,
            'message' => __('Kiitos palautteesta!', 'sivustamo-master'),
        ]);
    }
}
