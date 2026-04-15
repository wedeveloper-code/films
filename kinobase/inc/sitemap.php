<?php
/**
 * KinoBase — XML Sitemap Generator
 *
 * Serves /sitemap.xml with:
 *   - Homepage         priority=1.0  changefreq=hourly
 *   - Categories       priority=0.9  changefreq=daily
 *   - Movies / Actors  priority=0.5  changefreq=biweekly
 *   - Pages            priority=0.5  changefreq=weekly
 *
 * Admin: Параметры → Карта сайта
 *
 * @package KinoBase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   Rewrite rule: /sitemap.xml
   ============================================================ */

add_action('init', 'kb_sitemap_rewrite', 5);

function kb_sitemap_rewrite(): void
{
    add_rewrite_rule('^sitemap\.xml$', 'index.php?kb_sitemap=1', 'top');
}

// Flush rewrite rules once per theme version so the sitemap rule
// is always present after a deployment (no manual Save Permalinks needed)
add_action('init', 'kb_sitemap_maybe_flush', 999);

function kb_sitemap_maybe_flush(): void
{
    $stored_ver = (string) get_option('kb_sitemap_rw_ver', '');
    if ($stored_ver !== KINOBASE_VERSION) {
        flush_rewrite_rules(false);
        update_option('kb_sitemap_rw_ver', KINOBASE_VERSION);
    }
}

add_filter('query_vars', static function (array $vars): array {
    $vars[] = 'kb_sitemap';
    return $vars;
});

// Serve XML when query var is present
add_action('template_redirect', 'kb_sitemap_serve');

function kb_sitemap_serve(): void
{
    if (!get_query_var('kb_sitemap')) {
        return;
    }

    // Regenerate if cache is empty or stale
    $xml = (string) get_option('kb_sitemap_cache', '');
    if (!$xml) {
        $xml = kb_sitemap_generate();
        update_option('kb_sitemap_cache', $xml, false);
    }

    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Robots-Tag: noindex');
    echo $xml;
    exit;
}

/* ============================================================
   Generate sitemap XML
   ============================================================ */

function kb_sitemap_generate(): string
{
    $opts          = (array) get_option('kb_sitemap_opts', []);
    $inc_movies    = !isset($opts['movies'])   || (bool) $opts['movies'];
    $inc_actors    = !isset($opts['actors'])   || (bool) $opts['actors'];
    $inc_cats      = !isset($opts['cats'])     || (bool) $opts['cats'];
    $inc_pages     = !isset($opts['pages'])    || (bool) $opts['pages'];
    $exc_page_ids  = array_filter(array_map('intval', (array) ($opts['exc_pages'] ?? [])));

    $urls = [];

    // ---- Homepage ----
    $urls[] = [
        'loc'        => home_url('/'),
        'lastmod'    => gmdate('Y-m-d'),
        'changefreq' => 'hourly',
        'priority'   => '1.0',
    ];

    // ---- Categories ----
    if ($inc_cats) {
        $cats = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => true,
            'number'     => 0,
        ]);
        if (!is_wp_error($cats)) {
            foreach ($cats as $cat) {
                $urls[] = [
                    'loc'        => get_term_link($cat),
                    'lastmod'    => gmdate('Y-m-d'),
                    'changefreq' => 'daily',
                    'priority'   => '0.9',
                ];
            }
        }
    }

    // ---- Movies (post + movie CPT) ----
    if ($inc_movies) {
        $offset = 0;
        $batch  = 500;
        do {
            $posts = get_posts([
                'post_type'      => ['post', 'movie'],
                'post_status'    => 'publish',
                'posts_per_page' => $batch,
                'offset'         => $offset,
                'no_found_rows'  => true,
                'fields'         => 'ids',
            ]);
            foreach ($posts as $id) {
                $modified = get_post_modified_time('Y-m-d', true, (int) $id);
                $urls[]   = [
                    'loc'        => get_permalink((int) $id),
                    'lastmod'    => $modified ?: gmdate('Y-m-d'),
                    'changefreq' => 'monthly',
                    'priority'   => '0.5',
                ];
            }
            $offset += $batch;
        } while (count($posts) === $batch);
    }

    // ---- Actors ----
    if ($inc_actors) {
        $offset = 0;
        $batch  = 500;
        do {
            $posts = get_posts([
                'post_type'      => 'actor',
                'post_status'    => 'publish',
                'posts_per_page' => $batch,
                'offset'         => $offset,
                'no_found_rows'  => true,
                'fields'         => 'ids',
            ]);
            foreach ($posts as $id) {
                $modified = get_post_modified_time('Y-m-d', true, (int) $id);
                $urls[]   = [
                    'loc'        => get_permalink((int) $id),
                    'lastmod'    => $modified ?: gmdate('Y-m-d'),
                    'changefreq' => 'monthly',
                    'priority'   => '0.5',
                ];
            }
            $offset += $batch;
        } while (count($posts) === $batch);
    }

    // ---- Pages ----
    if ($inc_pages) {
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            'fields'         => 'ids',
            'exclude'        => $exc_page_ids,
        ]);
        foreach ($pages as $id) {
            $modified = get_post_modified_time('Y-m-d', true, (int) $id);
            $urls[]   = [
                'loc'        => get_permalink((int) $id),
                'lastmod'    => $modified ?: gmdate('Y-m-d'),
                'changefreq' => 'weekly',
                'priority'   => '0.5',
            ];
        }
    }

    // ---- Build XML ----
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . esc_url($u['loc']) . "</loc>\n";
        $xml .= '    <lastmod>' . esc_html($u['lastmod']) . "</lastmod>\n";
        $xml .= '    <changefreq>' . esc_html($u['changefreq']) . "</changefreq>\n";
        $xml .= '    <priority>' . esc_html($u['priority']) . "</priority>\n";
        $xml .= "  </url>\n";
    }
    $xml .= '</urlset>';

    return $xml;
}

