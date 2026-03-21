<!-- Site Footer -->
<footer class="site-footer" role="contentinfo">
    <div class="container footer-inner">
        <p>
            &copy; <?php echo esc_html(date_i18n('Y')); ?> KINOBASE.
            <?php esc_html_e('Все права защищены. Сайт не содержит видеофайлов, а лишь предоставляет ссылки на открытые источники.', 'kinobase'); ?>
        </p>
        <nav class="footer-links" aria-label="<?php esc_attr_e('Правовая информация', 'kinobase'); ?>">
            <?php
            $footer_links = [
                __('Пользовательское соглашение', 'kinobase') => get_privacy_policy_url() ?: '#',
                __('Правообладателям', 'kinobase')             => home_url('/copyrights/'),
                __('Контакты', 'kinobase')                     => home_url('/contacts/'),
            ];
            foreach ($footer_links as $text => $url) {
                echo '<a href="' . esc_url($url) . '">' . esc_html($text) . '</a>';
            }
            ?>
        </nav>
    </div>
</footer>
<!-- /Site Footer -->

</div><!-- .site-wrapper -->

<?php wp_footer(); ?>
</body>
</html>
