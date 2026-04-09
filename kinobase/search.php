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

            <?php
            global $wp_query;
            $s_current = max(1, get_query_var('paged'));
            $s_max     = (int) $wp_query->max_num_pages;
            if ($s_max > 1) :
            ?>
            <nav class="mobile-page-list" aria-label="<?php esc_attr_e('Страницы', 'kinobase'); ?>">
                <?php if ($s_current > 1) : ?>
                <a href="<?php echo esc_url(get_pagenum_link($s_current - 1)); ?>" class="mobile-page-num">&laquo;</a>
                <?php endif; ?>
                <?php
                $links = paginate_links(['prev_text'=>'','next_text'=>'','type'=>'array','end_size'=>1,'mid_size'=>2,'current'=>$s_current,'total'=>$s_max]);
                if ($links) {
                    foreach ($links as $link) {
                        $link = preg_replace('/class="([^"]*page-numbers current[^"]*)"/', 'class="mobile-page-num current"', $link);
                        $link = preg_replace('/class="([^"]*page-numbers[^"]*)"/', 'class="mobile-page-num"', $link);
                        echo $link;
                    }
                }
                ?>
                <?php if ($s_current < $s_max) : ?>
                <a href="<?php echo esc_url(get_pagenum_link($s_current + 1)); ?>" class="mobile-page-num">&raquo;</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

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
