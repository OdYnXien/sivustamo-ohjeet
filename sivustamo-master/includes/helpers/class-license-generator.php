<?php
/**
 * Lisenssiavainten generointi
 */

namespace Sivustamo\Master\Helpers;

class License_Generator {

    /**
     * Generoi API-avain
     *
     * @return string
     */
    public static function generate_api_key() {
        // Muoto: SVM_XXXX-XXXX-XXXX-XXXX
        $parts = [];
        for ($i = 0; $i < 4; $i++) {
            $parts[] = strtoupper(bin2hex(random_bytes(2)));
        }

        return 'SVM_' . implode('-', $parts);
    }

    /**
     * Generoi salaisuus (secret)
     *
     * @return string
     */
    public static function generate_secret() {
        // 64 merkkiä hex-muodossa
        return bin2hex(random_bytes(32));
    }

    /**
     * Validoi API-avain muoto
     *
     * @param string $key
     * @return bool
     */
    public static function validate_api_key_format($key) {
        return (bool) preg_match('/^SVM_[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/', $key);
    }

    /**
     * Hae sivusto API-avaimen perusteella
     *
     * @param string $api_key
     * @return int|null Post ID tai null
     */
    public static function get_site_by_api_key($api_key) {
        global $wpdb;

        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_sivusto_api_key'
             AND meta_value = %s
             LIMIT 1",
            $api_key
        ));

        return $post_id ? (int) $post_id : null;
    }

    /**
     * Tarkista onko sivusto aktiivinen
     *
     * @param int $site_id
     * @return bool
     */
    public static function is_site_active($site_id) {
        $active = get_post_meta($site_id, '_sivusto_active', true);
        return $active === '1';
    }

    /**
     * Hae sivuston salaisuus
     *
     * @param int $site_id
     * @return string|null
     */
    public static function get_site_secret($site_id) {
        return get_post_meta($site_id, '_sivusto_secret', true) ?: null;
    }

    /**
     * Hae sivuston domain
     *
     * @param int $site_id
     * @return string|null
     */
    public static function get_site_domain($site_id) {
        return get_post_meta($site_id, '_sivusto_domain', true) ?: null;
    }

    /**
     * Validoi pyyntö (API-avain + signature + domain)
     *
     * @param string $api_key
     * @param string $signature
     * @param string $body
     * @param string $timestamp
     * @param string $request_domain Pyynnön lähettäjän domain (optional)
     * @return array ['valid' => bool, 'site_id' => int|null, 'error' => string|null]
     */
    public static function validate_request($api_key, $signature, $body, $timestamp, $request_domain = null) {
        // Tarkista API-avaimen muoto
        if (!self::validate_api_key_format($api_key)) {
            return [
                'valid' => false,
                'site_id' => null,
                'error' => 'Invalid API key format'
            ];
        }

        // Hae sivusto
        $site_id = self::get_site_by_api_key($api_key);
        if (!$site_id) {
            return [
                'valid' => false,
                'site_id' => null,
                'error' => 'API key not found'
            ];
        }

        // Tarkista onko aktiivinen
        if (!self::is_site_active($site_id)) {
            return [
                'valid' => false,
                'site_id' => $site_id,
                'error' => 'Site is not active'
            ];
        }

        // Tarkista domain jos annettu
        if ($request_domain) {
            $registered_domain = self::get_site_domain($site_id);
            if ($registered_domain) {
                // Normalisoi domainit vertailua varten
                $request_domain = strtolower(preg_replace('#^www\.#', '', $request_domain));
                $registered_domain = strtolower(preg_replace('#^www\.#', '', $registered_domain));

                if ($request_domain !== $registered_domain) {
                    return [
                        'valid' => false,
                        'site_id' => $site_id,
                        'error' => 'Domain mismatch'
                    ];
                }
            }
        }

        // Tarkista timestamp (±5 minuuttia)
        $request_time = (int) $timestamp;
        $current_time = time();
        $time_diff = abs($current_time - $request_time);

        if ($time_diff > 300) { // 5 minuuttia
            return [
                'valid' => false,
                'site_id' => $site_id,
                'error' => 'Request timestamp expired'
            ];
        }

        // Tarkista signature
        $secret = self::get_site_secret($site_id);
        if (!$secret) {
            return [
                'valid' => false,
                'site_id' => $site_id,
                'error' => 'Site secret not found'
            ];
        }

        $expected_signature = hash_hmac('sha256', $body . $timestamp, $secret);

        if (!hash_equals($expected_signature, $signature)) {
            return [
                'valid' => false,
                'site_id' => $site_id,
                'error' => 'Invalid signature'
            ];
        }

        return [
            'valid' => true,
            'site_id' => $site_id,
            'error' => null
        ];
    }
}
