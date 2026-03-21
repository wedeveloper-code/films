<?php
/**
 * 404 Error Template
 *
 * @package KinoBase
 */

get_header();
?>
<main class="site-content" id="main" role="main">
    <div class="container">
        <div class="error-page">
            <div class="error-code">404</div>
            <h1 class="error-msg"><?php esc_html_e('Страница не найдена', 'kinobase'); ?></h1>
            <p class="error-sub"><?php esc_html_e('Возможно, страница была перемещена или удалена. Попробуйте найти нужный фильм через поиск.', 'kinobase'); ?></p>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-home">
                <?php esc_html_e('На главную', 'kinobase'); ?>
            </a>
        </div>
    </div>
</main>
<?php get_footer(); ?>
