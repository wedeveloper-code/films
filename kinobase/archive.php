<?php
/**
 * Archive / Category Template
 *
 * @package KinoBase
 */

get_header();
?>
<main class="site-content" id="main" role="main">
    <div class="container">

        <div class="catalog-heading">
            <h1 class="catalog-title">
                <?php the_archive_title(); ?>
                <?php
                $cat = get_queried_object();
                if ($cat instanceof WP_Term) {
                    $count = $cat->count;
                    echo '<span class="catalog-count">— <strong>' . number_format($count) . '</strong> '
                        . esc_html(_n('фильм', 'фильмов', $count, 'kinobase')) . '</span>';
                }
                ?>
            </h1>
            <?php
            $desc = get_the_archive_description();
            if ($desc) {
                echo '<p style="color:var(--text-muted);margin-top:0.5rem;">' . wp_kses_post($desc) . '</p>';
            }
            ?>
        </div>

        <?php if (have_posts()) : ?>
            <div class="movie-grid">
                <?php
                $i = 0;
                while (have_posts()) :
                    the_post();
                    get_template_part('template-parts/movie-card', null, [
                        'is_first_block' => true,
                        'card_index'     => $i++,
                    ]);
                endwhile;
                ?>
            </div>

            <div class="pagination">
                <?php the_posts_pagination(['prev_text' => '&laquo;', 'next_text' => '&raquo;']); ?>
            </div>

        <?php else : ?>
            <div class="no-results">
                <h2><?php esc_html_e('Фильмов не найдено', 'kinobase'); ?></h2>
                <p><?php esc_html_e('В этой категории пока нет фильмов.', 'kinobase'); ?></p>
            </div>
        <?php endif; ?>

    </div>
</main>
<?php get_footer(); ?>
