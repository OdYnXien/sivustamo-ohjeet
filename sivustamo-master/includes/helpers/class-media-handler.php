<?php
/**
 * Median käsittely
 */

namespace Sivustamo\Master\Helpers;

class Media_Handler {

    /**
     * Hae ohjeen kaikki media-tiedostot
     *
     * @param int $ohje_id
     * @return array
     */
    public static function get_ohje_media($ohje_id) {
        $media = [];

        // Featured image
        $thumbnail_id = get_post_thumbnail_id($ohje_id);
        if ($thumbnail_id) {
            $media[] = self::get_attachment_data($thumbnail_id);
        }

        // Sisällön kuvat ja tiedostot
        $post = get_post($ohje_id);
        if ($post) {
            $content_media = self::extract_media_from_content($post->post_content);
            $media = array_merge($media, $content_media);
        }

        // Galleria-kuvat jos käytetään
        $gallery_ids = get_post_meta($ohje_id, '_ohje_gallery', true);
        if (is_array($gallery_ids)) {
            foreach ($gallery_ids as $attachment_id) {
                $media[] = self::get_attachment_data($attachment_id);
            }
        }

        // Poista duplikaatit ID:n perusteella
        $unique_media = [];
        $seen_ids = [];
        foreach ($media as $item) {
            if ($item && !in_array($item['id'], $seen_ids)) {
                $unique_media[] = $item;
                $seen_ids[] = $item['id'];
            }
        }

        return $unique_media;
    }

    /**
     * Hae attachment-tiedot
     *
     * @param int $attachment_id
     * @return array|null
     */
    public static function get_attachment_data($attachment_id) {
        $attachment = get_post($attachment_id);

        if (!$attachment || $attachment->post_type !== 'attachment') {
            return null;
        }

        $file_path = get_attached_file($attachment_id);
        $mime_type = get_post_mime_type($attachment_id);
        $url = wp_get_attachment_url($attachment_id);

        $data = [
            'id' => $attachment_id,
            'title' => $attachment->post_title,
            'filename' => basename($file_path),
            'mime_type' => $mime_type,
            'file_size' => file_exists($file_path) ? filesize($file_path) : 0,
            'url' => $url,
            'api_url' => rest_url('sivustamo/v1/media/' . $attachment_id),
        ];

        // Lisää kuvan koot jos kyseessä kuva
        if (strpos($mime_type, 'image/') === 0) {
            $sizes = [];
            foreach (['thumbnail', 'medium', 'large', 'full'] as $size) {
                $image = wp_get_attachment_image_src($attachment_id, $size);
                if ($image) {
                    $sizes[$size] = [
                        'url' => $image[0],
                        'width' => $image[1],
                        'height' => $image[2],
                        'api_url' => rest_url('sivustamo/v1/media/' . $attachment_id . '?size=' . $size),
                    ];
                }
            }
            $data['sizes'] = $sizes;
        }

        return $data;
    }

    /**
     * Pura media-tiedostot sisällöstä
     *
     * @param string $content
     * @return array
     */
    public static function extract_media_from_content($content) {
        $media = [];

        // Etsi kuvat img-tageista
        if (preg_match_all('/<img[^>]+wp-image-(\d+)[^>]*>/i', $content, $matches)) {
            foreach ($matches[1] as $attachment_id) {
                $data = self::get_attachment_data((int) $attachment_id);
                if ($data) {
                    $media[] = $data;
                }
            }
        }

        // Etsi linkit attachment-sivuille
        if (preg_match_all('/href=["\'][^"\']*attachment_id=(\d+)[^"\']*["\']/i', $content, $matches)) {
            foreach ($matches[1] as $attachment_id) {
                $data = self::get_attachment_data((int) $attachment_id);
                if ($data) {
                    $media[] = $data;
                }
            }
        }

        // Etsi Gutenberg-blokkien media
        if (preg_match_all('/"id":(\d+)/', $content, $matches)) {
            foreach ($matches[1] as $potential_id) {
                $potential_id = (int) $potential_id;
                // Tarkista onko attachment
                if (get_post_type($potential_id) === 'attachment') {
                    $data = self::get_attachment_data($potential_id);
                    if ($data) {
                        $media[] = $data;
                    }
                }
            }
        }

        // Etsi PDF-linkit
        if (preg_match_all('/href=["\']([^"\']+\.pdf)["\']/', $content, $matches)) {
            foreach ($matches[1] as $pdf_url) {
                $attachment_id = attachment_url_to_postid($pdf_url);
                if ($attachment_id) {
                    $data = self::get_attachment_data($attachment_id);
                    if ($data) {
                        $media[] = $data;
                    }
                }
            }
        }

        return $media;
    }

    /**
     * Korvaa sisällön media-URLit API-urleilla
     *
     * @param string $content
     * @param int $site_id Sivuston ID statistiikkaa varten
     * @return string
     */
    public static function replace_media_urls_with_api($content, $site_id = 0) {
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];

        // Korvaa upload-URLit API-urleilla
        $content = preg_replace_callback(
            '#' . preg_quote($base_url, '#') . '/([^"\'>\s]+)#',
            function ($matches) use ($site_id) {
                $file_path = $matches[1];
                $attachment_id = self::get_attachment_id_from_url($matches[0]);

                if ($attachment_id) {
                    $api_url = rest_url('sivustamo/v1/media/' . $attachment_id);
                    if ($site_id) {
                        $api_url = add_query_arg('site_id', $site_id, $api_url);
                    }
                    return $api_url;
                }

                return $matches[0];
            },
            $content
        );

        return $content;
    }

    /**
     * Hae attachment ID URLin perusteella
     *
     * @param string $url
     * @return int
     */
    public static function get_attachment_id_from_url($url) {
        global $wpdb;

        // Kokeile ensin WordPressin funktiota
        $attachment_id = attachment_url_to_postid($url);

        if ($attachment_id) {
            return $attachment_id;
        }

        // Kokeile hakea tiedostonimen perusteella
        $filename = basename(parse_url($url, PHP_URL_PATH));
        $filename = preg_replace('/-\d+x\d+\./', '.', $filename); // Poista kokokohtaiset suffiksit

        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_wp_attached_file'
             AND meta_value LIKE %s
             LIMIT 1",
            '%' . $wpdb->esc_like($filename)
        ));

        return $attachment_id ? (int) $attachment_id : 0;
    }

    /**
     * Lähetä mediatiedosto (proxy)
     *
     * @param int $attachment_id
     * @param string $size Kuvan koko (thumbnail, medium, large, full)
     */
    public static function serve_media($attachment_id, $size = 'full') {
        $attachment = get_post($attachment_id);

        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_die('Media not found', 'Not Found', ['response' => 404]);
        }

        $file_path = get_attached_file($attachment_id);
        $mime_type = get_post_mime_type($attachment_id);

        // Jos kyseessä kuva ja pyydetty koko, hae oikea tiedosto
        if (strpos($mime_type, 'image/') === 0 && $size !== 'full') {
            $image = image_get_intermediate_size($attachment_id, $size);
            if ($image) {
                $file_path = path_join(dirname(get_attached_file($attachment_id)), $image['file']);
            }
        }

        if (!file_exists($file_path)) {
            wp_die('File not found', 'Not Found', ['response' => 404]);
        }

        // Lähetä tiedosto
        header('Content-Type: ' . $mime_type);
        header('Content-Length: ' . filesize($file_path));
        header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
        header('Cache-Control: public, max-age=86400'); // 1 päivä

        readfile($file_path);
        exit;
    }
}
