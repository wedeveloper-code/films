<?php
/**
 * FastWP Theme Setup
 *
 * @package FastWP
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', 'fastwp_setup');

function fastwp_setup(): void
{
    // Localization
    load_theme_textdomain('fastwp', FASTWP_DIR . '/languages');

    // HTML5 support
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);

    // Title tag
    add_theme_support('title-tag');

    // Post thumbnails
    add_theme_support('post-thumbnails');

    // Movie poster: 2:3 ratio
    add_image_size('movie-poster', 300, 450, true);
    add_image_size('movie-poster-sm', 150, 225, true);

    // Menus
    register_nav_menus([
        'primary'          => __('Верхнее меню (рубрики)', 'fastwp'),
        'fastwp_filters' => __('Меню фильтров (Год, Жанр, Качество…)', 'fastwp'),
        'footer'           => __('Меню подвала', 'fastwp'),
    ]);

    // Wide alignment
    add_theme_support('align-wide');

    // Responsive embeds
    add_theme_support('responsive-embeds');
}

// Cache total movie count
add_action('save_post', 'fastwp_invalidate_movie_count');
add_action('delete_post', 'fastwp_invalidate_movie_count');

function fastwp_invalidate_movie_count(): void
{
    delete_transient('fastwp_movie_count');
}

/* ============================================================
   One-time migration: copy theme_mods_kinobase → theme_mods_fastwp
   Needed because renaming the theme folder changes the option key
   WordPress uses to store Customizer settings and nav menu locations.
   v2: uses $wpdb directly for reliability, bypasses object cache.
   ============================================================ */

add_action('init', 'fastwp_maybe_migrate_theme_mods', 1);

function fastwp_maybe_migrate_theme_mods(): void
{
    if (get_option('fastwp_mods_migrated_v2')) {
        return;
    }

    global $wpdb;

    // Read old mods directly from DB (bypass object cache)
    $raw = $wpdb->get_var(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'theme_mods_kinobase' LIMIT 1"
    );
    $old_mods = $raw ? maybe_unserialize($raw) : [];

    if (!empty($old_mods) && is_array($old_mods)) {
        // Read current fastwp mods directly from DB
        $raw_new  = $wpdb->get_var(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'theme_mods_fastwp' LIMIT 1"
        );
        $new_mods = ($raw_new ? maybe_unserialize($raw_new) : []) ?: [];
        if (!is_array($new_mods)) {
            $new_mods = [];
        }

        // Copy all keys not already present in the new mods
        foreach ($old_mods as $key => $val) {
            if (!isset($new_mods[$key])) {
                $new_mods[$key] = $val;
            }
        }

        // Rename nav_menu_locations key: kinobase_filters → fastwp_filters
        if (isset($new_mods['nav_menu_locations']['kinobase_filters'])) {
            $new_mods['nav_menu_locations']['fastwp_filters'] =
                $new_mods['nav_menu_locations']['kinobase_filters'];
            unset($new_mods['nav_menu_locations']['kinobase_filters']);
        }

        // Write directly to DB, then clear object + theme-mod caches
        $wpdb->replace(
            $wpdb->options,
            ['option_name' => 'theme_mods_fastwp', 'option_value' => maybe_serialize($new_mods), 'autoload' => 'yes']
        );
        wp_cache_delete('theme_mods_fastwp', 'options');
        wp_cache_delete('alloptions', 'options');
        // Clear WP's internal theme-mod cache
        unset($GLOBALS['_wp_theme_mods']);
    }

    update_option('fastwp_mods_migrated_v2', '1');
}

/**
 * Get cached total movie count
 */
function fastwp_get_movie_count(): int
{
    $count = get_transient('fastwp_movie_count');
    if ($count === false) {
        $posts  = (int) (wp_count_posts('post')->publish ?? 0);
        $movies = (int) (wp_count_posts('movie')->publish ?? 0);
        $count  = $posts + $movies;
        set_transient('fastwp_movie_count', $count, HOUR_IN_SECONDS * 6);
    }
    return (int) $count;
}

// Invalidate count when movie CPT posts change too
add_action('save_post_movie', 'fastwp_invalidate_movie_count');
add_action('delete_post',     'fastwp_invalidate_movie_count');

