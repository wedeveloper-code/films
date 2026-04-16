<?php
/**
 * FastWP — Movie Rotation
 *
 * Randomises the display order of films on the home page and category archives
 * using a stable seed that refreshes at a configurable interval.
 *
 * All visitors see the same randomised order within the current rotation window.
 * Pagination works correctly because RAND(seed) is deterministic for the
 * duration of the transient.
 *
 * Settings: Параметры → Ротация фильмов
 *
 * @package FastWP
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   Settings page — Параметры → Ротация фильмов
   ============================================================ */

add_action('admin_menu', 'kb_rotation_admin_menu');

function kb_rotation_admin_menu(): void
{
    add_options_page(
        __('Ротация фильмов', 'fastwp'),
        __('Ротация фильмов', 'fastwp'),
        'manage_options',
        'kb_rotation',
        'kb_rotation_settings_page'
    );
}

add_action('admin_init', 'kb_rotation_register_settings');

function kb_rotation_register_settings(): void
{
    register_setting('kb_rotation_group', 'kb_rotation_enabled', [
        'sanitize_callback' => 'intval',
        'default'           => 0,
    ]);
    register_setting('kb_rotation_group', 'kb_rotation_interval', [
        'sanitize_callback' => static function ($v): int {
            $v = absint($v);
            return max(1, min(1440, $v)); // 1 min – 24 h
        },
        'default' => 20,
    ]);
}

