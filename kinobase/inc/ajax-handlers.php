<?php
/**
 * KinoBase AJAX Handlers
 *
 * @package KinoBase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Both logged-in and guest users can increment views
add_action('wp_ajax_kinobase_increment_views',        'kinobase_ajax_increment_views');
add_action('wp_ajax_nopriv_kinobase_increment_views', 'kinobase_ajax_increment_views');

function kinobase_ajax_increment_views(): void
{
    // Verify nonce
    if (!check_ajax_referer('kinobase_views', 'nonce', false)) {
        wp_send_json_error(['message' => 'Invalid nonce'], 403);
    }

    $post_id = absint($_POST['post_id'] ?? 0);
    if (!$post_id || get_post_status($post_id) !== 'publish') {
        wp_send_json_error(['message' => 'Invalid post'], 400);
    }

    $views = (int) get_post_meta($post_id, 'movie_views', true);
    $views++;
    update_post_meta($post_id, 'movie_views', $views);

    wp_send_json_success([
        'views' => number_format($views),
    ]);
}
