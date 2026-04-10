<?php
/**
 * KinoBase Theme Setup
 *
 * @package KinoBase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', 'kinobase_setup');

function kinobase_setup(): void
{
    // Localization
    load_theme_textdomain('kinobase', KINOBASE_DIR . '/languages');

    // HTML5 support
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);

    // Title tag
    add_theme_support('title-tag');

    // Post thumbnails
    add_theme_support('post-thumbnails');

    // Movie poster: 2:3 ratio
    add_image_size('movie-poster', 300, 450, true);
    add_image_size('movie-poster-sm', 150, 225, true);

    // Menus
    register_nav_menus([
        'primary'          => __('Верхнее меню (рубрики)', 'kinobase'),
        'kinobase_filters' => __('Меню фильтров (Год, Жанр, Качество…)', 'kinobase'),
        'footer'           => __('Меню подвала', 'kinobase'),
    ]);

    // Wide alignment
    add_theme_support('align-wide');

    // Responsive embeds
    add_theme_support('responsive-embeds');
}

// Cache total movie count
add_action('save_post', 'kinobase_invalidate_movie_count');
add_action('delete_post', 'kinobase_invalidate_movie_count');

function kinobase_invalidate_movie_count(): void
{
    delete_transient('kinobase_movie_count');
}

/**
 * Get cached total movie count
 */
function kinobase_get_movie_count(): int
{
    $count = get_transient('kinobase_movie_count');
    if ($count === false) {
        $posts  = (int) (wp_count_posts('post')->publish ?? 0);
        $movies = (int) (wp_count_posts('movie')->publish ?? 0);
        $count  = $posts + $movies;
        set_transient('kinobase_movie_count', $count, HOUR_IN_SECONDS * 6);
    }
    return (int) $count;
}

// Invalidate count when movie CPT posts change too
add_action('save_post_movie', 'kinobase_invalidate_movie_count');
add_action('delete_post',     'kinobase_invalidate_movie_count');

// Remove category prefix from archive title
add_filter('get_the_archive_title', function (string $title): string {
    if (is_category()) {
        return single_cat_title('', false);
    }
    return $title;
});

// Reduce review rate-limit flood: our ajax handler already limits to 1/hour.
// This disables WordPress's own 15-second flood check for wp_insert_comment calls.
add_filter('comment_flood_filter', '__return_false');

/* ============================================================
   Customizer: footer copyright text
   ============================================================ */
add_action('customize_register', 'kinobase_customizer_register');

function kinobase_customizer_register(WP_Customize_Manager $wp_customize): void
{
    $wp_customize->add_section('kinobase_footer', [
        'title'    => __('Подвал сайта', 'kinobase'),
        'priority' => 120,
    ]);

    $wp_customize->add_setting('kinobase_footer_copyright', [
        'default'           => '',
        'sanitize_callback' => 'wp_kses_post',
        'transport'         => 'refresh',
    ]);

    $wp_customize->add_control('kinobase_footer_copyright', [
        'label'       => __('Текст авторского права (подвал)', 'kinobase'),
        'description' => __('Оставьте пустым для использования стандартного текста.', 'kinobase'),
        'section'     => 'kinobase_footer',
        'type'        => 'textarea',
    ]);
}

// Add body classes for theme
add_filter('body_class', function (array $classes): array {
    $classes[] = 'kinobase';
    return $classes;
});

/**
 * Format price for display on movie cards.
 * Defined here (not in template) to avoid redeclaration on each card.
 */
function kb_price(int $p): string
{
    return $p > 0 ? number_format($p) . '₽' : '—';
}

/**
 * Fallback nav menu — outputs category links when no menu is assigned.
 * Defined here (not in header.php) to prevent redeclaration if template is loaded twice.
 */
function kinobase_fallback_menu(): void
{
    $cats = [
        ['slug' => 'films',  'name' => 'Фильмы'],
        ['slug' => 'series', 'name' => 'Сериалы'],
        ['slug' => 'tv',     'name' => 'Телепередачи'],
        ['slug' => 'new',    'name' => 'Новинки'],
    ];
    foreach ($cats as $c) {
        $cat = get_category_by_slug($c['slug'])
            ?: get_term_by('name', $c['name'], 'category');
        $url = $cat ? get_category_link($cat->term_id) : home_url('/');
        echo '<a href="' . esc_url($url) . '">' . esc_html(__($c['name'], 'kinobase')) . '</a>';
    }
}

