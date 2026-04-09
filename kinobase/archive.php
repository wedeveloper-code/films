<?php
/**
 * Archive / Category Template
 *
 * @package KinoBase
 */

get_header();

$queried = get_queried_object();
$is_cat  = ($queried instanceof WP_Term && $queried->taxonomy === 'category');
?>
<main class="site-content" id="main" role="main">
    <div class="container">

        <div class="catalog-heading">
            <h1 class="catalog-title">
                <?php the_archive_title(); ?>
                <?php if ($is_cat) :
                    $count = $queried->count; ?>
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
        // --- Subcategory / sibling filter ---
        if ($is_cat) {
            // If current category has children — show them as filter
            $children = get_terms([
                'taxonomy'   => 'category',
                'parent'     => $queried->term_id,
                'hide_empty' => true,
            ]);

            // If no children but has a parent — show siblings (parent's children)
            if (empty($children) && $queried->parent) {
                $parent   = get_term($queried->parent, 'category');
                $siblings = get_terms([
                    'taxonomy'   => 'category',
                    'parent'     => $queried->parent,
                    'hide_empty' => true,
                ]);
                if (!empty($siblings)) : ?>
                <nav class="archive-filter" aria-label="<?php esc_attr_e('Фильтр по подкатегориям', 'kinobase'); ?>">
                    <a href="<?php echo esc_url(get_category_link($parent)); ?>" class="filter-pill">
                        ← <?php echo esc_html($parent->name); ?>
                    </a>
                    <?php foreach ($siblings as $sib) : ?>
                    <a href="<?php echo esc_url(get_category_link($sib->term_id)); ?>"
                       class="filter-pill<?php echo $sib->term_id === $queried->term_id ? ' active' : ''; ?>">
                        <?php echo esc_html($sib->name); ?>
                        <span class="filter-pill-count"><?php echo (int) $sib->count; ?></span>
                    </a>
                    <?php endforeach; ?>
                </nav>
                <?php endif;
            } elseif (!empty($children)) : ?>
                <nav class="archive-filter" aria-label="<?php esc_attr_e('Фильтр по подкатегориям', 'kinobase'); ?>">
                    <a href="<?php echo esc_url(get_category_link($queried->term_id)); ?>"
                       class="filter-pill active">
                        <?php esc_html_e('Все', 'kinobase'); ?>
                    </a>
                    <?php foreach ($children as $child) : ?>
                    <a href="<?php echo esc_url(get_category_link($child->term_id)); ?>"
                       class="filter-pill">
                        <?php echo esc_html($child->name); ?>
                        <span class="filter-pill-count"><?php echo (int) $child->count; ?></span>
                    </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif;
        }
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
