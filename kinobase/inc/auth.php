<?php
/**
 * KinoBase — Custom Authentication
 *
 * - Auto-creates /login/ and /register/ pages on theme activation
 * - Redirects wp-login.php to custom pages
 * - Handles registration: form → confirmation email → create user → send credentials
 * - Handles login via wp_signon()
 * - Restricts Author role: own media only, trimmed admin menu
 *
 * @package KinoBase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   Auto-create /login/ and /register/ pages
   ============================================================ */

add_action('after_switch_theme', 'kb_auth_create_pages');
add_action('init', 'kb_auth_create_pages_once');

function kb_auth_create_pages_once(): void
{
    if (get_transient('kb_auth_pages_ok')) {
        return;
    }
    kb_auth_create_pages();
    set_transient('kb_auth_pages_ok', 1, WEEK_IN_SECONDS);
}

function kb_auth_create_pages(): void
{
    $pages = [
        'login'    => 'Вход',
        'register' => 'Регистрация',
    ];
    foreach ($pages as $slug => $title) {
        if (!get_page_by_path($slug)) {
            wp_insert_post([
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_author'  => 1,
                'post_content' => '',
            ]);
        }
    }
}

/* ============================================================
   URL filters — point WP functions to custom pages
   ============================================================ */

add_filter('login_url', 'kb_filter_login_url', 10, 2);

function kb_filter_login_url(string $url, string $redirect = ''): string
{
    $page_url = home_url('/login/');
    if ($redirect) {
        $page_url = add_query_arg('redirect_to', rawurlencode($redirect), $page_url);
    }
    return $page_url;
}

add_filter('register_url', static fn(): string => home_url('/register/'));

// NOTE: wp-login.php remains accessible directly (for admin safety).
// Custom /login/ page is used via filtered login_url in theme links.

/* ============================================================
   Redirect already-logged-in users away from auth pages
   ============================================================ */

add_action('template_redirect', 'kb_redirect_logged_in_from_auth');

function kb_redirect_logged_in_from_auth(): void
{
    if (!is_user_logged_in()) {
        return;
    }
    if (is_page(['login', 'register'])) {
        wp_safe_redirect(home_url('/'));
        exit;
    }
}

/* ============================================================
   Process registration form (POST on /register/)
   ============================================================ */

add_action('template_redirect', 'kb_handle_register', 1);

function kb_handle_register(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['kb_action'] ?? '') !== 'register') {
        return;
    }
    if (!is_page('register')) {
        return;
    }

    if (
        !isset($_POST['kb_reg_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kb_reg_nonce'])), 'kb_register')
    ) {
        kb_auth_redirect_back('register', 'invalid_nonce');
    }

    if (!get_option('users_can_register')) {
        kb_auth_redirect_back('register', 'registration_disabled');
    }

    $username = sanitize_user(wp_unslash($_POST['kb_username'] ?? ''));
    $email    = sanitize_email(wp_unslash($_POST['kb_email'] ?? ''));

    if (mb_strlen($username) < 3 || !validate_username($username)) {
        kb_auth_redirect_back('register', 'invalid_username');
    }
    if (!is_email($email)) {
        kb_auth_redirect_back('register', 'invalid_email');
    }
    if (username_exists($username)) {
        kb_auth_redirect_back('register', 'username_exists');
    }
    if (email_exists($email)) {
        kb_auth_redirect_back('register', 'email_exists');
    }

    // Rate-limit: one confirmation email per email per hour
    if (get_transient('kb_reg_rate_' . md5($email))) {
        kb_auth_redirect_back('register', 'rate_limit');
    }
    set_transient('kb_reg_rate_' . md5($email), 1, HOUR_IN_SECONDS);

    // Store pending registration (24 h)
    $token = bin2hex(random_bytes(24));
    set_transient('kb_pending_reg_' . $token, [
        'username' => $username,
        'email'    => $email,
    ], DAY_IN_SECONDS);

    // Send confirmation email
    $confirm_url = add_query_arg('kb_confirm', $token, home_url('/'));
    $site_name   = get_bloginfo('name');

    wp_mail(
        $email,
        sprintf('[%s] Подтверждение регистрации', $site_name),
        sprintf(
            "Здравствуйте, %s!\n\n"
            . "Для завершения регистрации на сайте «%s» перейдите по ссылке:\n\n%s\n\n"
            . "Ссылка действительна 24 часа.\n\n"
            . "Если вы не регистрировались на нашем сайте — просто проигнорируйте это письмо.",
            $username,
            $site_name,
            $confirm_url
        )
    );

    wp_safe_redirect(add_query_arg('kb_status', 'confirm_sent', home_url('/register/')));
    exit;
}