/**
 * Mobile fallback nav — outputs <li><a> links for mobile drawer.
 */
function kinobase_mobile_fallback_menu(): void
{
    $cats = [
        ['slug' => 'films',  'name' => 'Фильмы'],
        ['slug' => 'series', 'name' => 'Сериалы'],
        ['slug' => 'tv',     'name' => 'Телепередачи'],
        ['slug' => 'new',    'name' => 'Новинки'],
    ];
    echo '<ul class="mobile-nav-list">';
    foreach ($cats as $c) {
        $cat = get_category_by_slug($c['slug'])
            ?: get_term_by('name', $c['name'], 'category');
        $url = $cat ? get_category_link($cat->term_id) : home_url('/');
        echo '<li><a href="' . esc_url($url) . '">' . esc_html(__($c['name'], 'kinobase')) . '</a></li>';
    }
    echo '</ul>';
}

/**
 * Simple nav walker — outputs bare <a> links without extra <li> wrappers.
 * Defined here (not in header.php) to prevent redeclaration if template is loaded twice.
 */
class Kinobase_Nav_Walker extends Walker_Nav_Menu
{
    public function start_el(&$output, $data_object, $depth = 0, $args = null, $id = 0): void
    {
        $item   = $data_object;
        $class  = in_array('current-menu-item', (array) $item->classes, true) ? 'current-menu-item' : '';
        $output .= '<a href="' . esc_url($item->url) . '"'
            . ($class ? ' class="' . esc_attr($class) . '"' : '') . '>'
            . esc_html($item->title) . '</a>';
    }
}

/**
 * Custom post type: Contact Messages (kb_message)
 * Visible only in admin, stores contact form submissions.
 */
add_action('init', 'kinobase_register_message_cpt');

