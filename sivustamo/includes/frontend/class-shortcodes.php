<?php
/**
 * Shortcodet
 */

namespace Sivustamo\Client\Frontend;

use Sivustamo\Client\Helpers\Helpers;

class Shortcodes {

    /**
     * Rekisteröi shortcodet
     */
    public static function register() {
        add_shortcode('sivustamo_ohjeet', [__CLASS__, 'ohjeet_shortcode']);
        add_shortcode('sivustamo_ohje', [__CLASS__, 'ohje_shortcode']);
        add_shortcode('sivustamo_kategoriat', [__CLASS__, 'kategoriat_shortcode']);
    }

    /**
     * [sivustamo_ohjeet] - Näytä ohjeet
     *
     * Attribuutit:
     * - kategoria: kategorian slug
     * - limit: montako näytetään (oletus: kaikki)
     */
    public static function ohjeet_shortcode($atts) {
        if (!Access_Control::check_access()) {
            return '<p>' . __('Kirjaudu sisään nähdäksesi ohjeet.', 'sivustamo') . '</p>';
        }

        $atts = shortcode_atts([
            'kategoria' => '',
            'limit' => -1,
        ], $atts);

        $args = [
            'post_type' => 'sivustamo_ohje',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'orderby' => 'meta_value_num',
            'meta_key' => '_ohje_priority',
            'order' => 'ASC',
        ];

        if (!empty($atts['kategoria'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'sivustamo_kategoria',
                    'field' => 'slug',
                    'terms' => $atts['kategoria'],
                ]
            ];
        }

        $ohjeet = get_posts($args);
        $ohjeet = Access_Control::filter_ohjeet($ohjeet);

        if (empty($ohjeet)) {
            return '<p>' . __('Ohjeita ei löytynyt.', 'sivustamo') . '</p>';
        }

        ob_start();
        ?>
        <div class="sivustamo-ohjeet-list">
            <?php foreach ($ohjeet as $ohje) : ?>
                <div class="sivustamo-ohje-card">
                    <h3>
                        <a href="<?php echo esc_url(Helpers::get_ohje_url($ohje->ID)); ?>">
                            <?php echo esc_html($ohje->post_title); ?>
                        </a>
                    </h3>
                    <?php if ($ohje->post_excerpt) : ?>
                        <p><?php echo esc_html($ohje->post_excerpt); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [sivustamo_ohje id="123"] - Näytä yksittäinen ohje
     */
    public static function ohje_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        if (!$atts['id']) {
            return '';
        }

        if (!Access_Control::can_view_ohje($atts['id'])) {
            return '<p>' . __('Sinulla ei ole oikeutta nähdä tätä ohjetta.', 'sivustamo') . '</p>';
        }

        $ohje = get_post($atts['id']);

        if (!$ohje || $ohje->post_type !== 'sivustamo_ohje') {
            return '<p>' . __('Ohjetta ei löytynyt.', 'sivustamo') . '</p>';
        }

        ob_start();
        ?>
        <div class="sivustamo-ohje-embed">
            <h2><?php echo esc_html($ohje->post_title); ?></h2>
            <div class="sivustamo-ohje-content">
                <?php echo apply_filters('the_content', $ohje->post_content); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [sivustamo_kategoriat] - Näytä kategoriat
     */
    public static function kategoriat_shortcode($atts) {
        if (!Access_Control::check_access()) {
            return '<p>' . __('Kirjaudu sisään nähdäksesi ohjeet.', 'sivustamo') . '</p>';
        }

        $kategoriat = get_terms([
            'taxonomy' => 'sivustamo_kategoria',
            'hide_empty' => true,
            'orderby' => 'meta_value_num',
            'meta_key' => '_kategoria_order',
            'order' => 'ASC',
        ]);

        $kategoriat = Access_Control::filter_kategoriat($kategoriat);

        if (empty($kategoriat)) {
            return '<p>' . __('Kategorioita ei löytynyt.', 'sivustamo') . '</p>';
        }

        ob_start();
        ?>
        <div class="sivustamo-kategoriat-list">
            <?php foreach ($kategoriat as $kategoria) : ?>
                <div class="sivustamo-kategoria-card">
                    <h3>
                        <a href="<?php echo esc_url(Helpers::get_kategoria_url($kategoria)); ?>">
                            <?php
                            $icon = get_term_meta($kategoria->term_id, '_kategoria_icon', true) ?: 'dashicons-category';
                            ?>
                            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                            <?php echo esc_html($kategoria->name); ?>
                        </a>
                    </h3>
                    <?php if ($kategoria->description) : ?>
                        <p><?php echo esc_html($kategoria->description); ?></p>
                    <?php endif; ?>
                    <span class="sivustamo-ohje-count">
                        <?php printf(_n('%d ohje', '%d ohjetta', $kategoria->count, 'sivustamo'), $kategoria->count); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