// Remove category prefix from archive title
add_filter('get_the_archive_title', function (string $title): string {
    if (is_category()) {
        return single_cat_title('', false);
    }
    return $title;
});

// Reduce review rate-limit flood: our ajax handler already limits to 1/hour.
// This disables WordPress's own 15-second flood check for wp_insert_comment calls.
add_filter('comment_flood_filter', '__return_false');

/* ============================================================
   Canonical URL tag for all public pages
   ============================================================ */

add_action('wp_head', 'fastwp_canonical_tag', 1);

function fastwp_canonical_tag(): void
{
    $canonical = '';
    $paged     = max((int) get_query_var('paged'), (int) get_query_var('page'));

    if (is_front_page()) {
        $canonical = $paged > 1 ? get_pagenum_link($paged) : home_url('/');
    } elseif (is_singular()) {
        $canonical = (string) get_permalink();
    } elseif (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $base      = (string) get_term_link($term);
            $canonical = $paged > 1 ? get_pagenum_link($paged) : $base;
        }
    } elseif (is_post_type_archive()) {
        $base      = (string) get_post_type_archive_link((string) get_post_type());
        $canonical = $paged > 1 ? get_pagenum_link($paged) : $base;
    } elseif (is_home()) {
        $canonical = $paged > 1 ? get_pagenum_link($paged) : home_url('/');
    }

    if ($canonical) {
        echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
    }
}

/* ============================================================
   Pagination: append "— страница N" to document title

   Two hooks needed:
   1. document_title_parts  — for WP-generated titles (runs when
      pre_get_document_title returns empty string).
   2. pre_get_document_title at priority 999 — for custom titles
      set by SEO hooks (e.g. kb_cat_seo_title). Only appends when
      the title is already non-empty.
   ============================================================ */

add_filter('document_title_parts', 'fastwp_paged_title_parts', 10);

function fastwp_paged_title_parts(array $parts): array
{
    $paged = max((int) get_query_var('paged'), (int) get_query_var('page'));
    if ($paged > 1 && !empty($parts['title'])) {
        $parts['title'] = rtrim($parts['title']) . ' — страница ' . $paged;
        unset($parts['page']); // Remove WP's default "Страница N" to avoid duplication
    }
    return $parts;
}

add_filter('pre_get_document_title', 'fastwp_paged_title_suffix', 999);

function fastwp_paged_title_suffix(string $title): string
{
    if (empty($title)) {
        return $title; // Let document_title_parts handle WP-generated titles
    }
    $paged = max((int) get_query_var('paged'), (int) get_query_var('page'));
    if ($paged > 1) {
        $title = rtrim($title) . ' — страница ' . $paged;
    }
    return $title;
}

/* ============================================================
   Admin settings: Theme defaults + time-based auto-switch
   ============================================================ */

add_action('admin_menu', 'fastwp_theme_settings_menu');

function fastwp_theme_settings_menu(): void
{
    add_options_page(
        __('Настройки темы', 'fastwp'),
        __('Настройки темы', 'fastwp'),
        'manage_options',
        'fastwp_theme',
        'fastwp_theme_settings_page'
    );
}