function kinobase_register_message_cpt(): void
{
    register_post_type('kb_message', [
        'labels' => [
            'name'               => 'Сообщения',
            'singular_name'      => 'Сообщение',
            'menu_name'          => 'Сообщения',
            'all_items'          => 'Все сообщения',
            'view_item'          => 'Просмотр сообщения',
            'search_items'       => 'Поиск сообщений',
            'not_found'          => 'Сообщений нет',
            'not_found_in_trash' => 'Корзина пуста',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-email-alt',
        'menu_position' => 25,
        'supports'      => ['title', 'editor'],
        'capabilities'  => ['create_posts' => 'do_not_allow'],
        'map_meta_cap'  => true,
    ]);
}

// Admin columns: Email + Date received
add_filter('manage_kb_message_posts_columns', 'kinobase_message_columns');

function kinobase_message_columns(array $cols): array
{
    return [
        'cb'              => $cols['cb'],
        'title'           => 'Отправитель',
        'kb_email'        => 'Email',
        'kb_msg'          => 'Сообщение',
        'date'            => 'Дата',
    ];
}

add_action('manage_kb_message_posts_custom_column', 'kinobase_message_column_data', 10, 2);

function kinobase_message_column_data(string $column, int $post_id): void
{
    if ($column === 'kb_email') {
        $email = get_post_meta($post_id, 'contact_email', true);
        echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
    }
    if ($column === 'kb_msg') {
        echo esc_html(wp_trim_words(get_post_field('post_content', $post_id), 12));
    }
}

/**
 * Recalculate movie_rating from approved review comments.
 * Fires when a comment status changes (approve / unapprove / trash / spam).
 */
add_action('transition_comment_status', 'kinobase_recalculate_movie_rating', 10, 3);

function kinobase_recalculate_movie_rating(string $new_status, string $old_status, WP_Comment $comment): void
{
    $post_id   = (int) $comment->comment_post_ID;
    $post_type = get_post_type($post_id);
    if (!$post_id || !in_array($post_type, ['post', 'movie'], true)) return;

    $approved_comments = get_comments([
        'post_id' => $post_id,
        'status'  => 'approve',
        'type'    => 'comment',
        'number'  => 0, // all
    ]);

    $ratings = [];
    foreach ($approved_comments as $c) {
        $r = (int) get_comment_meta((int) $c->comment_ID, 'review_rating', true);
        if ($r > 0) {
            $ratings[] = $r;
        }
    }

    if (!empty($ratings)) {
        $avg = array_sum($ratings) / count($ratings);
        update_post_meta($post_id, 'movie_rating', round($avg, 1));
    }
}

// ─────────────────────────────────────────────────────────────────
// Clean URL filtering: /category/{main}/{year}/
//                      /category/{main}/genres/{genre}/
//                      /category/{main}/{year}/genres/{genre}/
// ─────────────────────────────────────────────────────────────────
add_action('init', 'kinobase_add_rewrite_rules');
add_action('after_switch_theme', function (): void {
    kinobase_add_rewrite_rules();
    flush_rewrite_rules();
});

function kinobase_add_rewrite_rules(): void
{
    $b = 'category/([^/]+)'; // main category slug
    $y = '([0-9]{4})';        // 4-digit year
    $g = 'genres/([^/]+)';   // genre segment
    $p = 'page/([0-9]+)';    // pagination

    // year + genre + page
    add_rewrite_rule("^{$b}/{$y}/{$g}/{$p}/?$",
        'index.php?category_name=$matches[1]&kb_year=$matches[2]&kb_genre=$matches[3]&paged=$matches[4]', 'top');
    // year + genre
    add_rewrite_rule("^{$b}/{$y}/{$g}/?$",
        'index.php?category_name=$matches[1]&kb_year=$matches[2]&kb_genre=$matches[3]', 'top');
    // genre only + page
    add_rewrite_rule("^{$b}/{$g}/{$p}/?$",
        'index.php?category_name=$matches[1]&kb_genre=$matches[2]&paged=$matches[3]', 'top');
    // genre only
    add_rewrite_rule("^{$b}/{$g}/?$",
        'index.php?category_name=$matches[1]&kb_genre=$matches[2]', 'top');
    // year only + page
    add_rewrite_rule("^{$b}/{$y}/{$p}/?$",
        'index.php?category_name=$matches[1]&kb_year=$matches[2]&paged=$matches[3]', 'top');
    // year only
    add_rewrite_rule("^{$b}/{$y}/?$",
        'index.php?category_name=$matches[1]&kb_year=$matches[2]', 'top');

    // homepage filtered by year: /2022/ and /2022/page/2/
    add_rewrite_rule('^([0-9]{4})/page/([0-9]+)/?$',
        'index.php?kb_home_year=$matches[1]&paged=$matches[2]', 'top');
    add_rewrite_rule('^([0-9]{4})/?$',
        'index.php?kb_home_year=$matches[1]', 'top');
}

add_filter('query_vars', 'kinobase_query_vars');

function kinobase_query_vars(array $vars): array
{
    $vars[] = 'kb_year';
    $vars[] = 'kb_genre';
    $vars[] = 'kb_home_year';
    return $vars;
}

// Use front-page.php template for /{year}/ URLs
add_filter('template_include', 'kinobase_year_home_template');

function kinobase_year_home_template(string $template): string
{
    if (get_query_var('kb_home_year')) {
        $t = locate_template('front-page.php');
        if ($t) return $t;
    }
    return $template;
}

add_action('pre_get_posts', 'kinobase_filter_archive');

function kinobase_filter_archive(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query()) return;

    $kb_year  = sanitize_text_field($query->get('kb_year'));
    $kb_genre = sanitize_text_field($query->get('kb_genre'));

    if (!$kb_year && !$kb_genre) return;

    $tax_query = ['relation' => 'AND'];

    if ($kb_year) {
        $t = get_term_by('slug', $kb_year, 'category');
        if ($t && !is_wp_error($t)) {
            $tax_query[] = ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [(int) $t->term_id]];
        }
    }

    if ($kb_genre) {
        $t = get_term_by('slug', $kb_genre, 'category');
        if ($t && !is_wp_error($t)) {
            $tax_query[] = ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [(int) $t->term_id]];
        }
    }

    if (count($tax_query) > 1) {
        $query->set('tax_query', $tax_query);
    }
}

/**
 * Find the parent category for years (tries multiple slug/name variants).
 */
function kinobase_get_year_parent(): ?WP_Term
{
    static $cache = false;
    if ($cache !== false) return $cache ?: null;
    $t = get_term_by('slug', 'год', 'category')
      ?: get_term_by('slug', 'god', 'category')
      ?: get_term_by('slug', 'gody', 'category')
      ?: get_term_by('slug', 'years', 'category')
      ?: get_term_by('name', 'Год', 'category')
      ?: get_term_by('name', 'Годы', 'category')
      ?: get_term_by('name', 'Год выпуска', 'category');
    $cache = ($t && !is_wp_error($t)) ? $t : null;
    return $cache;
}

/**
 * Find the parent category for genres (tries multiple slug/name variants).
 */
