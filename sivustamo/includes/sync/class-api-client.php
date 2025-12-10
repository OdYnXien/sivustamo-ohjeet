<?php
/**
 * API Client - kommunikoi master-sivuston kanssa
 */

namespace Sivustamo\Client\Sync;

class API_Client {

    /**
     * API-osoite
     */
    private $api_url;

    /**
     * API-avain
     */
    private $api_key;

    /**
     * Salaisuus
     */
    private $secret;

    /**
     * Konstruktori
     */
    public function __construct() {
        $this->api_url = trailingslashit(get_option('sivustamo_master_url', 'https://sivustamo.dev')) . 'wp-json/sivustamo/v1/';
        $this->api_key = get_option('sivustamo_api_key', '');
        $this->secret = get_option('sivustamo_secret', '');
    }

    /**
     * Tee API-pyyntö
     */
    public function request($endpoint, $method = 'GET', $body = []) {
        if (empty($this->api_key) || empty($this->secret)) {
            return new \WP_Error('not_configured', __('API-avain tai salaisuus puuttuu', 'sivustamo'));
        }

        $url = $this->api_url . ltrim($endpoint, '/');
        $timestamp = time();
        $body_string = !empty($body) ? json_encode($body) : '';

        // Luo allekirjoitus
        $signature = hash_hmac('sha256', $body_string . $timestamp, $this->secret);

        $args = [
            'method' => $method,
            'timeout' => 30,
            'headers' => [
                'X-Sivustamo-Key' => $this->api_key,
                'X-Sivustamo-Signature' => $signature,
                'X-Sivustamo-Timestamp' => $timestamp,
                'Content-Type' => 'application/json',
            ],
        ];

        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = $body_string;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $error_message = isset($data['message']) ? $data['message'] : __('API-virhe', 'sivustamo');
            return new \WP_Error('api_error', $error_message, ['status' => $status_code]);
        }

        return $data;
    }

    /**
     * Tarkista yhteys
     */
    public function verify() {
        return $this->request('verify');
    }

    /**
     * Hae ohjeet
     */
    public function get_ohjeet($modified_after = null) {
        $endpoint = 'ohjeet';

        if ($modified_after) {
            $endpoint .= '?modified_after=' . intval($modified_after);
        }

        return $this->request($endpoint);
    }

    /**
     * Hae yksittäinen ohje
     */
    public function get_ohje($ohje_id) {
        return $this->request('ohje/' . intval($ohje_id));
    }

    /**
     * Hae kategoriat
     */
    public function get_kategoriat() {
        return $this->request('kategoriat');
    }

    /**
     * Lähetä katselukerta
     */
    public function record_view($ohje_id, $user_role = '') {
        return $this->request('stats/view', 'POST', [
            'ohje_id' => $ohje_id,
            'user_role' => $user_role,
        ]);
    }

    /**
     * Lähetä palaute
     */
    public function record_feedback($ohje_id, $thumbs, $stars = null, $comment = '') {
        return $this->request('stats/feedback', 'POST', [
            'ohje_id' => $ohje_id,
            'thumbs' => $thumbs,
            'stars' => $stars,
            'comment' => $comment,
        ]);
    }

    /**
     * Hae median URL
     */
    public function get_media_url($media_id, $size = 'full') {
        $timestamp = time();
        $signature = hash_hmac('sha256', '' . $timestamp, $this->secret);

        $url = $this->api_url . 'media/' . intval($media_id);
        $url = add_query_arg([
            'size' => $size,
        ], $url);

        // Lisää autentikointi query-parametreina (koska kyseessä on kuvan lataus)
        $url = add_query_arg([
            'key' => $this->api_key,
            'sig' => $signature,
            'ts' => $timestamp,
        ], $url);

        return $url;
    }

    /**
     * Tarkista onko konfiguroitu
     */
    public function is_configured() {
        return !empty($this->api_key) && !empty($this->secret);
    }
}
