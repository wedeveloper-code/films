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
        'sanitize_callback' => static function (string $value): string {
            $slug = sanitize_title(trim($value));
            return $slug !== '' ? $slug : 'film';
        },
        'default' => 'film',
    ]);
}

/* ============================================================
   Flush rewrite rules when slug changes
   ============================================================ */

add_action('update_option_fastwp_movie_slug', 'fastwp_flush_on_movie_slug_change', 10, 2);

function fastwp_flush_on_movie_slug_change(mixed $old, mixed $new): void
{
    if ((string) $old !== (string) $new) {
        flush_rewrite_rules();
    }
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
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Настройки URL', 'fastwp'); ?></h1>

        <?php settings_errors('fastwp_url_group'); ?>

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
                               value="<?php echo esc_attr($current_slug); ?>"
                               class="regular-text"
                               pattern="[a-z0-9\-]+"
                               title="Только строчные латинские буквы, цифры и дефис">

                        <p class="description" style="margin-top:0.5rem">
                            <?php printf(
                                esc_html__('Текущий URL фильма: %s', 'fastwp'),
                                '<code>' . esc_html($home . '/' . $current_slug . '/название-фильма/') . '</code>'
                            ); ?>
                        </p>

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
