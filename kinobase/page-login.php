<?php
/**
 * Template for the Login page (slug: login)
 *
 * @package KinoBase
 */

if (!defined('ABSPATH')) {
    exit;
}

$error       = isset($_GET['kb_error'])    ? sanitize_key($_GET['kb_error'])    : '';
$status      = isset($_GET['kb_status'])   ? sanitize_key($_GET['kb_status'])   : '';
$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';

get_header();
?>
<main class="site-content" id="main" role="main">
    <div class="auth-wrap">
        <div class="auth-card">

            <a class="auth-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('На главную', 'kinobase'); ?>">
                KINO<strong>BASE</strong>
            </a>

            <h1 class="auth-title"><?php esc_html_e('Вход', 'kinobase'); ?></h1>

            <?php if ($error) : ?>
            <div class="auth-alert auth-alert-error" role="alert">
                <?php echo esc_html(kb_auth_error_message($error)); ?>
            </div>
            <?php endif; ?>

            <?php if ($status) :
                $msg = kb_auth_status_message($status);
                if ($msg) :
            ?>
            <div class="auth-alert auth-alert-success" role="alert">
                <?php echo esc_html($msg); ?>
            </div>
            <?php endif; endif; ?>

            <form class="auth-form" method="post"
                  action="<?php echo esc_url(home_url('/login/')); ?>"
                  novalidate>
                <?php wp_nonce_field('kb_login', 'kb_login_nonce'); ?>
                <input type="hidden" name="kb_action" value="login">
                <?php if ($redirect_to) : ?>
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                <?php endif; ?>

                <div class="auth-field">
                    <label class="auth-label" for="kb-login">
                        <?php esc_html_e('Логин или Email', 'kinobase'); ?>
                    </label>
                    <input type="text" id="kb-login" name="kb_login"
                           class="auth-input<?php echo $error === 'wrong_credentials' ? ' is-error' : ''; ?>"
                           autocomplete="username"
                           value="<?php echo esc_attr(sanitize_text_field($_GET['kb_login'] ?? '')); ?>"
                           required autofocus>
                </div>

                <div class="auth-field">
                    <label class="auth-label" for="kb-password">
                        <?php esc_html_e('Пароль', 'kinobase'); ?>
                    </label>
                    <div class="auth-input-wrap">
                        <input type="password" id="kb-password" name="kb_password"
                               class="auth-input<?php echo $error === 'wrong_credentials' ? ' is-error' : ''; ?>"
                               autocomplete="current-password" required>
                        <button type="button" class="auth-toggle-pw"
                                aria-label="<?php esc_attr_e('Показать/скрыть пароль', 'kinobase'); ?>"
                                data-target="kb-password" tabindex="-1">
                            <svg class="pw-icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="pw-icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="display:none">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <label class="auth-remember">
                    <input type="checkbox" name="kb_remember" value="1">
                    <?php esc_html_e('Запомнить меня', 'kinobase'); ?>
                </label>

                <button type="submit" class="auth-submit">
                    <?php esc_html_e('Войти', 'kinobase'); ?>
                </button>
            </form>

            <div class="auth-links">
                <a href="<?php echo esc_url(wp_lostpassword_url(home_url('/'))); ?>">
                    <?php esc_html_e('Забыли пароль?', 'kinobase'); ?>
                </a>
                <span class="auth-links-sep" aria-hidden="true">·</span>
                <a href="<?php echo esc_url(home_url('/register/')); ?>">
                    <?php esc_html_e('Регистрация', 'kinobase'); ?>
                </a>
            </div>

        </div>
    </div>
</main>
<?php get_footer(); ?>
