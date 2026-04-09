<?php
/**
 * Archive / Category Template
 *
 * @package KinoBase
 */

get_header();

$queried = get_queried_object();
$is_cat  = ($queried instanceof WP_Term && $queried->taxonomy === 'category');

// Collect subcategory filter terms
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
        // Current category has children → show "All" + children
        $filter_terms  = $children;
        $filter_parent = $queried;
        $show_all_link = true;
    } elseif ($queried->parent) {
        // No children but has a parent → show siblings
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
            <h1 class="catalog-title">
                <?php the_archive_title(); ?>
                <?php if ($is_cat) :
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
