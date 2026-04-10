<?php
/**
 * KinoBase — Movie Field Builder
 *
 * Admin page (Фильмы → Мета-боксы) to add/remove custom fields
 * for the «Информация о фильме» meta-box without editing PHP.
 *
 * Fixed sections (галерея, цены, купон, актёры/режиссёры) are not affected.
 *
 * @package KinoBase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   Public API — used by meta-boxes.php and single.php
   ============================================================ */

/**
 * Built-in default fields for «Информация о фильме».
 *
 * @return array<int, array{key: string, label: string, type: string}>
 */
function kinobase_default_fields(): array
{
    return [
        ['key' => 'movie_duration',    'label' => 'Длительность',  'type' => 'text'],
        ['key' => 'movie_quality',     'label' => 'Качество',       'type' => 'text'],
        ['key' => 'movie_translation', 'label' => 'Перевод',        'type' => 'text'],
    ];
}

/**
 * Get the current field list (from WP option, or defaults on first run).
 *
 * @return array<int, array{key: string, label: string, type: string}>
 */
function kinobase_get_movie_fields(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $saved = get_option('kinobase_movie_fields', null);
    $cache = ($saved === null) ? kinobase_default_fields() : array_values((array) $saved);
    return $cache;
}

/* ============================================================
   Admin menu
   ============================================================ */

add_action('admin_menu', 'kinobase_field_builder_menu');

function kinobase_field_builder_menu(): void
{
    add_submenu_page(
        'edit.php?post_type=movie',
        __('Мета-боксы', 'kinobase'),
        __('Мета-боксы', 'kinobase'),
        'manage_options',
        'kinobase-fields',
        'kinobase_field_builder_page'
    );
}

/* ============================================================
   Action handlers (admin-post.php)
   ============================================================ */

add_action('admin_post_kinobase_add_field',    'kinobase_handle_add_field');
add_action('admin_post_kinobase_delete_field', 'kinobase_handle_delete_field');
add_action('admin_post_kinobase_reset_fields', 'kinobase_handle_reset_fields');

function kinobase_handle_add_field(): void
{
    check_admin_referer('kinobase_field_builder');
    if (!current_user_can('manage_options')) {
        wp_die(__('Недостаточно прав.', 'kinobase'), 403);
    }

    $label = sanitize_text_field($_POST['field_label'] ?? '');
    $key   = sanitize_key($_POST['field_key']   ?? '');
    $type  = in_array($_POST['field_type'] ?? '', ['text', 'number', 'textarea'], true)
             ? $_POST['field_type'] : 'text';

    $redirect = admin_url('edit.php?post_type=movie&page=kinobase-fields');

    if (!$label || !$key) {
        wp_redirect(add_query_arg('kberr', 'empty', $redirect));
        exit;
    }

    // Ensure movie_ prefix
    if (!str_starts_with($key, 'movie_')) {
        $key = 'movie_' . $key;
    }

    // Reserved keys
    $reserved = ['movie_gallery', 'movie_coupon', 'movie_views',
                 'movie_rent_1', 'movie_rent_3', 'movie_rent_5',
                 'movie_buy_week', 'movie_buy_month', 'movie_buy_forever',
                 'movie_actors', 'movie_directors', 'movie_rating', 'movie_box_office'];
    if (in_array($key, $reserved, true)) {
        wp_redirect(add_query_arg('kberr', 'reserved', $redirect));
        exit;
    }

    $fields = kinobase_get_movie_fields();

    // Duplicate check
    foreach ($fields as $f) {
        if ($f['key'] === $key) {
            wp_redirect(add_query_arg('kberr', 'duplicate', $redirect));
            exit;
        }
    }

    $fields[] = compact('key', 'label', 'type');
    update_option('kinobase_movie_fields', $fields);

    wp_redirect(add_query_arg('kbdone', 'added', $redirect));
    exit;
}

