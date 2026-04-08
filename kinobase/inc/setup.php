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
        'primary' => __('Главное меню', 'kinobase'),
        'footer'  => __('Подвал', 'kinobase'),
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
        $count = (int) wp_count_posts('post')->publish;
        set_transient('kinobase_movie_count', $count, HOUR_IN_SECONDS * 6);
    }
    return (int) $count;
}

// Remove category prefix from archive title
add_filter('get_the_archive_title', function (string $title): string {
    if (is_category()) {
        return single_cat_title('', false);
    }
    return $title;
});

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
