<?php
/**
 * Search Results Template
 *
 * @package KinoBase
 */

get_header();
?>
<main class="site-content" id="main" role="main">
    <div class="container">

        <div class="catalog-heading">
            <h1 class="catalog-title">
                <?php
                printf(
                    /* translators: %s: search query */
                    esc_html__('Результаты поиска: «%s»', 'kinobase'),
                    '<em>' . esc_html(get_search_query()) . '</em>'
                );
                ?>
            </h1>
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
                <h2><?php esc_html_e('Ничего не найдено', 'kinobase'); ?></h2>
                <p><?php printf(
                    esc_html__('По запросу «%s» ничего не найдено. Попробуйте другие слова.', 'kinobase'),
                    '<strong>' . esc_html(get_search_query()) . '</strong>'
                ); ?></p>
            </div>

        <?php endif; ?>

    </div>
</main>
<?php get_footer(); ?>