function kinobase_handle_delete_field(): void
{
    check_admin_referer('kinobase_field_builder');
    if (!current_user_can('manage_options')) {
        wp_die(__('Недостаточно прав.', 'kinobase'), 403);
    }

    $key    = sanitize_key($_POST['field_key'] ?? '');
    $fields = kinobase_get_movie_fields();
    $fields = array_values(array_filter($fields, fn($f) => $f['key'] !== $key));
    update_option('kinobase_movie_fields', $fields);

    $redirect = admin_url('edit.php?post_type=movie&page=kinobase-fields');
    wp_redirect(add_query_arg('kbdone', 'deleted', $redirect));
    exit;
}

function kinobase_handle_reset_fields(): void
{
    check_admin_referer('kinobase_reset_fields_nonce');
    if (!current_user_can('manage_options')) {
        wp_die(__('Недостаточно прав.', 'kinobase'), 403);
    }

    delete_option('kinobase_movie_fields');

    $redirect = admin_url('edit.php?post_type=movie&page=kinobase-fields');
    wp_redirect(add_query_arg('kbdone', 'reset', $redirect));
    exit;
}

/* ============================================================
   Admin page HTML
   ============================================================ */

function kinobase_field_builder_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $fields  = kinobase_get_movie_fields();
    $err     = sanitize_key($_GET['kberr']  ?? '');
    $done    = sanitize_key($_GET['kbdone'] ?? '');
    $post_url = esc_url(admin_url('admin-post.php'));

    $type_labels = [
        'text'     => __('Текст (одна строка)', 'kinobase'),
        'number'   => __('Число', 'kinobase'),
        'textarea' => __('Текст (многострочный)', 'kinobase'),
    ];
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Мета-боксы', 'kinobase'); ?></h1>
        <p style="color:#666;max-width:680px;">
            <?php esc_html_e('Управляйте полями мета-бокса «Информация о фильме» прямо из админки — без редактирования кода. Поля галереи, цен, купона, актёров и режиссёров зафиксированы и не затрагиваются.', 'kinobase'); ?>
        </p>

        <?php
        // Notices
        $notices = [
            'added'     => ['success', __('Поле добавлено.', 'kinobase')],
            'deleted'   => ['success', __('Поле удалено.', 'kinobase')],
            'reset'     => ['success', __('Поля сброшены к стандартным.', 'kinobase')],
            'empty'     => ['error',   __('Заполните название и ключ поля.', 'kinobase')],
            'duplicate' => ['error',   __('Поле с таким ключом уже существует.', 'kinobase')],
            'reserved'  => ['error',   __('Этот ключ зарезервирован системой и не может быть добавлен.', 'kinobase')],
        ];
        if ($done && isset($notices[$done])) {
            [$type, $msg] = $notices[$done];
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }
        if ($err && isset($notices[$err])) {
            [$type, $msg] = $notices[$err];
            echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        }
        ?>

        <!-- ── Current fields ── -->
        <h2 style="margin-top:1.5rem;"><?php esc_html_e('Текущие поля', 'kinobase'); ?></h2>
        <?php if (empty($fields)) : ?>
        <p><?php esc_html_e('Полей нет. Добавьте первое ниже.', 'kinobase'); ?></p>
        <?php else : ?>
        <table class="wp-list-table widefat fixed striped" style="max-width:820px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Название', 'kinobase'); ?></th>
                    <th><?php esc_html_e('Ключ (meta_key)', 'kinobase'); ?></th>
                    <th><?php esc_html_e('Тип', 'kinobase'); ?></th>
                    <th style="width:80px;"><?php esc_html_e('Действие', 'kinobase'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($fields as $field) : ?>
                <tr>
                    <td><strong><?php echo esc_html($field['label']); ?></strong></td>
                    <td><code><?php echo esc_html($field['key']); ?></code></td>
                    <td><?php echo esc_html($type_labels[$field['type']] ?? $field['type']); ?></td>
                    <td>
                        <form method="post" action="<?php echo $post_url; ?>"
                              onsubmit="return confirm('<?php echo esc_js(__('Удалить это поле? Ранее сохранённые значения в базе останутся.', 'kinobase')); ?>');">
                            <?php wp_nonce_field('kinobase_field_builder'); ?>
                            <input type="hidden" name="action"    value="kinobase_delete_field">
                            <input type="hidden" name="field_key" value="<?php echo esc_attr($field['key']); ?>">
                            <button type="submit" class="button button-small" style="color:#b32d2e;">
                                <?php esc_html_e('Удалить', 'kinobase'); ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- ── Add field ── -->
        <h2 style="margin-top:2rem;"><?php esc_html_e('Добавить поле', 'kinobase'); ?></h2>
        <form method="post" action="<?php echo $post_url; ?>" style="max-width:680px;">
            <?php wp_nonce_field('kinobase_field_builder'); ?>
            <input type="hidden" name="action" value="kinobase_add_field">
            <table class="form-table">
                <tr>
                    <th><label for="field_label"><?php esc_html_e('Название поля', 'kinobase'); ?></label></th>
                    <td>
                        <input type="text" id="field_label" name="field_label" class="regular-text"
                               placeholder="<?php esc_attr_e('Например: Полученные награды', 'kinobase'); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="field_key"><?php esc_html_e('Ключ', 'kinobase'); ?></label></th>
                    <td>
                        <input type="text" id="field_key" name="field_key" class="regular-text"
                               placeholder="<?php esc_attr_e('awards', 'kinobase'); ?>">
                        <p class="description">
                            <?php esc_html_e('Только a–z, 0–9, подчёркивание. Префикс movie_ добавится автоматически.', 'kinobase'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="field_type"><?php esc_html_e('Тип ввода', 'kinobase'); ?></label></th>
                    <td>
                        <select id="field_type" name="field_type">
                            <option value="text"><?php esc_html_e('Текст (одна строка)', 'kinobase'); ?></option>
                            <option value="number"><?php esc_html_e('Число', 'kinobase'); ?></option>
                            <option value="textarea"><?php esc_html_e('Текст (многострочный)', 'kinobase'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Добавить поле', 'kinobase'), 'primary', 'submit', false); ?>
        </form>

        <!-- ── Reset ── -->
        <hr style="margin:2rem 0;">
        <h3><?php esc_html_e('Сброс к стандартным', 'kinobase'); ?></h3>
        <p style="color:#666;"><?php esc_html_e('Восстановить три поля по умолчанию: Длительность, Качество, Перевод. Добавленные поля пропадут из конструктора, но данные в базе останутся.', 'kinobase'); ?></p>
        <form method="post" action="<?php echo $post_url; ?>"
              onsubmit="return confirm('<?php echo esc_js(__('Сбросить список полей к стандартным?', 'kinobase')); ?>');">
            <?php wp_nonce_field('kinobase_reset_fields_nonce'); ?>
            <input type="hidden" name="action" value="kinobase_reset_fields">
            <?php submit_button(__('Сбросить', 'kinobase'), 'secondary', 'submit', false); ?>
        </form>
    </div>

    <script>
    /* Auto-generate latin key from cyrillic/latin label */
    (function () {
        var labelInput = document.getElementById('field_label');
        var keyInput   = document.getElementById('field_key');

        var map = {
            'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'yo','ж':'zh',
            'з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o',
            'п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'kh','ц':'ts',
            'ч':'ch','ш':'sh','щ':'sch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'
        };

        function toKey(str) {
            return str.toLowerCase()
                .split('').map(function(c) { return map[c] !== undefined ? map[c] : c; }).join('')
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        }

        labelInput.addEventListener('input', function () {
            if (keyInput.dataset.manual) return;
            keyInput.value = toKey(this.value);
        });
        keyInput.addEventListener('input', function () {
            this.dataset.manual = '1';
        });
    })();
    </script>
    <?php
}