function fastwp_theme_settings_page(): void
{
    if (!current_user_can('manage_options')) return;

    if (
        isset($_POST['kb_theme_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kb_theme_nonce'])), 'kb_theme_save')
    ) {
        update_option('kb_default_theme',    sanitize_text_field(wp_unslash($_POST['kb_default_theme']    ?? 'dark')));
        update_option('kb_theme_auto_time',  !empty($_POST['kb_theme_auto_time']) ? '1' : '0');
        update_option('kb_theme_dark_from',  max(0, min(23, (int) ($_POST['kb_theme_dark_from']  ?? 20))));
        update_option('kb_theme_light_from', max(0, min(23, (int) ($_POST['kb_theme_light_from'] ?? 8))));
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Сохранено.', 'fastwp') . '</p></div>';
    }

    $default    = (string) get_option('kb_default_theme',    'dark');
    $auto       = (bool)   get_option('kb_theme_auto_time',  '0');
    $dark_from  = (int)    get_option('kb_theme_dark_from',  20);
    $light_from = (int)    get_option('kb_theme_light_from', 8);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Настройки темы', 'fastwp'); ?></h1>
        <p style="max-width:600px;color:#555">
            <?php esc_html_e(
                'Выберите тему по умолчанию для новых посетителей. '
                . 'Пользователи могут вручную переключить тему через меню — их выбор сохраняется в localStorage.',
                'fastwp'
            ); ?>
        </p>
        <form method="post">
            <?php wp_nonce_field('kb_theme_save', 'kb_theme_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Тема по умолчанию', 'fastwp'); ?></th>
                    <td>
                        <label style="margin-right:1.5rem">
                            <input type="radio" name="kb_default_theme" value="dark"
                                <?php checked($default, 'dark'); ?>>
                            <?php esc_html_e('Тёмная', 'fastwp'); ?>
                        </label>
                        <label>
                            <input type="radio" name="kb_default_theme" value="light"
                                <?php checked($default, 'light'); ?>>
                            <?php esc_html_e('Светлая', 'fastwp'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Автопереключение по времени', 'fastwp'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kb_theme_auto_time" value="1"
                                <?php checked($auto); ?>>
                            <?php esc_html_e('Переключать тему автоматически по расписанию', 'fastwp'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Если включено — настройки ниже определяют, когда включается тёмная/светлая тема. Ручной выбор пользователя отключает авторежим до перезагрузки страницы.', 'fastwp'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Тёмная тема с (ч.)', 'fastwp'); ?></th>
                    <td>
                        <input type="number" name="kb_theme_dark_from" value="<?php echo (int) $dark_from; ?>"
                               min="0" max="23" style="width:80px">
                        <span class="description"><?php esc_html_e('Час суток (0–23), с которого включается тёмная тема', 'fastwp'); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Светлая тема с (ч.)', 'fastwp'); ?></th>
                    <td>
                        <input type="number" name="kb_theme_light_from" value="<?php echo (int) $light_from; ?>"
                               min="0" max="23" style="width:80px">
                        <span class="description"><?php esc_html_e('Час суток (0–23), с которого включается светлая тема', 'fastwp'); ?></span>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Сохранить', 'fastwp')); ?>
        </form>
    </div>
    <?php
}

// Pass theme settings to JS
add_filter('fastwp_js_data', 'fastwp_theme_js_data');

function fastwp_theme_js_data(array $data): array
{
    $data['defaultTheme']  = (string) get_option('kb_default_theme',    'dark');
    $data['themeAutoTime'] = (bool)   get_option('kb_theme_auto_time',  '0');
    $data['themeDarkFrom'] = (int)    get_option('kb_theme_dark_from',  20);
    $data['themeLightFrom']= (int)    get_option('kb_theme_light_from', 8);
    return $data;
}

/* ============================================================
   Customizer: footer copyright text + homepage bottom text
   ============================================================ */
add_action('customize_register', 'fastwp_customizer_register');

function fastwp_customizer_register(WP_Customize_Manager $wp_customize): void
{
    // --- Footer ---
    $wp_customize->add_section('fastwp_footer', [
        'title'    => __('Подвал сайта', 'fastwp'),
        'priority' => 120,
    ]);

    $wp_customize->add_setting('fastwp_footer_copyright', [
        'default'           => '',
        'sanitize_callback' => 'wp_kses_post',
        'transport'         => 'refresh',
    ]);

    $wp_customize->add_control('fastwp_footer_copyright', [
        'label'       => __('Текст авторского права (подвал)', 'fastwp'),
        'description' => __('Оставьте пустым для использования стандартного текста.', 'fastwp'),
        'section'     => 'fastwp_footer',
        'type'        => 'textarea',
    ]);

    // --- Homepage bottom text ---
    $wp_customize->add_section('fastwp_homepage', [
        'title'    => __('Главная страница', 'fastwp'),
        'priority' => 110,
    ]);

    $wp_customize->add_setting('fastwp_home_bottom_text', [
        'default'           => '',
        'sanitize_callback' => 'wp_kses_post',
        'transport'         => 'refresh',
    ]);

    $wp_customize->add_control('fastwp_home_bottom_text', [
        'label'       => __('Текст внизу главной страницы', 'fastwp'),
        'description' => __('Отображается после блоков с фильмами, перед подвалом. Поддерживает HTML.', 'fastwp'),
        'section'     => 'fastwp_homepage',
        'type'        => 'textarea',
    ]);
}

