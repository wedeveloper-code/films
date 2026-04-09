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
    $post_id = (int) $comment->comment_post_ID;
    if (!$post_id || get_post_type($post_id) !== 'post') return;

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