function kinobase_get_genre_parent(): ?WP_Term
{
    static $cache = false;
    if ($cache !== false) return $cache ?: null;
    $t = get_term_by('slug', 'жанры', 'category')
      ?: get_term_by('slug', 'zhanry', 'category')
      ?: get_term_by('slug', 'genres', 'category')
      ?: get_term_by('name', 'Жанры', 'category')
      ?: get_term_by('name', 'Жанр', 'category');
    $cache = ($t && !is_wp_error($t)) ? $t : null;
    return $cache;
}

/**
 * Build a filtered category URL.
 * Pattern: /category/{main}/{year}/genres/{genre}/
 */
function kinobase_filter_url(string $main_slug, string $year = '', string $genre = ''): string
{
    $url = home_url('/category/' . $main_slug . '/');
    if ($year)  $url .= $year . '/';
    if ($genre) $url .= 'genres/' . $genre . '/';
    return $url;
}

/**
 * Primary navigation with year/genre dropdowns.
 * Replaces wp_nav_menu in header.php.
 */
function kinobase_nav_with_dropdowns(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $main_cats = [
        ['slug' => 'films',  'name' => 'Фильмы',       'label' => 'Фильмы'],
        ['slug' => 'series', 'name' => 'Сериалы',      'label' => 'Сериалы'],
        ['slug' => 'tv',     'name' => 'Телепередачи', 'label' => 'Телепередачи'],
        ['slug' => 'new',    'name' => 'Новинки',       'label' => 'Новинки'],
    ];

    $queried      = is_category() ? get_queried_object() : null;
    $active_year  = sanitize_text_field(get_query_var('kb_year'));
    $active_genre = sanitize_text_field(get_query_var('kb_genre'));
    $current_main = '';

    if ($queried) {
        foreach ($main_cats as $mc) {
            if ($queried->slug === $mc['slug']) {
                $current_main = $mc['slug'];
                break;
            }
        }
    }

    // Fetch year/genre terms once
    $year_parent  = kinobase_get_year_parent();
    $genre_parent = kinobase_get_genre_parent();

    $years = $genres = [];
    if ($year_parent) {
        $r     = get_terms(['taxonomy' => 'category', 'parent' => $year_parent->term_id,
                             'hide_empty' => true, 'orderby' => 'name', 'order' => 'DESC', 'number' => 20]);
        $years = !is_wp_error($r) ? $r : [];
    }
    if ($genre_parent) {
        $r      = get_terms(['taxonomy' => 'category', 'parent' => $genre_parent->term_id,
                              'hide_empty' => true, 'number' => 40]);
        $genres = !is_wp_error($r) ? $r : [];
    }

    $has_dropdowns = !empty($years) || !empty($genres);

    foreach ($main_cats as $mc) {
        $cat = get_category_by_slug($mc['slug'])
            ?: get_term_by('name', $mc['name'], 'category');
        if (!$cat) continue;

        $cat_url    = get_category_link($cat->term_id);
        $is_current = ($current_main === $mc['slug']);

        if (!$has_dropdowns) {
            $cls = $is_current ? ' class="current-menu-item"' : '';
            echo '<a href="' . esc_url($cat_url) . '"' . $cls . '>'
               . esc_html(__($mc['label'], 'kinobase')) . '</a>';
            continue;
        }

        $div_cls = 'nav-item' . ($is_current ? ' current-menu-item' : '');
        echo '<div class="' . esc_attr($div_cls) . '">';
        echo '<a href="' . esc_url($cat_url) . '" class="nav-item-link">'
           . esc_html(__($mc['label'], 'kinobase')) . '</a>';
        echo '<div class="nav-dropdown">';
        echo '<div class="nav-dropdown-inner">';

        if (!empty($years)) {
            echo '<div class="nav-dropdown-col">';
            echo '<div class="nav-dropdown-heading">' . esc_html__('Год', 'kinobase') . '</div>';
            foreach ($years as $term) {
                if (is_wp_error($term)) continue;
                $href = $is_current
                    ? kinobase_filter_url($mc['slug'], $term->slug, $active_genre)
                    : get_category_link($term->term_id);
                $ac   = ($active_year === $term->slug && $is_current) ? ' class="active"' : '';
                echo '<a href="' . esc_url($href) . '"' . $ac . '>' . esc_html($term->name) . '</a>';
            }
            echo '</div>';
        }

        if (!empty($genres)) {
            echo '<div class="nav-dropdown-col">';
            echo '<div class="nav-dropdown-heading">' . esc_html__('Жанр', 'kinobase') . '</div>';
            foreach ($genres as $term) {
                if (is_wp_error($term)) continue;
                $href = $is_current
                    ? kinobase_filter_url($mc['slug'], $active_year, $term->slug)
                    : get_category_link($term->term_id);
                $ac   = ($active_genre === $term->slug && $is_current) ? ' class="active"' : '';
                echo '<a href="' . esc_url($href) . '"' . $ac . '>' . esc_html($term->name) . '</a>';
            }
            echo '</div>';
        }

        echo '</div>'; // .nav-dropdown-inner
        echo '</div>'; // .nav-dropdown
        echo '</div>'; // .nav-item
    }
}

