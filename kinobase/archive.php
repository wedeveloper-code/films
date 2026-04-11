<?php
/**
 * Archive / Category Template
 *
 * @package KinoBase
 */

get_header();

$queried = get_queried_object();
$is_cat  = ($queried instanceof WP_Term && $queried->taxonomy === 'category');

// Active filter term (e.g. "2020" on /category/films/2020/)
$kb_filter_term = $is_cat ? kb_get_active_filter_term() : null;

// Active URL of the current category (for filter bar highlighting)
$active_url = $is_cat ? get_term_link($queried) : '';
$reset_url  = ''; // No reset link on regular archive pages

// Has the admin configured the filters nav menu?
$has_filter_menu = !empty(kinobase_get_filter_menu_data('kinobase_filters'));

// Subcategory filter pills (children/siblings of the current category)
$filter_terms  = [];
$filter_parent = null;
$show_all_link = false;

if ($is_cat) {
    $children = get_terms([
        'taxonomy'   => 'category',
        'parent'     => $queried->term_id,
        'hide_empty' => true,
    ]);

    if (!is_wp_error($children) && !empty($children)) {
        $filter_terms  = $children;
        $filter_parent = $queried;
        $show_all_link = true;
    } elseif ($queried->parent) {
        $parent   = get_term((int) $queried->parent, 'category');
        $siblings = get_terms([
            'taxonomy'   => 'category',
            'parent'     => (int) $queried->parent,
            'hide_empty' => true,
        ]);
        if (!is_wp_error($siblings) && !empty($siblings) && !is_wp_error($parent)) {
            $filter_terms  = $siblings;
            $filter_parent = $parent;
            $show_all_link = false;
        }
    }
}
?>
<main class="site-content" id="main" role="main">
    <div class="container">

        <div class="catalog-heading">
            <!-- Row: h1 + mobile-only Фильтры button -->
            <div class="catalog-heading-row">
                <div class="catalog-heading-left">
                    <h1 class="catalog-title">
                        <?php
                        if ($is_cat) {
                            echo esc_html(kb_cat_h1($queried, $kb_filter_term));
                        } else {
                            the_archive_title();
                        }
                        ?>
                        <?php if ($is_cat) :
                            // Count both 'post' and 'movie' types in this category
                            $count = (int) (new WP_Query([
                                'post_type'      => ['post', 'movie'],
                                'cat'            => $queried->term_id,
                                'posts_per_page' => 1,
                                'no_found_rows'  => false,
                                'fields'         => 'ids',
                            ]))->found_posts;
                        ?>
                        <span class="catalog-count">— <strong><?php echo number_format($count); ?></strong>
                        <?php echo esc_html(_n('фильм', 'фильмов', $count, 'kinobase')); ?></span>
                        <?php endif; ?>
                    </h1>
                    <?php
                    $desc = get_the_archive_description();
                    if ($desc) {
                        echo '<p style="color:var(--text-muted);margin-top:0.5rem;">' . wp_kses_post($desc) . '</p>';
                    }
                    ?>
                </div>

            </div><!-- .catalog-heading-row -->

            <?php if ($has_filter_menu) : ?>
            <!-- Filter bar — Год / Жанр / Качество -->
            <div class="filter-bar">
                <?php kinobase_render_desktop_filter_bar($active_url, $reset_url, $is_cat ? $queried : null); ?>
            </div><!-- .filter-bar -->
            <?php endif; ?>

        <?php if (!empty($filter_terms)) : ?>
        <nav class="archive-filter" aria-label="<?php esc_attr_e('Фильтр по подкатегориям', 'kinobase'); ?>">
            <?php if ($show_all_link) : ?>
            <a href="<?php echo esc_url(get_category_link($filter_parent->term_id)); ?>"
               class="filter-pill active">
                <?php esc_html_e('Все', 'kinobase'); ?>
            </a>
            <?php else : ?>
            <a href="<?php echo esc_url(get_category_link($filter_parent->term_id)); ?>"
               class="filter-pill">
                ← <?php echo esc_html($filter_parent->name); ?>
            </a>
            <?php endif; ?>

            <?php foreach ($filter_terms as $term) : ?>
            <a href="<?php echo esc_url(get_category_link($term->term_id)); ?>"
               class="filter-pill<?php echo (!$show_all_link && $term->term_id === $queried->term_id) ? ' active' : ''; ?>">
                <?php echo esc_html($term->name); ?>
                <span class="filter-pill-count"><?php echo (int) $term->count; ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
        </div><!-- /.catalog-heading -->

        <?php
        global $wp_query;
        $current_page = max(1, get_query_var('paged'));
        $max_pages    = (int) $wp_query->max_num_pages;
        $next_url     = $current_page < $max_pages ? get_pagenum_link($current_page + 1) : '';
        ?>

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
                <?php if ($next_url) : ?>
                <article class="movie-card card-goto">
                    <a href="<?php echo esc_url($next_url); ?>" class="card-goto-link">
                        <div class="card-goto-inner">
                            <div class="card-goto-arrow">›</div>
                            <div class="card-goto-text"><?php esc_html_e('Следующая страница', 'kinobase'); ?></div>
                        </div>
                    </a>
                </article>
                <?php endif; ?>
            </div>

            <div class="pagination">
                <?php the_posts_pagination(['prev_text' => '&laquo;', 'next_text' => '&raquo;']); ?>
            </div>

            <?php if ($max_pages > 1) : ?>
            <nav class="mobile-page-list" aria-label="<?php esc_attr_e('Страницы', 'kinobase'); ?>">
                <?php if ($current_page > 1) : ?>
                <a href="<?php echo esc_url(get_pagenum_link($current_page - 1)); ?>" class="mobile-page-num">&laquo;</a>
                <?php endif; ?>
                <?php
                // Show: first, nearby pages, last — with ellipsis
                $links = paginate_links([
                    'prev_text' => '',
                    'next_text' => '',
                    'type'      => 'array',
                    'end_size'  => 1,
                    'mid_size'  => 2,
                    'current'   => $current_page,
                    'total'     => $max_pages,
                ]);
                if ($links) {
                    foreach ($links as $link) {
                        // Convert WP's <a>/<span> to our mobile-page-num class
                        $link = preg_replace('/class="([^"]*page-numbers current[^"]*)"/', 'class="mobile-page-num current"', $link);
                        $link = preg_replace('/class="([^"]*page-numbers[^"]*)"/', 'class="mobile-page-num"', $link);
                        echo $link;
                    }
                }
                ?>
                <?php if ($current_page < $max_pages) : ?>
                <a href="<?php echo esc_url(get_pagenum_link($current_page + 1)); ?>" class="mobile-page-num">&raquo;</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

        <?php else : ?>
            <div class="no-results">
                <h2><?php esc_html_e('Фильмов не найдено', 'kinobase'); ?></h2>
                <p><?php esc_html_e('В этой категории пока нет фильмов.', 'kinobase'); ?></p>
            </div>
        <?php endif; ?>

    <?php
    // Bottom description (set per-category in Рубрики → [название] → «Текст внизу страницы»)
    if ($is_cat) {
        $bottom_desc = (string) get_term_meta($queried->term_id, 'kb_bottom_description', true);
        if ($bottom_desc) : ?>
    <div class="archive-bottom-desc">
        <div class="container">
            <?php echo wp_kses_post($bottom_desc); ?>
        </div>
    </div>
        <?php endif;
    }
    ?>

    </div>
</main>
<?php get_footer(); ?>
