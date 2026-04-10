<?php
/**
 * KinoBase — Custom Post Types & Taxonomies
 *
 * Registers:
 *  - movie      CPT (public, uses standard 'category' taxonomy)
 *  - actor      CPT (public, has archive at /actors/)
 *  - actor_attr taxonomy (hierarchical, on 'actor')
 *
 * Also provides filter-bar helper functions consumed by
 * archive.php and front-page.php.
 *
 * @package KinoBase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   1. Register CPTs + taxonomies
   ============================================================ */
add_action('init', 'kinobase_register_post_types', 5);
add_action('after_switch_theme', static function (): void {
    kinobase_register_post_types();
    flush_rewrite_rules();
});

function kinobase_register_post_types(): void
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
        'rewrite'         => ['slug' => 'film', 'with_front' => false],
        'supports'        => ['title', 'editor', 'thumbnail', 'comments', 'excerpt'],
        'taxonomies'      => ['category', 'post_tag'],
        'show_in_rest'    => true,
        'show_in_menu'    => true,
        'menu_icon'       => 'dashicons-format-video',
        'menu_position'   => 5,
        'capability_type' => 'post',
    ]);

    /* ---------- ACTOR ----------------------------------------- */
    register_post_type('actor', [
        'labels' => [
            'name'               => 'Актёры',
            'singular_name'      => 'Актёр',
            'menu_name'          => 'Актёры',
            'add_new'            => 'Добавить актёра',
            'add_new_item'       => 'Новый актёр',
            'edit_item'          => 'Редактировать профиль',
            'new_item'           => 'Новый актёр',
            'view_item'          => 'Профиль актёра',
            'search_items'       => 'Найти актёра',
            'not_found'          => 'Актёры не найдены',
            'not_found_in_trash' => 'Корзина пуста',
            'all_items'          => 'Все актёры',
        ],
        'public'          => true,
        'has_archive'     => 'actors',
        'rewrite'         => ['slug' => 'actor', 'with_front' => false],
        'supports'        => ['title', 'editor', 'thumbnail', 'excerpt'],
        'show_in_rest'    => true,
        'show_in_menu'    => true,
        'menu_icon'       => 'dashicons-admin-users',
        'menu_position'   => 6,
        'capability_type' => 'post',
    ]);

    /* ---------- ACTOR ATTRIBUTES taxonomy ---------------------- */
    // Hierarchical: parent = attribute TYPE (Гражданство, Родом из, Ярлыки…)
    //               child  = attribute VALUE (Норвегия, Обладатель Оскара…)
    register_taxonomy('actor_attr', 'actor', [
        'labels' => [
            'name'              => 'Атрибуты актёров',
            'singular_name'     => 'Атрибут',
            'menu_name'         => 'Атрибуты',
            'all_items'         => 'Все атрибуты',
            'parent_item'       => 'Группа',
            'parent_item_colon' => 'Группа:',
            'edit_item'         => 'Редактировать',
            'update_item'       => 'Обновить',
            'add_new_item'      => 'Добавить атрибут',
            'new_item_name'     => 'Новый атрибут',
            'search_items'      => 'Найти атрибут',
        ],
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => [
            'slug'         => 'actor-attr',
            'hierarchical' => true,
            'with_front'   => false,
        ],
    ]);
}

/* ============================================================
   2. Include 'category' archive queries for 'movie' CPT
   (so /category/films/ shows both post and movie post types)
   ============================================================ */
add_action('pre_get_posts', 'kinobase_movie_in_category_archives');

function kinobase_movie_in_category_archives(WP_Query $q): void
{
    if (is_admin() || !$q->is_main_query()) {
        return;
    }
    if ($q->is_category() || $q->is_tag() || $q->is_home()) {
        $types = (array) $q->get('post_type');
        if (empty($types) || $types === ['post']) {
            $q->set('post_type', ['post', 'movie']);
        }
    }
}

/* ============================================================
   3. Filter Bar Helpers
   Used by archive.php and front-page.php to render
   the Год / Жанр / … filter UI from the WP nav menu
   "kinobase_filters" (manageable at wp-admin/nav-menus.php).
   ============================================================ */

/**
 * Fetch all items for a registered nav menu location.
 * Returns [roots[], children[pid][]] or null when no menu assigned.
 *
 * @return array{roots: object[], children: array<int, object[]>}|null
 */