/* ============================================================
   SEO: custom title and meta description
   ============================================================ */

/**
 * Replace template variables in a SEO string.
 */
function kinobase_replace_seo_vars(string $tpl, int $post_id): string
{
    $post = get_post($post_id);
    if (!$post) return $tpl;

    $year = $genre = $quality_cat = '';
    foreach (get_the_category($post_id) as $cat) {
        if (!$cat->parent) continue;
        $parent = get_term((int) $cat->parent, 'category');
        if (!$parent || is_wp_error($parent)) continue;
        $pname = mb_strtolower($parent->name);
        if (in_array($pname, ['год', 'year', 'годы'], true) && !$year)         $year        = $cat->name;
        if (in_array($pname, ['жанры', 'жанр', 'genres', 'genre'], true) && !$genre)  $genre       = $cat->name;
        if (in_array($pname, ['качество', 'quality'], true) && !$quality_cat)  $quality_cat = $cat->name;
    }

    $vars = [
        '%название%'     => $post->post_title,
        '%год%'          => $year,
        '%жанр%'         => $genre,
        '%качество%'     => $quality_cat ?: (string) get_post_meta($post_id, 'movie_quality', true),
        '%длительность%' => (string) get_post_meta($post_id, 'movie_duration', true),
        '%сайт%'         => get_bloginfo('name'),
    ];

    return str_replace(array_keys($vars), array_values($vars), $tpl);
}

// Custom SEO title
add_filter('pre_get_document_title', 'kinobase_seo_title');

function kinobase_seo_title(string $title): string
{
    if (!is_singular(['post', 'movie'])) return $title;
    $custom = (string) get_post_meta(get_the_ID(), '_kb_seo_title', true);
    if (!$custom) return $title;
    return kinobase_replace_seo_vars($custom, get_the_ID());
}

// Custom meta description
add_action('wp_head', 'kinobase_seo_description', 1);

function kinobase_seo_description(): void
{
    if (!is_singular(['post', 'movie'])) return;
    $desc = (string) get_post_meta(get_the_ID(), '_kb_seo_description', true);
    if (!$desc) return;
    $desc = kinobase_replace_seo_vars($desc, get_the_ID());
    echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
}

/* ============================================================
   Transliteration: auto-convert Cyrillic slugs for movie/actor
   ============================================================ */

function kinobase_do_transliterate(string $text): string
{
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
        'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
        'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];
    $lower  = mb_strtolower($text);
    $result = '';
    foreach (mb_str_split($lower) as $char) {
        $result .= $map[$char] ?? $char;
    }
    $result = preg_replace('/[^a-z0-9]+/', '-', $result) ?? '';
    return trim($result, '-');
}

add_action('save_post_movie', 'kinobase_ensure_latin_slug', 20, 2);
add_action('save_post_actor', 'kinobase_ensure_latin_slug', 20, 2);

function kinobase_ensure_latin_slug(int $post_id, WP_Post $post): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post->post_status === 'auto-draft') return;

    $slug    = $post->post_name;
    $decoded = rawurldecode($slug);

    // Skip if no Cyrillic
    if (!preg_match('/[а-яёА-ЯЁ]/u', $decoded)) return;

    $new_slug = kinobase_do_transliterate($decoded);
    if (!$new_slug || $new_slug === $slug) return;

    // Avoid infinite loop
    remove_action('save_post_movie', 'kinobase_ensure_latin_slug', 20);
    remove_action('save_post_actor', 'kinobase_ensure_latin_slug', 20);

    wp_update_post(['ID' => $post_id, 'post_name' => $new_slug]);

    add_action('save_post_movie', 'kinobase_ensure_latin_slug', 20, 2);
    add_action('save_post_actor', 'kinobase_ensure_latin_slug', 20, 2);
}