/* ============================================================
   Invalidate cache on content changes
   ============================================================ */

add_action('save_post',   'kb_sitemap_invalidate');
add_action('delete_post', 'kb_sitemap_invalidate');
add_action('created_category', 'kb_sitemap_invalidate');
add_action('edited_category',  'kb_sitemap_invalidate');
add_action('delete_category',  'kb_sitemap_invalidate');

function kb_sitemap_invalidate(): void
{
    delete_option('kb_sitemap_cache');
}

/* ============================================================
   Scheduled auto-regeneration (wp-cron)
   ============================================================ */

add_action('kb_sitemap_cron', 'kb_sitemap_regen');

function kb_sitemap_regen(): void
{
    $xml = kb_sitemap_generate();
    update_option('kb_sitemap_cache', $xml, false);
}

function kb_sitemap_schedule(string $interval): void
{
    wp_clear_scheduled_hook('kb_sitemap_cron');
    if ($interval && $interval !== 'manual') {
        wp_schedule_event(time(), $interval, 'kb_sitemap_cron');
    }
}

// Register custom cron intervals
add_filter('cron_schedules', static function (array $schedules): array {
    $schedules['biweekly'] = [
        'interval' => WEEK_IN_SECONDS * 2,
        'display'  => __('Раз в 2 недели', 'kinobase'),
    ];
    return $schedules;
});

/* ============================================================
   Admin settings page — Параметры → Карта сайта
   ============================================================ */

add_action('admin_menu', 'kb_sitemap_admin_menu');

function kb_sitemap_admin_menu(): void
{
    add_options_page(
        __('Карта сайта', 'kinobase'),
        __('Карта сайта', 'kinobase'),
        'manage_options',
        'kb_sitemap',
        'kb_sitemap_settings_page'
    );
}