/* ============================================================
   Process email confirmation (GET ?kb_confirm=TOKEN on any page)
   ============================================================ */

add_action('init', 'kb_handle_email_confirm', 1);

function kb_handle_email_confirm(): void
{
    if (empty($_GET['kb_confirm'])) {
        return;
    }

    $token   = sanitize_text_field(wp_unslash($_GET['kb_confirm']));
    $pending = get_transient('kb_pending_reg_' . $token);

    if (!is_array($pending) || empty($pending['username']) || empty($pending['email'])) {
        wp_safe_redirect(add_query_arg('kb_status', 'confirm_invalid', home_url('/register/')));
        exit;
    }

    $username = (string) $pending['username'];
    $email    = (string) $pending['email'];

    // Guard: someone else may have taken the username/email meanwhile
    if (username_exists($username) || email_exists($email)) {
        delete_transient('kb_pending_reg_' . $token);
        wp_safe_redirect(add_query_arg('kb_status', 'already_registered', home_url('/login/')));
        exit;
    }

    // Create user
    $password = wp_generate_password(12, true, false);
    $user_id  = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        wp_safe_redirect(add_query_arg('kb_status', 'create_failed', home_url('/register/')));
        exit;
    }

    // Assign role (uses WP default_role setting, typically 'author')
    (new WP_User($user_id))->set_role((string) get_option('default_role', 'author'));

    delete_transient('kb_pending_reg_' . $token);

    // Send credentials email
    $site_name = get_bloginfo('name');
    $login_url = home_url('/login/');

    wp_mail(
        $email,
        sprintf('[%s] Ваши данные для входа', $site_name),
        sprintf(
            "Здравствуйте, %s!\n\n"
            . "Регистрация на сайте «%s» успешно завершена.\n\n"
            . "Ваши данные для входа:\n"
            . "Логин: %s\n"
            . "Пароль: %s\n\n"
            . "Страница входа: %s\n\n"
            . "Рекомендуем изменить пароль после первого входа.",
            $username,
            $site_name,
            $username,
            $password,
            $login_url
        )
    );

    wp_safe_redirect(add_query_arg('kb_status', 'registered', home_url('/login/')));
    exit;
}

/* ============================================================
   Process login form (POST on /login/)
   ============================================================ */

add_action('template_redirect', 'kb_handle_login', 1);

function kb_handle_login(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['kb_action'] ?? '') !== 'login') {
        return;
    }
    if (!is_page('login')) {
        return;
    }

    if (
        !isset($_POST['kb_login_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kb_login_nonce'])), 'kb_login')
    ) {
        kb_auth_redirect_back('login', 'invalid_nonce');
    }

    $login    = sanitize_text_field(wp_unslash($_POST['kb_login'] ?? ''));
    $password = wp_unslash($_POST['kb_password'] ?? '');
    $remember = !empty($_POST['kb_remember']);

    if (empty($login) || empty($password)) {
        kb_auth_redirect_back('login', 'empty_fields');
    }

    $result = wp_signon([
        'user_login'    => $login,
        'user_password' => $password,
        'remember'      => $remember,
    ], is_ssl());

    if (is_wp_error($result)) {
        kb_auth_redirect_back('login', 'wrong_credentials');
    }

    $redirect_to = isset($_POST['redirect_to'])
        ? esc_url_raw(wp_unslash($_POST['redirect_to']))
        : home_url('/');

    wp_safe_redirect($redirect_to);
    exit;
}

