<?php
/**
 * REST API Controller
 */

namespace Sivustamo\Master\API;

use Sivustamo\Master\Helpers\License_Generator;

class REST_Controller {

    /**
     * Namespace
     */
    const NAMESPACE = 'sivustamo/v1';

    /**
     * Rekisteröi reitit
     */
    public function register_routes() {
        // Autentikointi-middleware
        add_filter('rest_pre_dispatch', [$this, 'authenticate_request'], 10, 3);

        // Verify endpoint
        register_rest_route(self::NAMESPACE, '/verify', [
            'methods' => 'GET',
            'callback' => [Auth_API::class, 'verify'],
            'permission_callback' => '__return_true',
        ]);

        // Ohjeet endpoints
        register_rest_route(self::NAMESPACE, '/ohjeet', [
            'methods' => 'GET',
            'callback' => [Ohjeet_API::class, 'get_ohjeet'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/ohje/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [Ohjeet_API::class, 'get_ohje'],
            'permission_callback' => '__return_true',
        ]);

        // Kategoriat endpoint
        register_rest_route(self::NAMESPACE, '/kategoriat', [
            'methods' => 'GET',
            'callback' => [Ohjeet_API::class, 'get_kategoriat'],
            'permission_callback' => '__return_true',
        ]);

        // Media endpoint
        register_rest_route(self::NAMESPACE, '/media/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [Ohjeet_API::class, 'get_media'],
            'permission_callback' => '__return_true',
        ]);

        // Stats endpoints
        register_rest_route(self::NAMESPACE, '/stats/view', [
            'methods' => 'POST',
            'callback' => [Stats_API::class, 'record_view'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/stats/feedback', [
            'methods' => 'POST',
            'callback' => [Stats_API::class, 'record_feedback'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Autentikoi pyyntö
     */
    public function authenticate_request($result, $server, $request) {
        // Ohita jos ei sivustamo-endpoint
        if (strpos($request->get_route(), '/sivustamo/v1') !== 0) {
            return $result;
        }

        // Tarkista onko API käytössä
        if (get_option('sivustamo_master_api_enabled', '1') !== '1') {
            return new \WP_Error(
                'api_disabled',
                __('API ei ole käytössä', 'sivustamo-master'),
                ['status' => 503]
            );
        }

        // Hae headerit
        $api_key = $request->get_header('X-Sivustamo-Key');
        $signature = $request->get_header('X-Sivustamo-Signature');
        $timestamp = $request->get_header('X-Sivustamo-Timestamp');

        // Jos ei avaimia, palauta virhe
        if (!$api_key) {
            return new \WP_Error(
                'missing_api_key',
                __('API-avain puuttuu', 'sivustamo-master'),
                ['status' => 401]
            );
        }

        // Validoi API-avain
        $site_id = License_Generator::get_site_by_api_key($api_key);

        if (!$site_id) {
            return new \WP_Error(
                'invalid_api_key',
                __('Virheellinen API-avain', 'sivustamo-master'),
                ['status' => 401]
            );
        }

        // Tarkista onko sivusto aktiivinen
        if (!License_Generator::is_site_active($site_id)) {
            return new \WP_Error(
                'site_inactive',
                __('Sivusto ei ole aktiivinen', 'sivustamo-master'),
                ['status' => 403]
            );
        }

        // Hae pyynnön domain (Origin-headerista tai Referer-headerista)
        $request_domain = null;
        $origin = $request->get_header('Origin');
        $referer = $request->get_header('Referer');

        if ($origin) {
            $parsed = parse_url($origin);
            $request_domain = isset($parsed['host']) ? $parsed['host'] : null;
        } elseif ($referer) {
            $parsed = parse_url($referer);
            $request_domain = isset($parsed['host']) ? $parsed['host'] : null;
        }

        // Tarkista domain jos rekisteröity sivustolle
        $registered_domain = License_Generator::get_site_domain($site_id);
        if ($registered_domain && $request_domain) {
            // Normalisoi domainit
            $request_domain_normalized = strtolower(preg_replace('#^www\.#', '', $request_domain));
            $registered_domain_normalized = strtolower(preg_replace('#^www\.#', '', $registered_domain));

            if ($request_domain_normalized !== $registered_domain_normalized) {
                return new \WP_Error(
                    'domain_mismatch',
                    __('Pyynnön domain ei täsmää rekisteröityyn domainiin', 'sivustamo-master'),
                    ['status' => 403]
                );
            }
        }

        // Jos allekirjoitus vaaditaan
        if (get_option('sivustamo_master_require_signature', '1') === '1') {
            if (!$signature || !$timestamp) {
                return new \WP_Error(
                    'missing_signature',
                    __('Allekirjoitus puuttuu', 'sivustamo-master'),
                    ['status' => 401]
                );
            }

            $body = $request->get_body();
            $validation = License_Generator::validate_request($api_key, $signature, $body, $timestamp, $request_domain);

            if (!$validation['valid']) {
                return new \WP_Error(
                    'invalid_signature',
                    $validation['error'],
                    ['status' => 401]
                );
            }
        }

        // Aseta sivusto-ID requestiin
        $request->set_param('_sivusto_id', $site_id);

        return $result;
    }
}