// Add body classes for theme
add_filter('body_class', function (array $classes): array {
    $classes[] = 'fastwp';
    return $classes;
});

/**
 * Format price for display on movie cards.
 * Defined here (not in template) to avoid redeclaration on each card.
 */
function kb_price(int $p): string
{
    return $p > 0 ? number_format($p) . '₽' : '—';
}

/**
 * Fallback nav menu — outputs category links when no menu is assigned.
 * Defined here (not in header.php) to prevent redeclaration if template is loaded twice.
 */
function fastwp_fallback_menu(): void
{
    $cats = [
        ['slug' => 'films',  'name' => 'Фильмы'],
        ['slug' => 'series', 'name' => 'Сериалы'],
        ['slug' => 'tv',     'name' => 'Телепередачи'],
        ['slug' => 'new',    'name' => 'Новинки'],
    ];
    foreach ($cats as $c) {
        $cat = get_category_by_slug($c['slug'])
            ?: get_term_by('name', $c['name'], 'category');
        $url = $cat ? get_category_link($cat->term_id) : home_url('/');
        echo '<a href="' . esc_url($url) . '">' . esc_html(__($c['name'], 'fastwp')) . '</a>';
    }
}

/**
 * Mobile fallback nav — outputs <li><a> links for mobile drawer.
 */
function fastwp_mobile_fallback_menu(): void
{
    $cats = [
        ['slug' => 'films',  'name' => 'Фильмы'],
        ['slug' => 'series', 'name' => 'Сериалы'],
        ['slug' => 'tv',     'name' => 'Телепередачи'],
        ['slug' => 'new',    'name' => 'Новинки'],
    ];
    echo '<ul class="mobile-nav-list">';
    foreach ($cats as $c) {
        $cat = get_category_by_slug($c['slug'])
            ?: get_term_by('name', $c['name'], 'category');
        $url = $cat ? get_category_link($cat->term_id) : home_url('/');
        echo '<li><a href="' . esc_url($url) . '">' . esc_html(__($c['name'], 'fastwp')) . '</a></li>';
    }
    echo '</ul>';
}

/**
 * Simple nav walker — outputs bare <a> links without extra <li> wrappers.
 * Defined here (not in header.php) to prevent redeclaration if template is loaded twice.
 */
class FastWP_Nav_Walker extends Walker_Nav_Menu
{
    public function start_el(&$output, $data_object, $depth = 0, $args = null, $id = 0): void
    {
        $item   = $data_object;
        $class  = in_array('current-menu-item', (array) $item->classes, true) ? 'current-menu-item' : '';
        $output .= '<a href="' . esc_url($item->url) . '"'
            . ($class ? ' class="' . esc_attr($class) . '"' : '') . '>'
            . esc_html($item->title) . '</a>';
    }
}

/**
 * Custom post type: Contact Messages (kb_message)
 * Visible only in admin, stores contact form submissions.
 */
add_action('init', 'fastwp_register_message_cpt');

