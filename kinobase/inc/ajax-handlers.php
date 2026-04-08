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

// Contact form — available for guests and logged-in users
add_action('wp_ajax_kinobase_contact',        'kinobase_ajax_contact');
add_action('wp_ajax_nopriv_kinobase_contact', 'kinobase_ajax_contact');

function kinobase_ajax_contact(): void
{
    if (!check_ajax_referer('kinobase_contact', 'nonce', false)) {
        wp_send_json_error(['message' => 'Ошибка безопасности. Обновите страницу.'], 403);
    }

    $name    = sanitize_text_field($_POST['contact_name']    ?? '');
    $email   = sanitize_email($_POST['contact_email']        ?? '');
    $message = sanitize_textarea_field($_POST['contact_message'] ?? '');

    if (!$name || !$email || !$message) {
        wp_send_json_error(['message' => 'Пожалуйста, заполните все поля.']);
    }

    if (!is_email($email)) {
        wp_send_json_error(['message' => 'Некорректный адрес email.']);
    }

    if (mb_strlen($message) < 10) {
        wp_send_json_error(['message' => 'Сообщение слишком короткое.']);
    }

    $post_id = wp_insert_post([
        'post_type'    => 'kb_message',
        'post_status'  => 'publish',
        'post_title'   => $name . ' <' . $email . '>',
        'post_content' => $message,
        'post_date'    => current_time('mysql'),
    ]);

    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => 'Ошибка сервера. Попробуйте позже.']);
    }

    update_post_meta($post_id, 'contact_name',  $name);
    update_post_meta($post_id, 'contact_email', $email);

    wp_send_json_success(['message' => 'Спасибо! Ваше сообщение отправлено.']);
}
