<?php
/**
 * FastWP — Custom Post Types & Taxonomies
 *
 * Registers:
 *  - movie      CPT (public, uses standard 'category' taxonomy)
 *  - actor      CPT (public, has archive at /actors/)
 *  - actor_attr taxonomy (hierarchical, on 'actor')
 *
 * Also provides filter-bar helper functions consumed by
 * archive.php and front-page.php.
 *
 * @package FastWP
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   1. Register CPTs + taxonomies
   ============================================================ */
add_action('init', 'fastwp_register_post_types', 5);
add_action('after_switch_theme', static function (): void {
    fastwp_register_post_types();
    flush_rewrite_rules();
});

function fastwp_register_post_types(): void
{
    /* ---------- MOVIE ----------------------------------------- */
    register_post_type('movie', [
        'labels' => [
            'name'               => 'Фильмы',
            'singular_name'      => 'Фильм',
            'menu_name'          => 'Фильмы',
            'add_new'            => 'Добавить фильм',
            'add_new_item'       => 'Новый фильм',
            'edit_item'          => 'Редактировать фильм',
            'new_item'           => 'Новый фильм',
            'view_item'          => 'Просмотреть фильм',
            'search_items'       => 'Найти фильм',
            'not_found'          => 'Фильмы не найдены',
            'not_found_in_trash' => 'Корзина пуста',
            'all_items'          => 'Все фильмы',
            'archives'           => 'Каталог фильмов',
        ],
        'public'          => true,
        'has_archive'     => false,
        'rewrite'         => ['slug' => (string) get_option('fastwp_movie_slug', 'film'), 'with_front' => false],
        'supports'        => ['title', 'editor', 'thumbnail', 'comments', 'excerpt'],
        'taxonomies'      => ['category', 'post_tag'],
        'show_in_rest'    => true,
        'show_in_menu'    => true,
        'menu_icon'       => 'dashicons-format-video',
        'menu_position'   => 5,
        'capability_type' => 'post',
    ]);

}

/* ============================================================
   2. Include 'category' archive queries for 'movie' CPT
   (so /category/films/ shows both post and movie post types)
   ============================================================ */
add_action('pre_get_posts', 'fastwp_movie_in_category_archives');

function fastwp_movie_in_category_archives(WP_Query $q): void
{
    if (is_admin() || !$q->is_main_query()) {
        return;
    }
    if ($q->is_category() || $q->is_tag() || $q->is_home() || $q->is_front_page()) {
        // array_filter removes '' that WP returns when post_type is not explicitly set
        $types = array_filter((array) $q->get('post_type'));
        if (empty($types) || $types === ['post']) {
            $q->set('post_type', ['post', 'movie']);
        }
    }
}

/* ============================================================
   3. Clean filter URLs: /category/{cat}/{filter}/
   Registers rewrite rules so filter slugs become path segments
   instead of query params — no ?kb_filter= in the source HTML.
   ============================================================ */

add_filter('query_vars', 'fastwp_filter_query_vars');

function fastwp_filter_query_vars(array $vars): array
{
    $vars[] = 'kb_filter_path'; // captures multi-segment filter path, e.g. "2020/action"
    return $vars;
}

add_action('init', 'fastwp_register_filter_rewrites', 6);

function fastwp_register_filter_rewrites(): void
{
    $base = trim((string) get_option('category_base'), '/') ?: 'category';

    // Pagination first: /category/{cat}/{filter_path}/page/{n}/
    // (.+) is greedy and backtracks to find the literal /page/ separator
    add_rewrite_rule(
        '^' . $base . '/([^/]+)/(.+)/page/([0-9]+)/?$',
        'index.php?category_name=$matches[1]&kb_filter_path=$matches[2]&paged=$matches[3]',
        'top'
    );
    // Base: /category/{cat}/{filter_path}/  (one or more filter segments)
    add_rewrite_rule(
        '^' . $base . '/([^/]+)/(.+?)/?$',
        'index.php?category_name=$matches[1]&kb_filter_path=$matches[2]',
        'top'
    );
}

// One-time flush after rules are registered (transient guard, runs once per month max)
add_action('init', 'fastwp_maybe_flush_filter_rewrites', 99);

function fastwp_maybe_flush_filter_rewrites(): void
{
    if (get_transient('kb_filter_rewrites_v2')) {
        return;
    }
    flush_rewrite_rules(false);
    set_transient('kb_filter_rewrites_v2', 1, MONTH_IN_SECONDS);
}

