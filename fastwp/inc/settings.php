<?php
/**
 * FastWP — General Settings
 *
 * Provides an admin page at Параметры → Настройки URL for changing
 * the movie post type URL slug (default: "film").
 *
 * @package FastWP
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   Admin menu + settings registration
   ============================================================ */

add_action('admin_menu', 'fastwp_url_settings_menu');

function fastwp_url_settings_menu(): void
{
    add_options_page(
        __('Настройки URL', 'fastwp'),
        __('Настройки URL', 'fastwp'),
        'manage_options',
        'fastwp_url_settings',
        'fastwp_url_settings_page'
    );
}

add_action('admin_init', 'fastwp_url_settings_register');

function fastwp_url_settings_register(): void
{
    register_setting('fastwp_url_group', 'fastwp_movie_slug', [
        // sanitize_key allows only a-z, 0-9, _, - and rejects Cyrillic/special chars.
        // Falls back to 'film' if the result is empty.
        'sanitize_callback' => static function (string $value): string {
            $slug = sanitize_key(trim($value));
            if ($slug === '') {
                add_settings_error(
                    'fastwp_url_group',
                    'invalid_slug',
                    __('Слаг должен содержать только строчные латинские буквы, цифры и дефис. Значение сброшено на «film».', 'fastwp')
                );
                return 'film';
            }
            return $slug;
        },
        'default' => 'film',
    ]);
}

/* ============================================================
   Flush rewrite rules when slug changes.
   Must re-register post types first so flush captures the new slug.
   ============================================================ */

add_action('update_option_fastwp_movie_slug', 'fastwp_flush_on_movie_slug_change', 10, 2);

function fastwp_flush_on_movie_slug_change(mixed $old, mixed $new): void
{
    if ((string) $old === (string) $new) {
        return;
    }
    // Re-register CPTs with the updated slug so flush_rewrite_rules()
    // writes rules for the new slug, not the old one still in memory.
    fastwp_register_post_types();
    flush_rewrite_rules();
}

/* ============================================================
   Settings page
   ============================================================ */

function fastwp_url_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $current_slug = (string) get_option('fastwp_movie_slug', 'film');
    $home         = rtrim(home_url(), '/');
    $slug_is_bad  = ($current_slug !== sanitize_key($current_slug) || $current_slug === '');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Настройки URL', 'fastwp'); ?></h1>

        <?php settings_errors('fastwp_url_group'); ?>

        <?php if ($slug_is_bad) : ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Текущий слаг недопустим:', 'fastwp'); ?></strong>
                <code><?php echo esc_html($current_slug); ?></code> —
                <?php esc_html_e('слаг должен содержать только строчные латинские буквы (a–z), цифры и дефис. Кириллица не поддерживается. Сохраните корректный слаг ниже.', 'fastwp'); ?>
            </p>
        </div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields('fastwp_url_group'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="fastwp_movie_slug">
                            <?php esc_html_e('Слаг URL фильмов', 'fastwp'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="fastwp_movie_slug" name="fastwp_movie_slug"
                               value="<?php echo esc_attr($slug_is_bad ? 'film' : $current_slug); ?>"
                               class="regular-text"
                               pattern="[a-z0-9\-]+"
                               placeholder="film"
                               title="<?php esc_attr_e('Только строчные латинские буквы, цифры и дефис', 'fastwp'); ?>">

                        <p class="description" style="margin-top:0.5rem">
                            <?php esc_html_e('Допустимые символы: a–z, 0–9, дефис. Кириллица не работает в URL-слагах WordPress.', 'fastwp'); ?>
                        </p>

                        <?php if (!$slug_is_bad) : ?>
                        <p class="description">
                            <?php printf(
                                esc_html__('Текущий URL: %s', 'fastwp'),
                                '<code>' . esc_html($home . '/' . $current_slug . '/nazvanie-filma/') . '</code>'
                            ); ?>
                        </p>
                        <?php endif; ?>

                        <div style="margin-top:0.75rem;padding:0.75rem 1rem;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;max-width:600px">
                            <strong>⚠ <?php esc_html_e('Важно:', 'fastwp'); ?></strong>
                            <?php esc_html_e(
                                ' После изменения все старые ссылки на фильмы перестанут работать. '
                                . 'Добавьте 301-редиректы через .htaccess или плагин и обновите карту сайта.',
                                'fastwp'
                            ); ?>
                        </div>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Сохранить и применить', 'fastwp')); ?>
        </form>
    </div>
    <?php
}