/* ============================================================
   Author role — trim admin sidebar
   ============================================================ */

add_action('admin_menu', 'kb_restrict_author_admin_menu', 999);

function kb_restrict_author_admin_menu(): void
{
    if (current_user_can('manage_options')) {
        return;
    }

    $remove = [
        'index.php',           // Dashboard
        'edit.php',            // Posts
        'edit-comments.php',   // Comments
        'tools.php',           // Tools
        'options-general.php', // Settings
    ];

    foreach ($remove as $page) {
        remove_menu_page($page);
    }
}

/* ============================================================
   Author role — restrict media library to own uploads
   ============================================================ */

// Media modal (Gutenberg / Classic editor insert media)
add_filter('ajax_query_attachments_args', 'kb_restrict_media_library');

function kb_restrict_media_library(array $query): array
{
    if (current_user_can('manage_options')) {
        return $query;
    }
    $query['author'] = get_current_user_id();
    return $query;
}

// Media list table in wp-admin/upload.php
add_action('pre_get_posts', 'kb_restrict_media_list_table');

function kb_restrict_media_list_table(WP_Query $q): void
{
    if (!is_admin() || !$q->is_main_query()) {
        return;
    }
    if ($q->get('post_type') !== 'attachment') {
        return;
    }
    if (current_user_can('manage_options')) {
        return;
    }
    $q->set('author', get_current_user_id());
}

/* ============================================================
   Helpers
   ============================================================ */

function kb_auth_redirect_back(string $page, string $error): never
{
    wp_safe_redirect(add_query_arg('kb_error', $error, home_url('/' . $page . '/')));
    exit;
}

function kb_auth_error_message(string $code): string
{
    return [
        'invalid_nonce'          => 'Ошибка безопасности. Обновите страницу и попробуйте снова.',
        'registration_disabled'  => 'Регистрация на сайте временно отключена.',
        'invalid_username'       => 'Имя пользователя должно содержать минимум 3 символа и состоять из латинских букв, цифр или знаков _, -, @.',
        'invalid_email'          => 'Укажите корректный адрес электронной почты.',
        'username_exists'        => 'Это имя пользователя уже занято. Выберите другое.',
        'email_exists'           => 'Этот адрес почты уже зарегистрирован. Попробуйте войти.',
        'rate_limit'             => 'Письмо уже было отправлено. Проверьте почту или попробуйте через час.',
        'create_failed'          => 'Не удалось создать аккаунт. Пожалуйста, попробуйте позже.',
        'already_registered'     => 'Аккаунт с таким логином или email уже существует.',
        'confirm_invalid'        => 'Ссылка недействительна или срок её действия истёк.',
        'wrong_credentials'      => 'Неверный логин или пароль.',
        'empty_fields'           => 'Пожалуйста, заполните все поля.',
    ][$code] ?? 'Произошла ошибка. Попробуйте ещё раз.';
}

function kb_auth_status_message(string $code): string
{
    return [
        'confirm_sent'       => 'Письмо с подтверждением отправлено. Проверьте почту и перейдите по ссылке для завершения регистрации.',
        'confirm_invalid'    => 'Ссылка недействительна или её срок истёк. Попробуйте зарегистрироваться заново.',
        'registered'         => 'Регистрация завершена! Логин и пароль отправлены на ваш email.',
        'already_registered' => 'Этот аккаунт уже активирован. Войдите, используя свои данные.',
        'create_failed'      => 'Не удалось создать аккаунт. Попробуйте зарегистрироваться заново.',
    ][$code] ?? '';
}
