<?php
/**
 * Archive / Category Template
 *
 * @package KinoBase
 */

get_header();

$queried = get_queried_object();
$is_cat  = ($queried instanceof WP_Term && $queried->taxonomy === 'category');

// Active URL-based filters (kb_year / kb_genre come from custom rewrite rules)
$kb_year  = sanitize_text_field(get_query_var('kb_year'));
$kb_genre = sanitize_text_field(get_query_var('kb_genre'));

// Are we on one of the 4 main category pages?
$main_slugs    = ['films', 'series', 'tv', 'new'];
$main_cat_slug = ($is_cat && in_array($queried->slug, $main_slugs, true)) ? $queried->slug : '';

// Fetch year / genre terms for the filter bar
$filter_years  = [];
$filter_genres = [];
if ($main_cat_slug) {
    $year_parent  = kinobase_get_year_parent();
    $genre_parent = kinobase_get_genre_parent();

    if ($year_parent) {
        $r            = get_terms(['taxonomy' => 'category', 'parent' => $year_parent->term_id,
                                    'hide_empty' => true, 'orderby' => 'name', 'order' => 'DESC']);
        $filter_years = !is_wp_error($r) ? $r : [];
    }
    if ($genre_parent) {
        $r             = get_terms(['taxonomy' => 'category', 'parent' => $genre_parent->term_id,
                                     'hide_empty' => true]);
        $filter_genres = !is_wp_error($r) ? $r : [];
    }
}

// Active genre label for heading badge
$genre_label = '';
if ($kb_genre) {
    $genre_term  = get_term_by('slug', $kb_genre, 'category');
    $genre_label = ($genre_term && !is_wp_error($genre_term)) ? $genre_term->name : $kb_genre;
}

// Subcategory filter pills (for non-main categories, e.g. Жанры children)
$filter_terms  = [];
$filter_parent = null;
$show_all_link = false;

if ($is_cat && !$main_cat_slug) {
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
            <div class="catalog-heading-left">
                <h1 class="catalog-title">
                    <?php the_archive_title(); ?>
                    <?php if ($kb_year) : ?>
                    <span class="active-filter-tag">
                        <?php echo esc_html($kb_year); ?>
                        <a href="<?php echo esc_url(kinobase_filter_url($main_cat_slug, '', $kb_genre)); ?>"
                           class="remove-filter">×</a>
                    </span>
                    <?php endif; ?>
                    <?php if ($kb_genre && $genre_label) : ?>
                    <span class="active-filter-tag">
                        <?php echo esc_html($genre_label); ?>
                        <a href="<?php echo esc_url(kinobase_filter_url($main_cat_slug, $kb_year, '')); ?>"
                           class="remove-filter">×</a>
                    </span>
                    <?php endif; ?>
                    <?php if ($is_cat && !$kb_year && !$kb_genre) :
                        $count = (int) $queried->count; ?>
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

            <?php
            $has_filters = $main_cat_slug && (!empty($filter_years) || !empty($filter_genres));
            $active_count = ($kb_year ? 1 : 0) + ($kb_genre ? 1 : 0);
            if ($has_filters) :
            ?>
            <div class="filter-panel-wrap">
                <button class="filter-panel-btn" id="js-filter-btn" aria-expanded="false" aria-controls="js-filter-panel">
                    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6 10a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm2 4a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                    <?php esc_html_e('Фильтры', 'kinobase'); ?>
                    <?php if ($active_count) : ?>
                    <span class="filter-panel-badge"><?php echo $active_count; ?></span>
                    <?php endif; ?>
                </button>

                <div class="filter-panel" id="js-filter-panel" role="dialog" aria-label="<?php esc_attr_e('Фильтры', 'kinobase'); ?>">

                    <!-- Screen 1: filter categories -->
                    <div class="filter-screen active" id="filter-screen-main">
                        <div class="filter-screen-header">
                            <span class="filter-screen-title"><?php esc_html_e('Фильтры', 'kinobase'); ?></span>
                            <button class="filter-close-btn" aria-label="<?php esc_attr_e('Закрыть', 'kinobase'); ?>">×</button>
                        </div>
                        <ul class="filter-cat-list">
                            <?php if (!empty($filter_years)) : ?>
                            <li>
                                <button class="filter-cat-btn" data-target="filter-screen-year">
                                    <span><?php esc_html_e('Год', 'kinobase'); ?></span>
                                    <?php if ($kb_year) : ?>
                                    <span class="filter-selected-val"><?php echo esc_html($kb_year); ?></span>
                                    <?php else : ?>
                                    <span class="filter-cat-arrow">›</span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($filter_genres)) : ?>
                            <li>
                                <button class="filter-cat-btn" data-target="filter-screen-genre">
                                    <span><?php esc_html_e('Жанр', 'kinobase'); ?></span>
                                    <?php if ($genre_label) : ?>
                                    <span class="filter-selected-val"><?php echo esc_html($genre_label); ?></span>
                                    <?php else : ?>
                                    <span class="filter-cat-arrow">›</span>
                                    <?php endif; ?>
                                </button>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <?php if ($active_count) : ?>
                        <div class="filter-panel-footer">
                            <a href="<?php echo esc_url(get_category_link($queried->term_id)); ?>"
                               class="filter-reset-btn">
                                <?php esc_html_e('Сбросить', 'kinobase'); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($filter_years)) : ?>
                    <!-- Screen 2: year options -->
                    <div class="filter-screen" id="filter-screen-year">
                        <div class="filter-screen-header">
                            <button class="filter-back-btn" data-target="filter-screen-main">
                                ‹ <?php esc_html_e('Год', 'kinobase'); ?>
                            </button>
                            <button class="filter-close-btn" aria-label="<?php esc_attr_e('Закрыть', 'kinobase'); ?>">×</button>
                        </div>
                        <ul class="filter-sub-list">
                            <?php foreach ($filter_years as $term) : if (is_wp_error($term)) continue; ?>
                            <li>
                                <a href="<?php echo esc_url(kinobase_filter_url($main_cat_slug, $term->slug, $kb_genre)); ?>"
                                   class="filter-sub-link<?php echo ($kb_year === $term->slug) ? ' active' : ''; ?>">
                                    <?php echo esc_html($term->name); ?>
                                    <?php if ($kb_year === $term->slug) : ?>
                                    <span class="filter-sub-check">✓</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($filter_genres)) : ?>
                    <!-- Screen 3: genre options -->
                    <div class="filter-screen" id="filter-screen-genre">
                        <div class="filter-screen-header">
                            <button class="filter-back-btn" data-target="filter-screen-main">
                                ‹ <?php esc_html_e('Жанр', 'kinobase'); ?>
                            </button>
                            <button class="filter-close-btn" aria-label="<?php esc_attr_e('Закрыть', 'kinobase'); ?>">×</button>
                        </div>
                        <ul class="filter-sub-list">
                            <?php foreach ($filter_genres as $term) : if (is_wp_error($term)) continue; ?>
                            <li>
                                <a href="<?php echo esc_url(kinobase_filter_url($main_cat_slug, $kb_year, $term->slug)); ?>"
                                   class="filter-sub-link<?php echo ($kb_genre === $term->slug) ? ' active' : ''; ?>">
                                    <?php echo esc_html($term->name); ?>
                                    <?php if ($kb_genre === $term->slug) : ?>
                                    <span class="filter-sub-check">✓</span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                </div><!-- .filter-panel -->
            </div><!-- .filter-panel-wrap -->
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

    </div>
</main>
<?php get_footer(); ?>
