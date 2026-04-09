<?php
/**
 * Front Page Template
 *
 * Displays 4 category blocks (Фильмы, Сериалы, Телепередачи, Новинки),
 * each showing 10 movie cards in a 5-column grid.
 *
 * @package KinoBase
 */

get_header();
?>
<main class="site-content" id="main" role="main">
    <div class="container">

        <!-- H1 + Movie Count -->
        <div class="catalog-heading">
            <h1 class="catalog-title">
                <?php esc_html_e('Каталог видео', 'kinobase'); ?>
                <span class="catalog-count">
                    <?php printf(
                        /* translators: %s: formatted number */
                        esc_html__('— всего в базе %s видео', 'kinobase'),
                        '<strong>' . number_format(kinobase_get_movie_count()) . '</strong>'
                    ); ?>
                </span>
            </h1>
        </div>

        <!-- Category blocks -->
        <div class="content-blocks">
            <?php
            // Each section: try English slug first, then Russian name as fallback
            $sections = [
                ['slug' => 'films',  'name' => 'Фильмы',       'label' => __('Фильмы', 'kinobase')],
                ['slug' => 'series', 'name' => 'Сериалы',      'label' => __('Сериалы', 'kinobase')],
                ['slug' => 'tv',     'name' => 'Телепередачи', 'label' => __('Телепередачи', 'kinobase')],
                ['slug' => 'new',    'name' => 'Новинки',       'label' => __('Новинки', 'kinobase')],
            ];

            $block_index = 0;

            foreach ($sections as $section) :
                $label = $section['label'];
                $cat   = get_category_by_slug($section['slug'])
                      ?: get_term_by('name', $section['name'], 'category');
                if (!$cat) continue;

                $cat_link = get_category_link($cat->term_id);

                $query = new WP_Query([
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'cat'            => $cat->term_id,
                    'posts_per_page' => 10,
                    'no_found_rows'  => true,   // Performance: skip COUNT(*)
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'update_post_term_cache' => false, // We don't need terms in the loop
                ]);

                if (!$query->have_posts()) continue;
                ?>
                <section class="category-section">
                    <div class="section-header">
                        <h2 class="section-title"><?php echo esc_html($label); ?></h2>
                        <a href="<?php echo esc_url($cat_link); ?>" class="section-link">
                            <?php esc_html_e('Смотреть все', 'kinobase'); ?> &rarr;
                        </a>
                    </div>

                    <div class="movie-grid">
                        <?php
                        $card_index = 0;
                        while ($query->have_posts()) :
                            $query->the_post();
                            get_template_part('template-parts/movie-card', null, [
                                'is_first_block' => $block_index === 0,
                                'card_index'     => $card_index,
                            ]);
                            $card_index++;
                        endwhile;
                        wp_reset_postdata();
                        ?>
                        <article class="movie-card card-goto">
                            <a href="<?php echo esc_url($cat_link); ?>" class="card-goto-link">
                                <div class="card-goto-inner">
                                    <div class="card-goto-arrow">›</div>
                                    <div class="card-goto-text">
                                        <?php printf(
                                            /* translators: %s: category name */
                                            esc_html__('Смотреть все %s', 'kinobase'),
                                            esc_html($label)
                                        ); ?>
                                    </div>
                                </div>
                            </a>
                        </article>
                    </div>
                </section>
                <?php
                $block_index++;
            endforeach;
            ?>
        </div>
        <!-- /Category blocks -->

    </div>
</main>
<?php get_footer(); ?>