/* ============================================================
   4. Filter Bar Helpers
   Used by archive.php and front-page.php to render
   the Год / Жанр / … filter UI from the WP nav menu
   "fastwp_filters" (manageable at wp-admin/nav-menus.php).
   ============================================================ */

/**
 * Fetch all items for a registered nav menu location.
 * Returns [roots[], children[pid][]] or null when no menu assigned.
 *
 * @return array{roots: object[], children: array<int, object[]>}|null
 */
function fastwp_get_filter_menu_data(string $location): ?array
{
    static $cache = [];
    if (array_key_exists($location, $cache)) {
        return $cache[$location];
    }

    $all_locations = get_nav_menu_locations();

    // Resolve menu ID: try the requested location first, then the legacy KinoBase name
    $menu_id = (int) ($all_locations[$location] ?? 0);
    if (!$menu_id && $location === 'fastwp_filters') {
        $menu_id = (int) ($all_locations['kinobase_filters'] ?? 0);
    }
    if (!$menu_id) {
        return $cache[$location] = null;
    }

    $items = wp_get_nav_menu_items($menu_id);
    if (empty($items)) {
        return $cache[$location] = null;
    }

    $roots    = [];
    $children = [];
    foreach ($items as $item) {
        $pid = (int) $item->menu_item_parent;
        if ($pid === 0) {
            $roots[] = $item;
        } else {
            $children[$pid][] = $item;
        }
    }
    return $cache[$location] = ['roots' => $roots, 'children' => $children];
}

/**
 * Check if any child item in the menu matches $active_url.
 */
