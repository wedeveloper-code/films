<?php
/**
 * Template Name: Контакты
 *
 * Contact page with AJAX form. Submissions are saved as kb_message posts.
 *
 * @package KinoBase
 */

get_header();
?>
<main class="site-content" id="main" role="main">
    <div class="container">
        <div class="contact-wrap">

            <div class="contact-intro">
                <h1 class="contact-title"><?php esc_html_e('Свяжитесь с нами', 'kinobase'); ?></h1>
                <p class="contact-subtitle">
                    <?php esc_html_e('Если вам нужно связаться с администрацией сайта — напишите нам. Мы ответим в ближайшее время.', 'kinobase'); ?>
                </p>
            </div>

            <form class="contact-form" id="contact-form" novalidate>

                <div class="contact-field">
                    <label for="contact-name" class="contact-label">
                        <?php esc_html_e('Имя', 'kinobase'); ?>
                    </label>
                    <input
                        type="text"
                        id="contact-name"
                        name="contact_name"
                        class="contact-input"
                        placeholder="<?php esc_attr_e('Ваше имя', 'kinobase'); ?>"
                        required
                        maxlength="100"
                        autocomplete="name"
                    >
                </div>

                <div class="contact-field">
                    <label for="contact-email" class="contact-label">
                        <?php esc_html_e('Email', 'kinobase'); ?>
                    </label>
                    <input
                        type="email"
                        id="contact-email"
                        name="contact_email"
                        class="contact-input"
                        placeholder="<?php esc_attr_e('example@mail.com', 'kinobase'); ?>"
                        required
                        maxlength="150"
                        autocomplete="email"
                    >
                </div>

                <div class="contact-field">
                    <label for="contact-message" class="contact-label">
                        <?php esc_html_e('Ваше сообщение', 'kinobase'); ?>
                    </label>
                    <textarea
                        id="contact-message"
                        name="contact_message"
                        class="contact-input contact-textarea"
                        placeholder="<?php esc_attr_e('Опишите ваш вопрос или предложение…', 'kinobase'); ?>"
                        required
                        maxlength="3000"
                        rows="6"
                    ></textarea>
                </div>

                <div id="contact-alert" class="contact-alert" role="alert" aria-live="polite"></div>

                <button type="submit" class="contact-submit" id="contact-submit">
                    <span class="contact-submit-text"><?php esc_html_e('Отправить', 'kinobase'); ?></span>
                    <span class="contact-submit-spinner" aria-hidden="true"></span>
                </button>

            </form>

        </div>
    </div>
</main>
<?php get_footer(); ?>