function kinobase_get_filter_menu_data(string $location): ?array
{
    static $cache = [];
    if (array_key_exists($location, $cache)) {
        return $cache[$location];
    }

    $locations = get_nav_menu_locations();
    if (empty($locations[$location])) {
        return $cache[$location] = null;
    }
    $items = wp_get_nav_menu_items((int) $locations[$location]);
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
function kinobase_filter_menu_has_active(array $data, string $active_url): bool
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
 * Render desktop filter bar from the "kinobase_filters" nav menu.
 * Each top-level item = dropdown button; children = filter links.
 *
 * @param string $active_url  URL of the currently active filter (to highlight it).
 * @param string $reset_url   URL for the "× Сбросить" link (omit to hide reset).
 */
function kinobase_render_desktop_filter_bar(
    string $active_url = '',
    string $reset_url  = ''
): void {
    $data = kinobase_get_filter_menu_data('kinobase_filters');
    if (!$data) {
        return; // menu not configured yet
    }

    $active_url = rtrim($active_url, '/');
    $has_active = kinobase_filter_menu_has_active($data, $active_url);

    foreach ($data['roots'] as $parent) {
        $kids = $data['children'][$parent->ID] ?? [];
        if (empty($kids)) {
            continue;
        }

        $group_active = false;
        foreach ($kids as $kid) {
            if (rtrim($kid->url, '/') === $active_url) {
                $group_active = true;
                break;
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
                        $is_current = (rtrim($kid->url, '/') === $active_url);
                    ?>
                    <li>
                        <a href="<?php echo esc_url($kid->url); ?>"
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

    if ($has_active && $reset_url) {
        echo '<a href="' . esc_url($reset_url) . '" class="filter-bar-reset">× '
            . esc_html__('Сбросить', 'kinobase') . '</a>';
    }
}

/**
 * Render mobile sliding filter panel from the "kinobase_filters" nav menu.
 *
 * @param string $active_url URL of the currently active filter.
 * @param string $reset_url  URL for the reset link inside the panel.
 */
function kinobase_render_mobile_filter_panel(
    string $active_url = '',
    string $reset_url  = ''
): void {
    $data = kinobase_get_filter_menu_data('kinobase_filters');
    if (!$data) {
        return;
    }

    $active_url = rtrim($active_url, '/');
    $has_active = kinobase_filter_menu_has_active($data, $active_url);
    ?>
    <div class="filter-panel-wrap filter-mobile-only">
        <button class="filter-panel-btn<?php echo $has_active ? ' active' : ''; ?>"
                id="js-filter-btn" aria-expanded="false" aria-controls="js-filter-panel">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6 10a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm2 4a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1z" clip-rule="evenodd"/>
            </svg>
            <?php esc_html_e('Фильтры', 'kinobase'); ?>
            <?php if ($has_active) : ?>
            <span class="filter-panel-badge">!</span>
            <?php endif; ?>
        </button>

        <div class="filter-panel<?php echo $has_active ? ' open' : ''; ?>"
             id="js-filter-panel" role="dialog"
             aria-label="<?php esc_attr_e('Фильтры', 'kinobase'); ?>">

            <!-- Screen 1: list of filter groups -->
            <div class="filter-screen active" id="filter-screen-main">
                <div class="filter-screen-header">
                    <span class="filter-screen-title"><?php esc_html_e('Фильтры', 'kinobase'); ?></span>
                    <button class="filter-close-btn">×</button>
                </div>
                <ul class="filter-cat-list">
                    <?php foreach ($data['roots'] as $parent) :
                        $kids = $data['children'][$parent->ID] ?? [];
                        if (empty($kids)) continue;
                        $grp_active = false;
                        $grp_label  = '';
                        foreach ($kids as $kid) {
                            if (rtrim($kid->url, '/') === $active_url) {
                                $grp_active = true;
                                $grp_label  = $kid->title;
                                break;
                            }
                        }
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
                        <?php esc_html_e('Сбросить', 'kinobase'); ?>
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
                        $is_current = (rtrim($kid->url, '/') === $active_url);
                    ?>
                    <li>
                        <a href="<?php echo esc_url($kid->url); ?>"
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
