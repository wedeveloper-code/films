<?php
/**
 * Front Page Template
 *
 * Displays 4 category blocks (Фильмы, Сериалы, Телепередачи, Новинки).
 * Supports /{year}/ URL for year-filtered homepage via kb_home_year query var.
 *
 * @package KinoBase
 */

get_header();

// Year filter (set when URL is e.g. /2022/)
$kb_home_year = sanitize_text_field(get_query_var('kb_home_year'));
$year_term    = null;
if ($kb_home_year) {
    $t = get_term_by('slug', $kb_home_year, 'category');
    if ($t && !is_wp_error($t)) {
        $year_term = $t;
    }
}

// Year terms for filter panel
$year_parent = kinobase_get_year_parent();
$home_years  = [];
if ($year_parent) {
    $r          = get_terms(['taxonomy' => 'category', 'parent' => $year_parent->term_id,
                              'hide_empty' => true, 'orderby' => 'name', 'order' => 'DESC', 'number' => 20]);
    $home_years = !is_wp_error($r) ? $r : [];
}
?>
<main class="site-content" id="main" role="main">
    <div class="container">

        <!-- H1 + Filters -->
        <?php
        $has_filter_menu  = !empty(kinobase_get_filter_menu_data('kinobase_filters'));
        $home_active_url  = ''; // No active URL on homepage (filter links go to category archives)
        $home_reset_url   = $kb_home_year ? home_url('/') : '';
        ?>
        <div class="catalog-heading">
            <div class="catalog-heading-row">
                <div class="catalog-heading-left">
                    <h1 class="catalog-title">
                        <?php esc_html_e('Каталог видео', 'kinobase'); ?>
                        <?php if ($kb_home_year) : ?>
                        <span class="active-filter-tag">
                            <?php echo esc_html($year_term ? $year_term->name : $kb_home_year); ?>
                            <a href="<?php echo esc_url(home_url('/')); ?>" class="remove-filter">×</a>
                        </span>
                        <?php else : ?>
                        <span class="catalog-count">
                            <?php printf(
                                esc_html__('— всего в базе %s видео', 'kinobase'),
                                '<strong>' . number_format(kinobase_get_movie_count()) . '</strong>'
                            ); ?>
                        </span>
                        <?php endif; ?>
                    </h1>
                </div>

                <?php if ($has_filter_menu) :
                    kinobase_render_mobile_filter_panel('kinobase_filters', $home_active_url, $home_reset_url);
                endif; ?>
            </div><!-- .catalog-heading-row -->

            <?php if ($has_filter_menu) : ?>
            <!-- Desktop filter bar — managed at wp-admin/nav-menus.php → Меню фильтров -->
            <div class="filter-bar filter-desktop-only">
                <?php kinobase_render_desktop_filter_bar($home_active_url, $home_reset_url); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Category blocks -->
        <div class="content-blocks">
            <?php
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

                $cat_link = $kb_home_year
                    ? kinobase_filter_url($section['slug'], $kb_home_year)
                    : get_category_link($cat->term_id);

                // Build query args — add year tax_query when filtering
                $query_args = [
                    'post_type'               => 'post',
                    'post_status'             => 'publish',
                    'posts_per_page'          => 10,
                    'no_found_rows'           => true,
                    'orderby'                 => 'date',
                    'order'                   => 'DESC',
                    'update_post_term_cache'  => false,
                ];

                if ($year_term) {
                    $query_args['tax_query'] = [
                        'relation' => 'AND',
                        ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [(int) $cat->term_id]],
                        ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [(int) $year_term->term_id]],
                    ];
                } else {
                    $query_args['cat'] = $cat->term_id;
                }

                $query = new WP_Query($query_args);

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
