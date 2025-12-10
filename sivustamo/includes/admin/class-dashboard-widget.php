<?php
/**
 * Dashboard Widget
 */

namespace Sivustamo\Client\Admin;

use Sivustamo\Client\Helpers\Helpers;
use Sivustamo\Client\Frontend\Access_Control;

class Dashboard_Widget {

    /**
     * Rekisteröi widget
     */
    public static function register() {
        if (!current_user_can('view_sivustamo_ohjeet')) {
            return;
        }

        wp_add_dashboard_widget(
            'sivustamo_ohjeet_widget',
            __('Sivustamo Ohjeet', 'sivustamo'),
            [__CLASS__, 'render']
        );
    }

    /**
     * Renderöi widget
     */
    public static function render() {
        // Hae ohjeet
        $ohjeet = get_posts([
            'post_type' => 'sivustamo_ohje',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        // Suodata käyttöoikeuksien mukaan
        $ohjeet = Access_Control::filter_ohjeet($ohjeet);

        // Laske tilastot
        $total_ohjeet = wp_count_posts('sivustamo_ohje')->publish;
        $synced_ohjeet = self::count_synced_ohjeet();
        $local_ohjeet = $total_ohjeet - $synced_ohjeet;

        $last_sync = get_option('sivustamo_last_sync', 0);

        ?>
        <div class="sivustamo-dashboard-widget">
            <?php if (empty($ohjeet)) : ?>
                <p><?php _e('Ohjeita ei ole vielä saatavilla.', 'sivustamo'); ?></p>
            <?php else : ?>
                <ul class="sivustamo-ohjeet-list">
                    <?php foreach ($ohjeet as $ohje) : ?>
                        <li>
                            <a href="<?php echo esc_url(Helpers::get_ohje_url($ohje->ID)); ?>">
                                <?php
                                $icon = get_post_meta($ohje->ID, '_ohje_icon', true) ?: 'dashicons-book';
                                ?>
                                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                                <?php echo esc_html($ohje->post_title); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="sivustamo-widget-footer">
                <div class="sivustamo-widget-stats">
                    <span><?php printf(__('Ohjeita: %d', 'sivustamo'), $total_ohjeet); ?></span>
                    <?php if ($synced_ohjeet > 0) : ?>
                        <span class="sivustamo-synced">
                            <?php printf(__('Synkronoituja: %d', 'sivustamo'), $synced_ohjeet); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($local_ohjeet > 0) : ?>
                        <span class="sivustamo-local">
                            <?php printf(__('Paikallisia: %d', 'sivustamo'), $local_ohjeet); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="sivustamo-widget-sync">
                    <?php if ($last_sync) : ?>
                        <span class="sivustamo-sync-time">
                            <?php printf(__('Synkronoitu: %s sitten', 'sivustamo'), human_time_diff($last_sync, current_time('timestamp'))); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <p class="sivustamo-widget-link">
                    <a href="<?php echo esc_url(Helpers::get_archive_url()); ?>" class="button">
                        <?php _e('Näytä kaikki ohjeet', 'sivustamo'); ?>
                    </a>
                </p>
            </div>
        </div>

        <style>
            .sivustamo-dashboard-widget .sivustamo-ohjeet-list {
                margin: 0;
                padding: 0;
                list-style: none;
            }
            .sivustamo-dashboard-widget .sivustamo-ohjeet-list li {
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            .sivustamo-dashboard-widget .sivustamo-ohjeet-list li:last-child {
                border-bottom: none;
            }
            .sivustamo-dashboard-widget .sivustamo-ohjeet-list a {
                text-decoration: none;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .sivustamo-dashboard-widget .sivustamo-ohjeet-list .dashicons {
                color: #2271b1;
            }
            .sivustamo-widget-footer {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #eee;
            }
            .sivustamo-widget-stats {
                font-size: 12px;
                color: #666;
                margin-bottom: 10px;
            }
            .sivustamo-widget-stats span {
                margin-right: 15px;
            }
            .sivustamo-widget-sync {
                font-size: 12px;
                color: #999;
                margin-bottom: 10px;
            }
            .sivustamo-widget-link {
                margin: 0;
            }
        </style>
        <?php
    }

    /**
     * Laske synkronoidut ohjeet
     */
    private static function count_synced_ohjeet() {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_ohje_source' AND meta_value = 'master'"
        );
    }
}
