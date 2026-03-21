<?php
/**
 * The main template file (fallback).
 * Used when no more specific template is found.
 *
 * @package KinoBase
 */

get_header();
?>
<main class="site-content" id="main" role="main">
    <div class="container">

        <?php if (have_posts()) : ?>

            <?php if (is_home() && !is_front_page()) : ?>
                <div class="catalog-heading">
                    <h1 class="catalog-title"><?php esc_html_e('Все записи', 'kinobase'); ?></h1>
                </div>
            <?php endif; ?>

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
                <?php
                the_posts_pagination([
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                ]);
                ?>
            </div>

        <?php else : ?>

            <div class="no-results">
                <h2><?php esc_html_e('Ничего не найдено', 'kinobase'); ?></h2>
                <p><?php esc_html_e('Попробуйте изменить запрос или вернитесь на главную.', 'kinobase'); ?></p>
            </div>

        <?php endif; ?>

    </div>
</main>
<?php get_footer(); ?>
