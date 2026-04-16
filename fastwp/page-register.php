<?php
/**
 * Template for the Registration page (slug: register)
 *
 * @package FastWP
 */

if (!defined('ABSPATH')) {
    exit;
}

$error  = isset($_GET['kb_error'])  ? sanitize_key($_GET['kb_error'])  : '';
$status = isset($_GET['kb_status']) ? sanitize_key($_GET['kb_status']) : '';

// Pre-fill username on error
$prev_username = isset($_GET['kb_username']) ? sanitize_user(wp_unslash($_GET['kb_username'])) : '';

get_header();
?>
<main class="site-content" id="main" role="main">
    <div class="auth-wrap">
        <div class="auth-card">

            <a class="auth-logo" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('На главную', 'fastwp'); ?>">
                FAST<strong>WP</strong>
            </a>

            <h1 class="auth-title"><?php esc_html_e('Регистрация', 'fastwp'); ?></h1>

            <?php if (!get_option('users_can_register')) : ?>
            <div class="auth-alert auth-alert-error" role="alert">
                <?php esc_html_e('Регистрация на сайте временно отключена.', 'fastwp'); ?>
            </div>

            <?php else : ?>

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

            <?php
            // After successful submission — show only the success block, hide the form
            $hide_form = in_array($status, ['confirm_sent'], true);
            ?>

            <?php if (!$hide_form) : ?>
            <form class="auth-form" method="post"
                  action="<?php echo esc_url(home_url('/register/')); ?>"
                  novalidate>
                <?php wp_nonce_field('kb_register', 'kb_reg_nonce'); ?>
                <input type="hidden" name="kb_action" value="register">

                <div class="auth-field">
                    <label class="auth-label" for="kb-username">
                        <?php esc_html_e('Имя пользователя', 'fastwp'); ?>
                    </label>
                    <input type="text" id="kb-username" name="kb_username"
                           class="auth-input<?php echo in_array($error, ['invalid_username', 'username_exists'], true) ? ' is-error' : ''; ?>"
                           autocomplete="username"
                           value="<?php echo esc_attr($prev_username); ?>"
                           minlength="3" maxlength="60"
                           pattern="[a-zA-Z0-9._\-@]+"
                           required autofocus>
                    <p class="auth-hint">
                        <?php esc_html_e('Латинские буквы, цифры, символы: _ - @ . Минимум 3 символа.', 'fastwp'); ?>
                    </p>
                </div>

                <div class="auth-field">
                    <label class="auth-label" for="kb-email">
                        <?php esc_html_e('Email', 'fastwp'); ?>
                    </label>
                    <input type="email" id="kb-email" name="kb_email"
                           class="auth-input<?php echo in_array($error, ['invalid_email', 'email_exists'], true) ? ' is-error' : ''; ?>"
                           autocomplete="email"
                           required>
                    <p class="auth-hint">
                        <?php esc_html_e('На этот адрес придёт ссылка для подтверждения и данные для входа.', 'fastwp'); ?>
                    </p>
                </div>

                <button type="submit" class="auth-submit">
                    <?php esc_html_e('Создать аккаунт', 'fastwp'); ?>
                </button>
            </form>
            <?php endif; ?>

            <?php endif; // users_can_register ?>

            <div class="auth-links">
                <span><?php esc_html_e('Уже есть аккаунт?', 'fastwp'); ?></span>
                <a href="<?php echo esc_url(home_url('/login/')); ?>">
                    <?php esc_html_e('Войти', 'fastwp'); ?>
                </a>
            </div>

        </div>
    </div>
</main>
<?php get_footer(); ?>
