<?php
/**
 * Template Name: Избранное
 * Template Post Type: page
 *
 * Auto-applied when page slug = "favorites".
 * Reads kb_favorites cookie (JSON array of post IDs set by main.js).
 *
 * @package KinoBase
 */

get_header();

// Parse favorites cookie (set by JS as encodeURIComponent(JSON))
$fav_ids = [];
if (!empty($_COOKIE['kb_favorites'])) {
    $raw     = sanitize_text_field(wp_unslash($_COOKIE['kb_favorites']));
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $fav_ids = array_values(array_filter(array_map('absint', $decoded)));
    }
}
?>
<main class="site-content" id="main" role="main">
    <div class="container">

        <div class="catalog-heading">
            <h1 class="catalog-title">
                <?php esc_html_e('Избранное', 'kinobase'); ?>
                <?php if (!empty($fav_ids)) : ?>
                <span class="catalog-count">
                    — <strong><?php echo count($fav_ids); ?></strong>
                    <?php echo esc_html(_n('фильм', 'фильмов', count($fav_ids), 'kinobase')); ?>
                </span>
                <?php endif; ?>
            </h1>
        </div>

        <?php if (empty($fav_ids)) : ?>

            <div class="favorites-empty">
                <div class="favorites-empty-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                    </svg>
                </div>
                <h2 class="favorites-empty-title"><?php esc_html_e('Список избранного пуст', 'kinobase'); ?></h2>
                <p class="favorites-empty-text">
                    <?php esc_html_e('Нажмите на сердечко ♥ на карточке фильма, чтобы добавить его сюда.', 'kinobase'); ?>
                </p>
                <a href="<?php echo esc_url(home_url('/')); ?>" class="favorites-back-btn">
                    <?php esc_html_e('Перейти в каталог', 'kinobase'); ?>
                </a>
            </div>

        <?php else :

            $query = new WP_Query([
                'post_type'              => ['post', 'movie'],
                'post_status'            => 'publish',
                'post__in'               => $fav_ids,
                'orderby'                => 'post__in',
                'posts_per_page'         => -1,
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
            ]);

            if ($query->have_posts()) : ?>
            <div class="movie-grid">
                <?php
                $i = 0;
                while ($query->have_posts()) :
                    $query->the_post();
                    get_template_part('template-parts/movie-card', null, [
                        'is_first_block' => true,
                        'card_index'     => $i++,
                    ]);
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
            <?php else : ?>
            <div class="no-results">
                <h2><?php esc_html_e('Фильмы не найдены', 'kinobase'); ?></h2>
                <p><?php esc_html_e('Некоторые фильмы из избранного были удалены с сайта.', 'kinobase'); ?></p>
            </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</main>
<?php get_footer(); ?>