function fastwp_filter_menu_has_active(array $data, string $active_url): bool
{
    $active_url = rtrim($active_url, '/');
    foreach ($data['roots'] as $parent) {
        foreach ($data['children'][$parent->ID] ?? [] as $kid) {
            if (rtrim($kid->url, '/') === $active_url) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Get the taxonomy slug for a nav menu item.
 * Falls back to extracting the last path segment from the item URL.
 */
function fastwp_menu_item_slug(object $item): string
{
    if (!empty($item->object_id) && !empty($item->object)) {
        $term = get_term((int) $item->object_id, $item->object);
        if ($term && !is_wp_error($term)) {
            return $term->slug;
        }
    }
    return basename(rtrim($item->url, '/'));
}

/**
 * Render desktop filter bar from the "fastwp_filters" nav menu.
 * Each top-level item = dropdown button; children = filter links.
 *
 * In context mode (context_term set) each pill is a toggle link:
 *  - selecting a filter from a new group ADDS it to the URL
 *  - selecting a filter from an already-active group REPLACES the old selection
 *  - clicking an active filter REMOVES it
 * Filter slugs are ordered in the URL according to their menu position (left→right).
 * Example: active = 2020 + action → /category/films/2020/action/
 *
 * @param string       $active_url   URL of the currently active filter (non-context mode).
 * @param string       $reset_url    URL for the "× Сбросить" link (non-context mode).
 * @param WP_Term|null $context_term When set, generates toggle links relative to this category.
 */
function fastwp_render_desktop_filter_bar(
    string   $active_url   = '',
    string   $reset_url    = '',
    ?WP_Term $context_term = null
): void {
    $data = fastwp_get_filter_menu_data('fastwp_filters');
    if (!$data) {
        return;
    }

    $context_mode = ($context_term !== null);

    // Build ordered flat list of all filter items: [['slug'=>'2020','group_id'=>15], ...]
    // The ORDER here determines the order of slugs in the generated URL.
    $all_filter_items = [];
    foreach ($data['roots'] as $root) {
        foreach ($data['children'][$root->ID] ?? [] as $kid) {
            $slug = fastwp_menu_item_slug($kid);
            if ($slug !== '') {
                $all_filter_items[] = ['slug' => $slug, 'group_id' => (int) $root->ID];
            }
        }
    }

    // Parse currently active filter slugs from the URL query var
    $active_slugs = [];
    if ($context_mode) {
        $filter_path = sanitize_text_field((string) get_query_var('kb_filter_path', ''));
        if ($filter_path !== '') {
            $active_slugs = array_values(array_filter(
                array_map('sanitize_key', explode('/', trim($filter_path, '/')))
            ));
        }
    }

    // Helper closure: build toggle URL for one filter item.
    // - Adds the slug if not active (replacing any other slug from the same group).
    // - Removes the slug if already active.
    // - Reorders remaining active slugs by menu position.
    $build_toggle_url = static function (
        string $toggle_slug,
        int    $toggle_group_id
    ) use ($context_term, $active_slugs, $all_filter_items): string {
        $is_active = in_array($toggle_slug, $active_slugs, true);

        if ($is_active) {
            // Toggle OFF: remove this slug
            $new_slugs = array_values(
                array_filter($active_slugs, static fn($s) => $s !== $toggle_slug)
            );
        } else {
            // Collect all slugs that belong to the same group
            $same_group = array_column(
                array_filter($all_filter_items, static fn($i) => $i['group_id'] === $toggle_group_id),
                'slug'
            );
            // Remove any active slug from the same group, then add the new one
            $new_slugs   = array_values(
                array_filter($active_slugs, static fn($s) => !in_array($s, $same_group, true))
            );
            $new_slugs[] = $toggle_slug;
        }

        if (empty($new_slugs)) {
            return rtrim((string) get_term_link($context_term), '/') . '/';
        }

        // Reorder by menu position (iterate all_filter_items in order)
        $ordered = [];
        foreach ($all_filter_items as $item) {
            if (in_array($item['slug'], $new_slugs, true)) {
                $ordered[] = $item['slug'];
            }
        }

        return rtrim((string) get_term_link($context_term), '/') . '/' . implode('/', $ordered) . '/';
    };

    if ($context_mode) {
        $has_active = !empty($active_slugs);
    } else {
        $active_url = rtrim($active_url, '/');
        $has_active = fastwp_filter_menu_has_active($data, $active_url);
    }

    foreach ($data['roots'] as $parent) {
        $kids = $data['children'][$parent->ID] ?? [];
        if (empty($kids)) {
            continue;
        }

        $group_active = false;
        foreach ($kids as $kid) {
            if ($context_mode) {
                if (in_array(fastwp_menu_item_slug($kid), $active_slugs, true)) {
                    $group_active = true;
                    break;
                }
            } else {
                if (rtrim($kid->url, '/') === $active_url) {
                    $group_active = true;
                    break;
                }
            }
        }

        $uid = 'fb-' . (int) $parent->ID;
        ?>
        <div class="filter-bar-group">
            <button class="filter-bar-btn<?php echo $group_active ? ' is-active' : ''; ?>"
                    data-fb-toggle="<?php echo esc_attr($uid); ?>"
                    aria-expanded="false">
                <?php echo esc_html($parent->title); ?>
                <span class="filter-bar-chevron">▾</span>
            </button>
            <div class="filter-bar-drop" id="<?php echo esc_attr($uid); ?>">
                <ul>
                    <?php foreach ($kids as $kid) :
                        if ($context_mode) {
                            $kid_slug   = fastwp_menu_item_slug($kid);
                            $link_url   = $kid_slug !== ''
                                ? $build_toggle_url($kid_slug, (int) $parent->ID)
                                : $kid->url;
                            $is_current = in_array($kid_slug, $active_slugs, true);
                        } else {
                            $link_url   = $kid->url;
                            $is_current = (rtrim($kid->url, '/') === $active_url);
                        }
                    ?>
                    <li>
                        <a href="<?php echo esc_url($link_url); ?>"
                           class="<?php echo $is_current ? 'active' : ''; ?>">
                            <?php echo esc_html($kid->title); ?>
                            <?php if ($is_current) : ?><span>✓</span><?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }

    if ($context_mode) {
        if ($has_active) {
            echo '<a href="' . esc_url(get_term_link($context_term)) . '" class="filter-bar-reset">× '
                . esc_html__('Сбросить', 'fastwp') . '</a>';
        }
    } elseif ($has_active && $reset_url) {
        echo '<a href="' . esc_url($reset_url) . '" class="filter-bar-reset">× '
            . esc_html__('Сбросить', 'fastwp') . '</a>';
    }
}

/**
 * Render mobile sliding filter panel from the "fastwp_filters" nav menu.
 *
 * Supports the same multi-filter toggle logic as the desktop bar when context_term is set:
 * selecting a filter adds/replaces it in the URL, keeping other active filters.
 *
 * @param string       $active_url   URL of the currently active filter (non-context mode).
 * @param string       $reset_url    URL for the reset link inside the panel.
 * @param WP_Term|null $context_term When set, generates toggle links relative to this category.
 */
function fastwp_render_mobile_filter_panel(
    string   $active_url   = '',
    string   $reset_url    = '',
    ?WP_Term $context_term = null
): void {
    $data = fastwp_get_filter_menu_data('fastwp_filters');
    if (!$data) {
        return;
    }

    $context_mode = ($context_term !== null);

    // Build ordered flat list for URL construction (same logic as desktop bar)
    $all_filter_items = [];
    foreach ($data['roots'] as $root) {
        foreach ($data['children'][$root->ID] ?? [] as $kid) {
            $slug = fastwp_menu_item_slug($kid);
            if ($slug !== '') {
                $all_filter_items[] = ['slug' => $slug, 'group_id' => (int) $root->ID];
            }
        }
    }

    $active_slugs = [];
    if ($context_mode) {
        $filter_path = sanitize_text_field((string) get_query_var('kb_filter_path', ''));
        if ($filter_path !== '') {
            $active_slugs = array_values(array_filter(
                array_map('sanitize_key', explode('/', trim($filter_path, '/')))
            ));
        }
        $has_active = !empty($active_slugs);
        $reset_url  = $has_active ? rtrim((string) get_term_link($context_term), '/') . '/' : '';
    } else {
        $active_url = rtrim($active_url, '/');
        $has_active = fastwp_filter_menu_has_active($data, $active_url);
    }

    // Toggle URL builder (mirrors desktop bar logic)
    $build_toggle_url = static function (
        string $toggle_slug,
        int    $toggle_group_id
    ) use ($context_term, $active_slugs, $all_filter_items): string {
        $is_active = in_array($toggle_slug, $active_slugs, true);
        if ($is_active) {
            $new_slugs = array_values(
                array_filter($active_slugs, static fn($s) => $s !== $toggle_slug)
            );
        } else {
            $same_group = array_column(
                array_filter($all_filter_items, static fn($i) => $i['group_id'] === $toggle_group_id),
                'slug'
            );
            $new_slugs   = array_values(
                array_filter($active_slugs, static fn($s) => !in_array($s, $same_group, true))
            );
            $new_slugs[] = $toggle_slug;
        }
        if (empty($new_slugs)) {
            return rtrim((string) get_term_link($context_term), '/') . '/';
        }
        $ordered = [];
        foreach ($all_filter_items as $item) {
            if (in_array($item['slug'], $new_slugs, true)) {
                $ordered[] = $item['slug'];
            }
        }
        return rtrim((string) get_term_link($context_term), '/') . '/' . implode('/', $ordered) . '/';
    };
    ?>
    <div class="filter-panel-wrap filter-mobile-only">
        <button class="filter-panel-btn<?php echo $has_active ? ' active' : ''; ?>"
                id="js-filter-btn" aria-expanded="false" aria-controls="js-filter-panel">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6 10a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm2 4a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1z" clip-rule="evenodd"/>
            </svg>
            <?php esc_html_e('Фильтры', 'fastwp'); ?>
            <?php if ($has_active) : ?>
            <span class="filter-panel-badge"><?php echo count($active_slugs) ?: '!'; ?></span>
            <?php endif; ?>
        </button>

        <div class="filter-panel<?php echo $has_active ? ' open' : ''; ?>"
             id="js-filter-panel" role="dialog"
             aria-label="<?php esc_attr_e('Фильтры', 'fastwp'); ?>">

            <!-- Screen 1: list of filter groups -->
            <div class="filter-screen active" id="filter-screen-main">
                <div class="filter-screen-header">
                    <span class="filter-screen-title"><?php esc_html_e('Фильтры', 'fastwp'); ?></span>
                    <button class="filter-close-btn">×</button>
                </div>
                <ul class="filter-cat-list">
                    <?php foreach ($data['roots'] as $parent) :
                        $kids = $data['children'][$parent->ID] ?? [];
                        if (empty($kids)) continue;

                        $grp_active = false;
                        $grp_labels = [];
                        foreach ($kids as $kid) {
                            if ($context_mode) {
                                $s = fastwp_menu_item_slug($kid);
                                if (in_array($s, $active_slugs, true)) {
                                    $grp_active  = true;
                                    $grp_labels[] = $kid->title;
                                }
                            } else {
                                if (rtrim($kid->url, '/') === $active_url) {
                                    $grp_active  = true;
                                    $grp_labels[] = $kid->title;
                                }
                            }
                        }
                        $grp_label = implode(', ', $grp_labels);
                    ?>
                    <li>
                        <button class="filter-cat-btn"
                                data-target="filter-screen-<?php echo (int) $parent->ID; ?>">
                            <span><?php echo esc_html($parent->title); ?></span>
                            <?php if ($grp_active) : ?>
                            <span class="filter-selected-val"><?php echo esc_html($grp_label); ?></span>
                            <?php else : ?>
                            <span class="filter-cat-arrow">›</span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($has_active && $reset_url) : ?>
                <div class="filter-panel-footer">
                    <a href="<?php echo esc_url($reset_url); ?>" class="filter-reset-btn">
                        <?php esc_html_e('Сбросить', 'fastwp'); ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Screen 2+: each filter group's items -->
            <?php foreach ($data['roots'] as $parent) :
                $kids = $data['children'][$parent->ID] ?? [];
                if (empty($kids)) continue;
            ?>
            <div class="filter-screen" id="filter-screen-<?php echo (int) $parent->ID; ?>">
                <div class="filter-screen-header">
                    <button class="filter-back-btn" data-target="filter-screen-main">
                        ‹ <?php echo esc_html($parent->title); ?>
                    </button>
                    <button class="filter-close-btn">×</button>
                </div>
                <ul class="filter-sub-list">
                    <?php foreach ($kids as $kid) :
                        if ($context_mode) {
                            $kid_slug   = fastwp_menu_item_slug($kid);
                            $link_url   = $kid_slug !== ''
                                ? $build_toggle_url($kid_slug, (int) $parent->ID)
                                : $kid->url;
                            $is_current = in_array($kid_slug, $active_slugs, true);
                        } else {
                            $link_url   = $kid->url;
                            $is_current = (rtrim($kid->url, '/') === $active_url);
                        }
                    ?>
                    <li>
                        <a href="<?php echo esc_url($link_url); ?>"
                           class="filter-sub-link<?php echo $is_current ? ' active' : ''; ?>">
                            <?php echo esc_html($kid->title); ?>
                            <?php if ($is_current) : ?>
                            <span class="filter-sub-check">✓</span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>

        </div><!-- /.filter-panel -->
    </div><!-- /.filter-panel-wrap -->
    <?php
}

/* ============================================================
   Apply ?kb_filter=SLUG to category archive main query
   Combines the current category with the filter category via
   an AND tax_query so users see e.g. "series from 2020".
   ============================================================ */

add_action('pre_get_posts', 'fastwp_apply_category_filter', 15);

function fastwp_apply_category_filter(WP_Query $q): void
{
    if (is_admin() || !$q->is_main_query()) {
        return;
    }
    if (!$q->is_category()) {
        return;
    }

    $filter_path = sanitize_text_field((string) $q->get('kb_filter_path'));
    if (!$filter_path) {
        return;
    }

    // Parse path into individual slugs: "2020/action" → ['2020', 'action']
    $slugs = array_values(array_filter(
        array_map('sanitize_key', explode('/', trim($filter_path, '/')))
    ));
    if (empty($slugs)) {
        return;
    }

    // Resolve context category (base of the URL, e.g. "films")
    $cat_name = (string) $q->get('category_name');
    if (!$cat_name) {
        return;
    }
    $parts        = explode('/', trim($cat_name, '/'));
    $context_slug = (string) end($parts);
    $context_term = get_category_by_slug($context_slug);
    if (!$context_term) {
        return;
    }

    // Resolve filter terms, skip unknown slugs
    $filter_terms = [];
    foreach ($slugs as $slug) {
        $term = get_category_by_slug($slug);
        if ($term) {
            $filter_terms[] = $term;
        }
    }
    if (empty($filter_terms)) {
        return;
    }

    // Build AND tax_query: posts must belong to the context AND every filter category.
    // This covers both cross-taxonomy (films + 2020 + action) and genuine child hierarchies
    // (год + 2020) — AND gives correct results in both cases.
    $tax_query = [
        'relation' => 'AND',
        ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [(int) $context_term->term_id]],
    ];
    foreach ($filter_terms as $ft) {
        $tax_query[] = ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [(int) $ft->term_id]];
    }

    $q->set('category_name', '');
    $q->set('tax_query', $tax_query);
}