function kb_sitemap_settings_page(): void
{
    if (!current_user_can('manage_options')) return;

    $notice = '';

    if (
        isset($_POST['kb_sitemap_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kb_sitemap_nonce'])), 'kb_sitemap_save')
    ) {
        // "Обновить сейчас"
        if (!empty($_POST['kb_sitemap_now'])) {
            $xml = kb_sitemap_generate();
            update_option('kb_sitemap_cache', $xml, false);
            $notice = 'regenerated';
        } else {
            $interval = sanitize_text_field(wp_unslash($_POST['kb_sitemap_interval'] ?? 'manual'));
            $opts = [
                'movies'    => !empty($_POST['kb_sitemap_movies']),
                'actors'    => !empty($_POST['kb_sitemap_actors']),
                'cats'      => !empty($_POST['kb_sitemap_cats']),
                'pages'     => !empty($_POST['kb_sitemap_pages']),
                'interval'  => $interval,
                'exc_pages' => array_filter(array_map('intval',
                    explode(',', sanitize_text_field(wp_unslash($_POST['kb_sitemap_exc_pages'] ?? '')))
                )),
            ];
            update_option('kb_sitemap_opts', $opts);
            kb_sitemap_schedule($interval);
            // Invalidate so the next request regenerates
            delete_option('kb_sitemap_cache');
            $notice = 'saved';
        }
    }

    $opts     = (array) get_option('kb_sitemap_opts', []);
    $interval = $opts['interval'] ?? 'daily';
    $exc_ids  = implode(', ', (array) ($opts['exc_pages'] ?? []));

    $sitemap_url = home_url('/sitemap.xml');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Карта сайта (sitemap.xml)', 'kinobase'); ?></h1>

        <p>
            <?php esc_html_e('URL карты сайта:', 'kinobase'); ?>
            <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank">
                <?php echo esc_html($sitemap_url); ?>
            </a>
        </p>

        <?php if ($notice === 'saved') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Настройки сохранены. Карта сайта будет пересоздана при следующем запросе.', 'kinobase'); ?></p></div>
        <?php elseif ($notice === 'regenerated') : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Карта сайта успешно обновлена.', 'kinobase'); ?></p></div>
        <?php endif; ?>

        <form method="post" style="max-width:680px;margin-top:1rem">
            <?php wp_nonce_field('kb_sitemap_save', 'kb_sitemap_nonce'); ?>

            <h2 style="font-size:1rem"><?php esc_html_e('Включить в карту сайта', 'kinobase'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Типы контента', 'kinobase'); ?></th>
                    <td>
                        <?php foreach ([
                            'kb_sitemap_movies' => __('Фильмы (записи и CPT movie)', 'kinobase'),
                            'kb_sitemap_actors' => __('Актёры', 'kinobase'),
                            'kb_sitemap_cats'   => __('Рубрики', 'kinobase'),
                            'kb_sitemap_pages'  => __('Страницы', 'kinobase'),
                        ] as $field => $label) :
                            $key = str_replace('kb_sitemap_', '', $field);
                            $checked = !isset($opts[$key]) || $opts[$key];
                        ?>
                        <label style="display:block;margin-bottom:.35rem">
                            <input type="checkbox" name="<?php echo esc_attr($field); ?>" value="1"
                                <?php checked($checked); ?>>
                            <?php echo esc_html($label); ?>
                        </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="kb_sitemap_exc_pages"><?php esc_html_e('Исключить страницы (ID)', 'kinobase'); ?></label></th>
                    <td>
                        <input type="text" id="kb_sitemap_exc_pages" name="kb_sitemap_exc_pages"
                               value="<?php echo esc_attr($exc_ids); ?>" class="regular-text">
                        <p class="description"><?php esc_html_e('ID страниц через запятую (например: 2, 15, 42)', 'kinobase'); ?></p>
                    </td>
                </tr>
            </table>

            <h2 style="font-size:1rem;margin-top:1.5rem"><?php esc_html_e('Автоматическое обновление', 'kinobase'); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="kb_sitemap_interval"><?php esc_html_e('Интервал обновления', 'kinobase'); ?></label></th>
                    <td>
                        <select id="kb_sitemap_interval" name="kb_sitemap_interval">
                            <?php foreach ([
                                'manual'   => __('Только вручную', 'kinobase'),
                                'hourly'   => __('Каждый час', 'kinobase'),
                                'twicedaily' => __('Два раза в день', 'kinobase'),
                                'daily'    => __('Каждый день', 'kinobase'),
                                'weekly'   => __('Каждую неделю', 'kinobase'),
                                'biweekly' => __('Раз в 2 недели', 'kinobase'),
                            ] as $val => $label) : ?>
                            <option value="<?php echo esc_attr($val); ?>"
                                <?php selected($interval, $val); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <div style="display:flex;gap:1rem;margin-top:1.5rem;align-items:center">
                <?php submit_button(__('Сохранить настройки', 'kinobase'), 'primary', 'kb_sitemap_submit', false); ?>
                <button type="submit" name="kb_sitemap_now" value="1" class="button button-secondary">
                    <?php esc_html_e('Обновить сейчас', 'kinobase'); ?>
                </button>
            </div>
        </form>
    </div>
    <?php
}
