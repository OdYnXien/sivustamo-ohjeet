<?php
/**
 * Template: Ohjeiden arkisto
 */

use Sivustamo\Client\Helpers\Helpers;

get_header();

$kategoriat = get_query_var('sivustamo_kategoriat', []);
$ohjeet = get_query_var('sivustamo_ohjeet', []);
$kategoria = get_query_var('sivustamo_kategoria', null);
$page_title = get_query_var('sivustamo_page_title', __('Ohjeet', 'sivustamo'));
?>

<div class="sivustamo-container">
    <div class="sivustamo-header">
        <h1><?php echo esc_html($page_title); ?></h1>

        <?php if ($kategoria) : ?>
            <nav class="sivustamo-breadcrumb">
                <a href="<?php echo esc_url(Helpers::get_archive_url()); ?>"><?php _e('Ohjeet', 'sivustamo'); ?></a>
                <span class="sivustamo-breadcrumb-separator">/</span>
                <span><?php echo esc_html($kategoria->name); ?></span>
            </nav>
        <?php endif; ?>
    </div>

    <?php if (!$kategoria && !empty($kategoriat)) : ?>
        <!-- Kategoriat -->
        <div class="sivustamo-kategoriat-grid">
            <?php foreach ($kategoriat as $kat) : ?>
                <?php
                $icon = get_term_meta($kat->term_id, '_kategoria_icon', true) ?: 'dashicons-category';
                ?>
                <a href="<?php echo esc_url(Helpers::get_kategoria_url($kat)); ?>" class="sivustamo-kategoria-card">
                    <div class="sivustamo-kategoria-icon">
                        <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                    </div>
                    <h2><?php echo esc_html($kat->name); ?></h2>
                    <?php if ($kat->description) : ?>
                        <p><?php echo esc_html($kat->description); ?></p>
                    <?php endif; ?>
                    <span class="sivustamo-ohje-count">
                        <?php printf(_n('%d ohje', '%d ohjetta', $kat->count, 'sivustamo'), $kat->count); ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($kategoria || !empty($ohjeet)) : ?>
        <!-- Ohjeet -->
        <?php
        if (empty($ohjeet) && $kategoria) {
            $ohjeet = get_posts([
                'post_type' => 'sivustamo_ohje',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'meta_value_num',
                'meta_key' => '_ohje_priority',
                'order' => 'ASC',
                'tax_query' => [
                    [
                        'taxonomy' => 'sivustamo_kategoria',
                        'field' => 'term_id',
                        'terms' => $kategoria->term_id,
                    ]
                ],
            ]);
        }
        ?>

        <?php if (!empty($ohjeet)) : ?>
            <div class="sivustamo-ohjeet-grid">
                <?php foreach ($ohjeet as $ohje) : ?>
                    <?php
                    $icon = get_post_meta($ohje->ID, '_ohje_icon', true) ?: 'dashicons-book';
                    ?>
                    <a href="<?php echo esc_url(Helpers::get_ohje_url($ohje->ID)); ?>" class="sivustamo-ohje-card">
                        <div class="sivustamo-ohje-icon">
                            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                        </div>
                        <h3><?php echo esc_html($ohje->post_title); ?></h3>
                        <?php if ($ohje->post_excerpt) : ?>
                            <p><?php echo esc_html(Helpers::truncate($ohje->post_excerpt, 100)); ?></p>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="sivustamo-no-content"><?php _e('Ohjeita ei löytynyt.', 'sivustamo'); ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (empty($kategoriat) && empty($ohjeet) && !$kategoria) : ?>
        <p class="sivustamo-no-content"><?php _e('Ohjeita ei ole vielä saatavilla.', 'sivustamo'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