function kb_rotation_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle "Перемешать сейчас"
    $reset_done = false;
    if (
        isset($_POST['kb_rotation_reset']) &&
        check_admin_referer('kb_rotation_reset_nonce', 'kb_rotation_reset_nonce_field')
    ) {
        delete_transient('kb_rotation_seed');
        $reset_done = true;
    }

    $enabled  = (int) get_option('kb_rotation_enabled', 0);
    $interval = (int) get_option('kb_rotation_interval', 20);

    // Next rotation countdown
    $next_in = kb_rotation_next_in_seconds();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Ротация фильмов', 'fastwp'); ?></h1>

        <p style="max-width:640px;color:#555;margin-bottom:1.5rem">
            <?php esc_html_e(
                'Ротация автоматически перемешивает порядок отображения фильмов на главной странице и в рубриках. '
                . 'Все посетители видят одинаковый порядок, который обновляется через указанный интервал.',
                'fastwp'
            ); ?>
        </p>

        <?php if ($reset_done) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Ротация выполнена — фильмы перемешаны.', 'fastwp'); ?></p>
        </div>
        <?php endif; ?>

        <?php settings_errors('kb_rotation_group'); ?>

        <!-- Status card -->
        <?php if ($enabled) : ?>
        <div style="display:inline-flex;align-items:center;gap:1rem;background:#f0f6fc;border:1px solid #c3d9ed;border-radius:6px;padding:0.75rem 1.25rem;margin-bottom:1.5rem">
            <?php if ($next_in !== null && $next_in > 0) :
                $next_min = ceil($next_in / 60);
            ?>
            <span style="color:#0073aa;font-size:1.5rem;font-weight:700"><?php echo (int) $next_min; ?></span>
            <span style="color:#555">
                <?php printf(
                    esc_html(_n('минута до следующей ротации', 'минут до следующей ротации', (int) $next_min, 'fastwp')),
                );?>
            </span>
            <?php else : ?>
            <span style="color:#888"><?php esc_html_e('Ротация произойдёт при следующем запросе страницы.', 'fastwp'); ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Main settings form -->
        <form method="post" action="options.php">
            <?php settings_fields('kb_rotation_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Включить ротацию', 'fastwp'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="kb_rotation_enabled" value="1"
                                   <?php checked($enabled, 1); ?>>
                            <?php esc_html_e('Перемешивать фильмы на главной и в рубриках', 'fastwp'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="kb_rotation_interval"><?php esc_html_e('Интервал обновления', 'fastwp'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="kb_rotation_interval" name="kb_rotation_interval"
                               value="<?php echo esc_attr((string) $interval); ?>"
                               min="1" max="1440" step="1" style="width:90px">
                        <span><?php esc_html_e('минут', 'fastwp'); ?></span>
                        <p class="description">
                            <?php esc_html_e('Минимум 1 мин., максимум 1440 мин. (24 ч.). После изменения интервала нажмите «Сохранить» — ротация произойдёт при следующем истечении таймера.', 'fastwp'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Сохранить', 'fastwp')); ?>
        </form>

        <hr style="margin:1.5rem 0">

        <!-- Manual reset form -->
        <h2 style="font-size:1rem;margin-bottom:0.5rem">
            <?php esc_html_e('Ручное перемешивание', 'fastwp'); ?>
        </h2>
        <p style="color:#555;margin-bottom:0.75rem">
            <?php esc_html_e('Немедленно перемешать фильмы, не дожидаясь истечения таймера.', 'fastwp'); ?>
        </p>
        <form method="post">
            <?php wp_nonce_field('kb_rotation_reset_nonce', 'kb_rotation_reset_nonce_field'); ?>
            <input type="hidden" name="kb_rotation_reset" value="1">
            <?php submit_button(__('Перемешать сейчас', 'fastwp'), 'secondary'); ?>
        </form>

    </div>
    <?php
}

/* ============================================================
   Seed management
   ============================================================ */

/**
 * Returns the current stable random seed.
 * Creates a new seed (and starts the timer) if none exists.
 */
function kb_rotation_seed(): int
{
    $seed = get_transient('kb_rotation_seed');

    if ($seed === false) {
        $seed     = wp_rand(1, 999983); // prime-ish upper bound for better distribution
        $interval = max(1, (int) get_option('kb_rotation_interval', 20));
        set_transient('kb_rotation_seed', $seed, $interval * MINUTE_IN_SECONDS);
    }

    return (int) $seed;
}

/**
 * Returns seconds until the next rotation, or null if no active seed.
 */
function kb_rotation_next_in_seconds(): ?int
{
    global $wpdb;
    $timeout = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
            '_transient_timeout_kb_rotation_seed'
        )
    );

    if (!$timeout) {
        return null;
    }

    $remaining = (int) $timeout - time();
    return $remaining > 0 ? $remaining : null;
}

/* ============================================================
   Apply rotation to queries via posts_orderby filter
   ============================================================ */

add_filter('posts_orderby', 'kb_rotation_posts_orderby', 10, 2);

function kb_rotation_posts_orderby(string $orderby, WP_Query $q): string
{
    // Only when rotation is enabled
    if (!get_option('kb_rotation_enabled', 0)) {
        return $orderby;
    }

    // Never in admin
    if (is_admin()) {
        return $orderby;
    }

    // Apply if:
    // a) query has the kb_rotate flag (front-page.php custom queries)
    // b) it's the main category archive query
    $should_rotate = (bool) $q->get('kb_rotate')
        || ($q->is_main_query() && ($q->is_category() || $q->is_tag()));

    if (!$should_rotate) {
        return $orderby;
    }

    return 'RAND(' . kb_rotation_seed() . ')';
}

/* ============================================================
   Category archive: clear default date ordering from main query
   so RAND(seed) takes full effect without interference
   ============================================================ */

add_action('pre_get_posts', 'kb_rotation_pre_get_posts');

function kb_rotation_pre_get_posts(WP_Query $q): void
{
    if (is_admin() || !$q->is_main_query()) {
        return;
    }
    if (!get_option('kb_rotation_enabled', 0)) {
        return;
    }
    if (!($q->is_category() || $q->is_tag())) {
        return;
    }

    // Remove the default date order — posts_orderby will set RAND(seed)
    $q->set('orderby', 'none');
    $q->set('order', '');
}
