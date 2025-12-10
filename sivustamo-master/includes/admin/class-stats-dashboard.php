<?php
/**
 * Statistiikka-dashboard
 */

namespace Sivustamo\Master\Admin;

class Stats_Dashboard {

    /**
     * Renderöi statistiikkasivu
     */
    public static function render() {
        global $wpdb;

        $views_table = $wpdb->prefix . 'sivustamo_views';
        $feedback_table = $wpdb->prefix . 'sivustamo_feedback';

        // Aikaväli
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '30';
        $date_from = date('Y-m-d', strtotime("-{$period} days"));

        // Katselukerrat aikavälillä
        $views_by_day = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(viewed_at) as date, COUNT(*) as count
             FROM $views_table
             WHERE viewed_at >= %s
             GROUP BY DATE(viewed_at)
             ORDER BY date ASC",
            $date_from
        ));

        // Suosituimmat ohjeet
        $top_ohjeet = $wpdb->get_results($wpdb->prepare(
            "SELECT v.ohje_id, p.post_title, COUNT(*) as views
             FROM $views_table v
             LEFT JOIN {$wpdb->posts} p ON v.ohje_id = p.ID
             WHERE v.viewed_at >= %s
             GROUP BY v.ohje_id
             ORDER BY views DESC
             LIMIT 10",
            $date_from
        ));

        // Aktiivisimmat sivustot
        $top_sivustot = $wpdb->get_results($wpdb->prepare(
            "SELECT v.sivusto_id, p.post_title, COUNT(*) as views
             FROM $views_table v
             LEFT JOIN {$wpdb->posts} p ON v.sivusto_id = p.ID
             WHERE v.viewed_at >= %s
             GROUP BY v.sivusto_id
             ORDER BY views DESC
             LIMIT 10",
            $date_from
        ));

        // Palautteiden yhteenveto
        $feedback_summary = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN thumbs = 'up' THEN 1 ELSE 0 END) as thumbs_up,
                SUM(CASE WHEN thumbs = 'down' THEN 1 ELSE 0 END) as thumbs_down,
                AVG(stars) as avg_stars
             FROM $feedback_table
             WHERE created_at >= %s",
            $date_from
        ));

        // Viimeisimmät kommentit
        $recent_comments = $wpdb->get_results($wpdb->prepare(
            "SELECT f.*, p.post_title as ohje_title, s.post_title as sivusto_name
             FROM $feedback_table f
             LEFT JOIN {$wpdb->posts} p ON f.ohje_id = p.ID
             LEFT JOIN {$wpdb->posts} s ON f.sivusto_id = s.ID
             WHERE f.comment IS NOT NULL AND f.comment != ''
             AND f.created_at >= %s
             ORDER BY f.created_at DESC
             LIMIT 20",
            $date_from
        ));

        ?>
        <div class="wrap">
            <h1><?php _e('Statistiikka', 'sivustamo-master'); ?></h1>

            <!-- Aikavälin valinta -->
            <div class="sivustamo-period-filter">
                <form method="get">
                    <input type="hidden" name="page" value="sivustamo-stats">
                    <label for="period"><?php _e('Aikaväli:', 'sivustamo-master'); ?></label>
                    <select name="period" id="period" onchange="this.form.submit()">
                        <option value="7" <?php selected($period, '7'); ?>><?php _e('7 päivää', 'sivustamo-master'); ?></option>
                        <option value="30" <?php selected($period, '30'); ?>><?php _e('30 päivää', 'sivustamo-master'); ?></option>
                        <option value="90" <?php selected($period, '90'); ?>><?php _e('90 päivää', 'sivustamo-master'); ?></option>
                        <option value="365" <?php selected($period, '365'); ?>><?php _e('Vuosi', 'sivustamo-master'); ?></option>
                    </select>
                </form>
            </div>

            <div class="sivustamo-stats-dashboard">
                <!-- Yhteenveto -->
                <div class="sivustamo-card full-width">
                    <h2><?php _e('Palautteiden yhteenveto', 'sivustamo-master'); ?></h2>
                    <div class="sivustamo-feedback-summary">
                        <div class="sivustamo-feedback-stat">
                            <span class="number"><?php echo intval($feedback_summary->total ?? 0); ?></span>
                            <span class="label"><?php _e('Palautteita yhteensä', 'sivustamo-master'); ?></span>
                        </div>
                        <div class="sivustamo-feedback-stat positive">
                            <span class="number">&#x1F44D; <?php echo intval($feedback_summary->thumbs_up ?? 0); ?></span>
                            <span class="label"><?php _e('Positiivista', 'sivustamo-master'); ?></span>
                        </div>
                        <div class="sivustamo-feedback-stat negative">
                            <span class="number">&#x1F44E; <?php echo intval($feedback_summary->thumbs_down ?? 0); ?></span>
                            <span class="label"><?php _e('Negatiivista', 'sivustamo-master'); ?></span>
                        </div>
                        <div class="sivustamo-feedback-stat">
                            <span class="number"><?php echo $feedback_summary->avg_stars ? number_format($feedback_summary->avg_stars, 1) : '-'; ?>/5</span>
                            <span class="label"><?php _e('Keskiarvo', 'sivustamo-master'); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Katselukerrat päivittäin -->
                <div class="sivustamo-card">
                    <h2><?php _e('Katselukerrat päivittäin', 'sivustamo-master'); ?></h2>
                    <div class="sivustamo-chart-container">
                        <canvas id="viewsChart"></canvas>
                    </div>
                </div>

                <!-- Suosituimmat ohjeet -->
                <div class="sivustamo-card">
                    <h2><?php _e('Suosituimmat ohjeet', 'sivustamo-master'); ?></h2>
                    <?php if ($top_ohjeet) : ?>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Ohje', 'sivustamo-master'); ?></th>
                                    <th><?php _e('Katselut', 'sivustamo-master'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_ohjeet as $ohje) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo get_edit_post_link($ohje->ohje_id); ?>">
                                                <?php echo esc_html($ohje->post_title ?: __('(poistettu)', 'sivustamo-master')); ?>
                                            </a>
                                        </td>
                                        <td><?php echo intval($ohje->views); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php _e('Ei dataa.', 'sivustamo-master'); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Aktiivisimmat sivustot -->
                <div class="sivustamo-card">
                    <h2><?php _e('Aktiivisimmat sivustot', 'sivustamo-master'); ?></h2>
                    <?php if ($top_sivustot) : ?>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Sivusto', 'sivustamo-master'); ?></th>
                                    <th><?php _e('Katselut', 'sivustamo-master'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_sivustot as $sivusto) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo get_edit_post_link($sivusto->sivusto_id); ?>">
                                                <?php echo esc_html($sivusto->post_title ?: __('(poistettu)', 'sivustamo-master')); ?>
                                            </a>
                                        </td>
                                        <td><?php echo intval($sivusto->views); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php _e('Ei dataa.', 'sivustamo-master'); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Viimeisimmät kommentit -->
                <div class="sivustamo-card full-width">
                    <h2><?php _e('Viimeisimmät kommentit', 'sivustamo-master'); ?></h2>
                    <?php if ($recent_comments) : ?>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Ohje', 'sivustamo-master'); ?></th>
                                    <th><?php _e('Sivusto', 'sivustamo-master'); ?></th>
                                    <th><?php _e('Arvio', 'sivustamo-master'); ?></th>
                                    <th><?php _e('Kommentti', 'sivustamo-master'); ?></th>
                                    <th><?php _e('Aika', 'sivustamo-master'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_comments as $comment) : ?>
                                    <tr>
                                        <td><?php echo esc_html($comment->ohje_title ?: '-'); ?></td>
                                        <td><?php echo esc_html($comment->sivusto_name ?: '-'); ?></td>
                                        <td>
                                            <?php echo $comment->thumbs === 'up' ? '&#x1F44D;' : '&#x1F44E;'; ?>
                                            <?php if ($comment->stars) : ?>
                                                (<?php echo intval($comment->stars); ?>/5)
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($comment->comment); ?></td>
                                        <td><?php echo esc_html(human_time_diff(strtotime($comment->created_at), current_time('timestamp')) . ' ' . __('sitten', 'sivustamo-master')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php _e('Ei kommentteja.', 'sivustamo-master'); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
            .sivustamo-period-filter {
                margin: 20px 0;
            }
            .sivustamo-stats-dashboard {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
                margin-top: 20px;
            }
            .sivustamo-card.full-width {
                grid-column: 1 / -1;
            }
            .sivustamo-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
            }
            .sivustamo-card h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .sivustamo-feedback-summary {
                display: flex;
                gap: 30px;
                justify-content: center;
                padding: 20px 0;
            }
            .sivustamo-feedback-stat {
                text-align: center;
            }
            .sivustamo-feedback-stat .number {
                display: block;
                font-size: 28px;
                font-weight: bold;
            }
            .sivustamo-feedback-stat.positive .number {
                color: green;
            }
            .sivustamo-feedback-stat.negative .number {
                color: #d63638;
            }
            .sivustamo-feedback-stat .label {
                color: #666;
            }
            .sivustamo-chart-container {
                height: 300px;
            }
        </style>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var ctx = document.getElementById('viewsChart');
                if (ctx) {
                    new Chart(ctx.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode(array_map(function($item) {
                                return date_i18n('j.n.', strtotime($item->date));
                            }, $views_by_day)); ?>,
                            datasets: [{
                                label: '<?php _e('Katselukerrat', 'sivustamo-master'); ?>',
                                data: <?php echo json_encode(array_map(function($item) {
                                    return intval($item->count);
                                }, $views_by_day)); ?>,
                                borderColor: '#2271b1',
                                backgroundColor: 'rgba(34, 113, 177, 0.1)',
                                tension: 0.3,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                }
            });
        </script>
        <?php
    }
}
