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
    return $count;
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
