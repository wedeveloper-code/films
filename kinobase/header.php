<!DOCTYPE html>
<html <?php language_attributes(); ?> class="dark">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
    <script>
    /* Apply saved theme BEFORE paint to prevent flash */
    (function(){
        var t = localStorage.getItem('kinobase_theme');
        var d = window.matchMedia('(prefers-color-scheme: dark)').matches;
        var isDark = t ? t === 'dark' : d;
        document.documentElement.classList.toggle('dark', isDark);
    })();
    </script>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="site-wrapper">

<!-- Search Overlay -->
<div id="search-overlay" class="search-overlay" role="dialog" aria-label="<?php esc_attr_e('Поиск', 'kinobase'); ?>">
    <button id="search-close" class="sr-only" style="position:absolute;top:1rem;right:1rem;font-size:2rem;color:#fff;background:none;border:none;cursor:pointer;" aria-label="<?php esc_attr_e('Закрыть поиск', 'kinobase'); ?>">✕</button>
    <form class="search-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
        <input
            class="search-input"
            type="search"
            name="s"
            placeholder="<?php esc_attr_e('Найти фильм, сериал…', 'kinobase'); ?>"
            value="<?php echo esc_attr(get_search_query()); ?>"
            autocomplete="off"
        >
        <button class="search-btn" type="submit"><?php esc_html_e('Найти', 'kinobase'); ?></button>
    </form>
</div>

<!-- Site Header -->
<header class="site-header" role="banner">
    <div class="container header-inner">

        <!-- Logo + Nav -->
        <div style="display:flex;align-items:center;gap:2rem;min-width:0;">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo" aria-label="<?php bloginfo('name'); ?> — <?php esc_attr_e('На главную', 'kinobase'); ?>">
                <span class="logo-brand">KINO</span><span class="logo-name">BASE</span>
            </a>

            <nav class="nav-primary" id="nav-primary" role="navigation" aria-label="<?php esc_attr_e('Главное меню', 'kinobase'); ?>">
                <?php
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => '',
                    'items_wrap'     => '%3$s',
                    'walker'         => new Kinobase_Nav_Walker(),
                    'fallback_cb'    => 'kinobase_fallback_menu',
                ]);
                ?>
            </nav>
        </div>

        <!-- Header Actions -->
        <div class="header-actions">
            <!-- Search button -->
            <button id="search-toggle" class="icon-btn" aria-label="<?php esc_attr_e('Поиск', 'kinobase'); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
            </button>

            <!-- Settings dropdown -->
            <div class="settings-menu" id="settings-menu">
                <button class="icon-btn" aria-label="<?php esc_attr_e('Настройки', 'kinobase'); ?>" aria-haspopup="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                    </svg>
                </button>

                <div class="settings-dropdown" role="menu">
                    <?php if (is_user_logged_in()) : ?>
                        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="dropdown-item" role="menuitem">
                            <?php esc_html_e('Выйти', 'kinobase'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('profile.php')); ?>" class="dropdown-item" role="menuitem">
                            <?php esc_html_e('Профиль', 'kinobase'); ?>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="dropdown-item" role="menuitem">
                            <?php esc_html_e('Войти', 'kinobase'); ?>
                        </a>
                        <a href="<?php echo esc_url(wp_registration_url()); ?>" class="dropdown-item" role="menuitem">
                            <?php esc_html_e('Регистрация', 'kinobase'); ?>
                        </a>
                    <?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <button id="theme-toggle" class="dropdown-item" role="menuitem">
                        <?php esc_html_e('Тема', 'kinobase'); ?>
                        <span id="theme-icon" class="theme-icon">🌙</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</header>
<!-- /Site Header -->