function fastwp_register_message_cpt(): void
{
    register_post_type('kb_message', [
        'labels' => [
            'name'               => 'Сообщения',
            'singular_name'      => 'Сообщение',
            'menu_name'          => 'Сообщения',
            'all_items'          => 'Все сообщения',
            'view_item'          => 'Просмотр сообщения',
            'search_items'       => 'Поиск сообщений',
            'not_found'          => 'Сообщений нет',
            'not_found_in_trash' => 'Корзина пуста',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-email-alt',
        'menu_position' => 25,
        'supports'      => ['title', 'editor'],
        'capabilities'  => ['create_posts' => 'do_not_allow'],
        'map_meta_cap'  => true,
    ]);
}

// Admin columns: Email + Date received
add_filter('manage_kb_message_posts_columns', 'fastwp_message_columns');

function fastwp_message_columns(array $cols): array
{
    return [
        'cb'              => $cols['cb'],
        'title'           => 'Отправитель',
        'kb_email'        => 'Email',
        'kb_msg'          => 'Сообщение',
        'date'            => 'Дата',
    ];
}

add_action('manage_kb_message_posts_custom_column', 'fastwp_message_column_data', 10, 2);

function fastwp_message_column_data(string $column, int $post_id): void
{
    if ($column === 'kb_email') {
        $email = get_post_meta($post_id, 'contact_email', true);
        echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
    }
    if ($column === 'kb_msg') {
        echo esc_html(wp_trim_words(get_post_field('post_content', $post_id), 12));
    }
}

/**
 * Recalculate movie_rating from approved review comments.
 * Fires when a comment status changes (approve / unapprove / trash / spam).
 */
add_action('transition_comment_status', 'fastwp_recalculate_movie_rating', 10, 3);

function fastwp_recalculate_movie_rating(string $new_status, string $old_status, WP_Comment $comment): void
{
    $post_id   = (int) $comment->comment_post_ID;
    $post_type = get_post_type($post_id);
    if (!$post_id || !in_array($post_type, ['post', 'movie'], true)) return;

    $approved_comments = get_comments([
        'post_id' => $post_id,
        'status'  => 'approve',
        'type'    => 'comment',
        'number'  => 0, // all
    ]);

    $ratings = [];
    foreach ($approved_comments as $c) {
        $r = (int) get_comment_meta((int) $c->comment_ID, 'review_rating', true);
        if ($r > 0) {
            $ratings[] = $r;
        }
    }

    if (!empty($ratings)) {
        $avg = array_sum($ratings) / count($ratings);
        update_post_meta($post_id, 'movie_rating', round($avg, 1));
    }
}

// Homepage year filter: /{year}/ and /{year}/page/{n}/
add_action('init', 'fastwp_register_year_rewrites');
add_action('after_switch_theme', static function (): void {
    fastwp_register_year_rewrites();
    flush_rewrite_rules();
});

function fastwp_register_year_rewrites(): void
{
    add_rewrite_rule('^([0-9]{4})/page/([0-9]+)/?$',
        'index.php?kb_home_year=$matches[1]&paged=$matches[2]', 'top');
    add_rewrite_rule('^([0-9]{4})/?$',
        'index.php?kb_home_year=$matches[1]', 'top');
}

add_filter('query_vars', 'fastwp_query_vars');

function fastwp_query_vars(array $vars): array
{
    $vars[] = 'kb_home_year';
    return $vars;
}

// Use front-page.php template for /{year}/ URLs
add_filter('template_include', 'fastwp_year_home_template');

function fastwp_year_home_template(string $template): string
{
    if (get_query_var('kb_home_year')) {
        $t = locate_template('front-page.php');
        if ($t) return $t;
    }
    return $template;
}


/**
 * Find the parent category for years (tries multiple slug/name variants).
 */
function fastwp_get_year_parent(): ?WP_Term
{
    static $cache = false;
    if ($cache !== false) return $cache ?: null;
    $t = get_term_by('slug', 'год', 'category')
      ?: get_term_by('slug', 'god', 'category')
      ?: get_term_by('slug', 'gody', 'category')
      ?: get_term_by('slug', 'years', 'category')
      ?: get_term_by('name', 'Год', 'category')
      ?: get_term_by('name', 'Годы', 'category')
      ?: get_term_by('name', 'Год выпуска', 'category');
    $cache = ($t && !is_wp_error($t)) ? $t : null;
    return $cache;
}

/**
 * Find the parent category for genres (tries multiple slug/name variants).
 */
function fastwp_get_genre_parent(): ?WP_Term
{
    static $cache = false;
    if ($cache !== false) return $cache ?: null;
    $t = get_term_by('slug', 'жанры', 'category')
      ?: get_term_by('slug', 'zhanry', 'category')
      ?: get_term_by('slug', 'genres', 'category')
      ?: get_term_by('name', 'Жанры', 'category')
      ?: get_term_by('name', 'Жанр', 'category');
    $cache = ($t && !is_wp_error($t)) ? $t : null;
    return $cache;
}

/**
 * Build a filtered category URL.
 * Pattern: /category/{main}/{year}/genres/{genre}/
 */
function fastwp_filter_url(string $main_slug, string $year = '', string $genre = ''): string
{
    $url = home_url('/category/' . $main_slug . '/');
    if ($year)  $url .= $year . '/';
    if ($genre) $url .= 'genres/' . $genre . '/';
    return $url;
}


/* ============================================================
   SEO: custom title and meta description
   ============================================================ */

/**
 * Replace template variables in a SEO string.
 */
function fastwp_replace_seo_vars(string $tpl, int $post_id): string
{
    $post = get_post($post_id);
    if (!$post) return $tpl;

    $year = $genre = $quality_cat = '';
    foreach (get_the_category($post_id) as $cat) {
        if (!$cat->parent) continue;
        $parent = get_term((int) $cat->parent, 'category');
        if (!$parent || is_wp_error($parent)) continue;
        $pname = mb_strtolower($parent->name);
        if (in_array($pname, ['год', 'year', 'годы'], true) && !$year)         $year        = $cat->name;
        if (in_array($pname, ['жанры', 'жанр', 'genres', 'genre'], true) && !$genre)  $genre       = $cat->name;
        if (in_array($pname, ['качество', 'quality'], true) && !$quality_cat)  $quality_cat = $cat->name;
    }

    $vars = [
        '%название%'     => $post->post_title,
        '%год%'          => $year,
        '%жанр%'         => $genre,
        '%качество%'     => $quality_cat ?: (string) get_post_meta($post_id, 'movie_quality', true),
        '%длительность%' => (string) get_post_meta($post_id, 'movie_duration', true),
        '%сайт%'         => get_bloginfo('name'),
    ];

    return str_replace(array_keys($vars), array_values($vars), $tpl);
}

// Custom SEO title
add_filter('pre_get_document_title', 'fastwp_seo_title');

function fastwp_seo_title(string $title): string
{
    if (!is_singular(['post', 'movie'])) return $title;
    $custom = (string) get_post_meta(get_the_ID(), '_kb_seo_title', true);
    if (!$custom) return $title;
    return fastwp_replace_seo_vars($custom, get_the_ID());
}

// Custom meta description
add_action('wp_head', 'fastwp_seo_description', 1);

function fastwp_seo_description(): void
{
    if (!is_singular(['post', 'movie'])) return;
    $desc = (string) get_post_meta(get_the_ID(), '_kb_seo_description', true);
    if (!$desc) return;
    $desc = fastwp_replace_seo_vars($desc, get_the_ID());
    echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
}

/* ============================================================
   Transliteration: auto-convert Cyrillic slugs for movie/actor
   ============================================================ */

function fastwp_do_transliterate(string $text): string
{
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo',
        'ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
        'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u',
        'ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];
    $lower  = mb_strtolower($text);
    $result = '';
    foreach (mb_str_split($lower) as $char) {
        $result .= $map[$char] ?? $char;
    }
    $result = preg_replace('/[^a-z0-9]+/', '-', $result) ?? '';
    return trim($result, '-');
}

add_action('save_post_movie', 'fastwp_ensure_latin_slug', 20, 2);
add_action('save_post_actor', 'fastwp_ensure_latin_slug', 20, 2);

function fastwp_ensure_latin_slug(int $post_id, WP_Post $post): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if ($post->post_status === 'auto-draft') return;

    $slug    = $post->post_name;
    $decoded = rawurldecode($slug);

    // Skip if no Cyrillic
    if (!preg_match('/[а-яёА-ЯЁ]/u', $decoded)) return;

    $new_slug = fastwp_do_transliterate($decoded);
    if (!$new_slug || $new_slug === $slug) return;

    // Avoid infinite loop
    remove_action('save_post_movie', 'fastwp_ensure_latin_slug', 20);
    remove_action('save_post_actor', 'fastwp_ensure_latin_slug', 20);

    wp_update_post(['ID' => $post_id, 'post_name' => $new_slug]);

    add_action('save_post_movie', 'fastwp_ensure_latin_slug', 20, 2);
    add_action('save_post_actor', 'fastwp_ensure_latin_slug', 20, 2);
}
