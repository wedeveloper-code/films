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

/**
 * Review rate limit: seconds between reviews from the same IP on the same post.
 * Change this constant to adjust the limit.
 * 60 = once per minute. 3600 = once per hour.
 */
if (!defined('KB_REVIEW_RATE_LIMIT')) {
    define('KB_REVIEW_RATE_LIMIT', MINUTE_IN_SECONDS);
}

// Admin notice on edit-comments.php: explains where to change the rate limit
add_action('admin_notices', 'kinobase_review_rate_admin_notice');
function kinobase_review_rate_admin_notice(): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-comments') {
        return;
    }
    $current   = KB_REVIEW_RATE_LIMIT;
    $minutes   = round($current / 60, 1);
    $file_path = 'wp-content/themes/kinobase/inc/ajax-handlers.php';
    echo '<div class="notice notice-info" style="padding:12px 16px;">'
        . '<strong>KinoBase — интервал отзывов:</strong> '
        . esc_html(sprintf(
            'Один пользователь может оставлять отзыв не чаще, чем раз в %s мин. '
            . 'Чтобы изменить — откройте файл %s и измените константу KB_REVIEW_RATE_LIMIT (значение в секундах). '
            . 'Сейчас: %d сек. (%s мин.).',
            $minutes,
            $file_path,
            $current,
            $minutes
        ))
        . '</div>';
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

// Review (comment) — available for guests and logged-in users
add_action('wp_ajax_kinobase_review',        'kinobase_ajax_review');
add_action('wp_ajax_nopriv_kinobase_review', 'kinobase_ajax_review');

function kinobase_ajax_review(): void
{
    if (!check_ajax_referer('kinobase_review', 'nonce', false)) {
        wp_send_json_error(['message' => 'Ошибка безопасности. Обновите страницу.'], 403);
    }

    $post_id       = absint($_POST['post_id'] ?? 0);
    $author        = sanitize_text_field($_POST['review_author'] ?? '');
    $text          = sanitize_textarea_field($_POST['review_text'] ?? '');
    $rating        = min(10, max(1, (int) ($_POST['review_rating'] ?? 5)));
    $captcha_key   = sanitize_text_field($_POST['captcha_key'] ?? '');
    $captcha_given = (int) ($_POST['captcha_answer'] ?? -999);

    if (!$post_id || get_post_status($post_id) !== 'publish') {
        wp_send_json_error(['message' => 'Неверный запрос.']);
    }

    if (!$author || mb_strlen($author) < 2) {
        wp_send_json_error(['message' => 'Укажите ваше имя (минимум 2 символа).']);
    }

    if (!$text || mb_strlen($text) < 10) {
        wp_send_json_error(['message' => 'Отзыв слишком короткий (минимум 10 символов).']);
    }

    // Validate captcha
    if (!$captcha_key) {
        wp_send_json_error(['message' => 'Ошибка проверки. Обновите страницу.']);
    }
    $expected = get_transient('kb_captcha_' . $captcha_key);
    if ($expected === false || (int) $expected !== $captcha_given) {
        wp_send_json_error(['message' => 'Неверный ответ на проверочный вопрос.']);
    }
    delete_transient('kb_captcha_' . $captcha_key);

    // Rate-limit: one comment per IP per post per hour
    $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
    $rate_key = 'kb_rev_' . md5($ip . $post_id);
    if (get_transient($rate_key)) {
        wp_send_json_error(['message' => 'Вы уже оставляли отзыв недавно. Попробуйте позже.']);
    }

    $comment_id = wp_insert_comment([
        'comment_post_ID'  => $post_id,
        'comment_author'   => $author,
        'comment_content'  => $text,
        'comment_type'     => 'comment',
        'comment_approved' => 0, // pending moderation
        'comment_date'     => current_time('mysql'),
        'comment_agent'    => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'comment_author_IP'=> $ip,
    ]);

    if (!$comment_id || is_wp_error($comment_id)) {
        wp_send_json_error(['message' => 'Ошибка сервера. Попробуйте позже.']);
    }

    update_comment_meta((int) $comment_id, 'review_rating', $rating);
    // Rate limit: 1 review per minute per IP per post.
    // To change the interval: search for KB_REVIEW_RATE_LIMIT in inc/ajax-handlers.php
    set_transient($rate_key, 1, KB_REVIEW_RATE_LIMIT);

    wp_send_json_success([
        'message' => 'Спасибо! Ваш отзыв отправлен на модерацию и появится после проверки.',
        'html'    => '',
    ]);
}
