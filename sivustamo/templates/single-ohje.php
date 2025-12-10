<?php
/**
 * Template: Yksittäinen ohje
 */

use Sivustamo\Client\Helpers\Helpers;

get_header();

$ohje = get_query_var('sivustamo_ohje');
$kategoria = get_query_var('sivustamo_kategoria');
$page_title = get_query_var('sivustamo_page_title', $ohje->post_title);

// Hae naapuriohjeet
$adjacent_ohjeet = [];
if ($kategoria) {
    $all_ohjeet = get_posts([
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

    $current_index = null;
    foreach ($all_ohjeet as $index => $o) {
        if ($o->ID === $ohje->ID) {
            $current_index = $index;
            break;
        }
    }

    if ($current_index !== null) {
        if (isset($all_ohjeet[$current_index - 1])) {
            $adjacent_ohjeet['prev'] = $all_ohjeet[$current_index - 1];
        }
        if (isset($all_ohjeet[$current_index + 1])) {
            $adjacent_ohjeet['next'] = $all_ohjeet[$current_index + 1];
        }
    }
}

$master_id = get_post_meta($ohje->ID, '_ohje_master_id', true);
?>

<div class="sivustamo-container">
    <div class="sivustamo-header">
        <nav class="sivustamo-breadcrumb">
            <a href="<?php echo esc_url(Helpers::get_archive_url()); ?>"><?php _e('Ohjeet', 'sivustamo'); ?></a>
            <?php if ($kategoria) : ?>
                <span class="sivustamo-breadcrumb-separator">/</span>
                <a href="<?php echo esc_url(Helpers::get_kategoria_url($kategoria)); ?>"><?php echo esc_html($kategoria->name); ?></a>
            <?php endif; ?>
            <span class="sivustamo-breadcrumb-separator">/</span>
            <span><?php echo esc_html($ohje->post_title); ?></span>
        </nav>
    </div>

    <article class="sivustamo-ohje">
        <header class="sivustamo-ohje-header">
            <?php
            $icon = get_post_meta($ohje->ID, '_ohje_icon', true) ?: 'dashicons-book';
            ?>
            <span class="dashicons <?php echo esc_attr($icon); ?> sivustamo-ohje-icon-large"></span>
            <h1><?php echo esc_html($ohje->post_title); ?></h1>
        </header>

        <div class="sivustamo-ohje-content">
            <?php echo apply_filters('the_content', $ohje->post_content); ?>
        </div>

        <!-- Palaute -->
        <?php if ($master_id) : ?>
        <div class="sivustamo-feedback" data-ohje-id="<?php echo intval($master_id); ?>">
            <h4><?php _e('Oliko tämä ohje hyödyllinen?', 'sivustamo'); ?></h4>

            <div class="sivustamo-feedback-buttons">
                <button type="button" class="sivustamo-feedback-btn sivustamo-thumbs-up" data-thumbs="up">
                    <span>&#x1F44D;</span> <?php _e('Kyllä', 'sivustamo'); ?>
                </button>
                <button type="button" class="sivustamo-feedback-btn sivustamo-thumbs-down" data-thumbs="down">
                    <span>&#x1F44E;</span> <?php _e('Ei', 'sivustamo'); ?>
                </button>
            </div>

            <div class="sivustamo-feedback-extended" style="display: none;">
                <div class="sivustamo-feedback-stars">
                    <label><?php _e('Kuinka hyödyllinen?', 'sivustamo'); ?></label>
                    <div class="sivustamo-stars">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <button type="button" class="sivustamo-star" data-star="<?php echo $i; ?>">&#9733;</button>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="sivustamo-feedback-comment">
                    <label for="sivustamo-comment"><?php _e('Mitä voisimme parantaa?', 'sivustamo'); ?></label>
                    <textarea id="sivustamo-comment" rows="3" placeholder="<?php _e('Kirjoita kommenttisi tähän...', 'sivustamo'); ?>"></textarea>
                </div>

                <button type="button" class="sivustamo-feedback-submit">
                    <?php _e('Lähetä palaute', 'sivustamo'); ?>
                </button>
            </div>

            <div class="sivustamo-feedback-thanks" style="display: none;">
                <p><?php _e('Kiitos palautteesta!', 'sivustamo'); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Navigaatio -->
        <?php if (!empty($adjacent_ohjeet)) : ?>
        <nav class="sivustamo-ohje-nav">
            <?php if (isset($adjacent_ohjeet['prev'])) : ?>
                <a href="<?php echo esc_url(Helpers::get_ohje_url($adjacent_ohjeet['prev']->ID)); ?>" class="sivustamo-nav-prev">
                    <span class="sivustamo-nav-label"><?php _e('Edellinen', 'sivustamo'); ?></span>
                    <span class="sivustamo-nav-title"><?php echo esc_html($adjacent_ohjeet['prev']->post_title); ?></span>
                </a>
            <?php endif; ?>

            <?php if (isset($adjacent_ohjeet['next'])) : ?>
                <a href="<?php echo esc_url(Helpers::get_ohje_url($adjacent_ohjeet['next']->ID)); ?>" class="sivustamo-nav-next">
                    <span class="sivustamo-nav-label"><?php _e('Seuraava', 'sivustamo'); ?></span>
                    <span class="sivustamo-nav-title"><?php echo esc_html($adjacent_ohjeet['next']->post_title); ?></span>
                </a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </article>
</div>

<?php get_footer(); ?>
