<!-- Site Footer -->
<footer class="site-footer" role="contentinfo">
    <div class="container footer-inner">
        <p>
            <?php
            $copyright = get_theme_mod('kinobase_footer_copyright', '');
            if ($copyright) {
                echo wp_kses_post($copyright);
            } else {
                echo '&copy; ' . esc_html(date_i18n('Y')) . ' KINOBASE. ';
                esc_html_e('Все права защищены. Сайт не содержит видеофайлов, а лишь предоставляет ссылки на открытые источники.', 'kinobase');
            }
            ?>
        </p>
        <nav class="footer-links" aria-label="<?php esc_attr_e('Правовая информация', 'kinobase'); ?>">
            <?php
            if (has_nav_menu('footer')) {
                wp_nav_menu([
                    'theme_location' => 'footer',
                    'container'      => false,
                    'menu_class'     => '',
                    'items_wrap'     => '%3$s',
                    'depth'          => 1,
                    'walker'         => new Kinobase_Nav_Walker(),
                ]);
            } else {
                // Fallback: hardcoded links
                $links = [
                    __('Пользовательское соглашение', 'kinobase') => get_privacy_policy_url() ?: '#',
                    __('Правообладателям', 'kinobase')             => home_url('/copyrights/'),
                    __('Контакты', 'kinobase')                     => home_url('/contacts/'),
                ];
                foreach ($links as $text => $url) {
                    echo '<a href="' . esc_url($url) . '">' . esc_html($text) . '</a>';
                }
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
