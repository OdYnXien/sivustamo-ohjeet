<?php
/**
 * Media Proxy - välittää median masterilta
 */

namespace Sivustamo\Client\Sync;

class Media_Proxy {

    /**
     * Alusta
     */
    public static function init() {
        // Ei tarvita tässä vaiheessa - media haetaan suoraan masterilta
    }

    /**
     * Korvaa median URLit sisällössä
     *
     * Tämä muuntaa master-sivuston median URLit toimimaan autentikaation kanssa
     */
    public static function process_content($content) {
        $api = new API_Client();

        if (!$api->is_configured()) {
            return $content;
        }

        $master_url = get_option('sivustamo_master_url', 'https://sivustamo.dev');
        $master_api_url = trailingslashit($master_url) . 'wp-json/sivustamo/v1/media/';

        // Etsi kaikki media-URLit sisällöstä
        $pattern = '#' . preg_quote($master_api_url, '#') . '(\d+)(?:\?[^"\'>\s]*)?#';

        $content = preg_replace_callback($pattern, function($matches) use ($api) {
            $media_id = $matches[1];

            // Hae koko query-parametrista jos on
            $size = 'full';
            if (preg_match('/size=([a-z]+)/', $matches[0], $size_match)) {
                $size = $size_match[1];
            }

            return $api->get_media_url($media_id, $size);
        }, $content);

        return $content;
    }
}
