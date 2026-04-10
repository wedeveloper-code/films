<?php
/**
 * KinoBase — Category Meta
 *
 * 1. «Текст внизу страницы» — custom textarea on every category edit form,
 *    stored as term_meta `kb_bottom_description`, displayed at the bottom
 *    of archive.php.
 *
 * 2. Per-category SEO overrides — «SEO Title» and «SEO Description» fields
 *    in the category edit form (optional, override the global template).
 *
 * 3. Global SEO templates — Параметры → SEO рубрик settings page, applies
 *    to all category archive pages. Supports variables:
 *    %название_рубрики%  — current category name
 *    %родительская_рубрика% — parent category name (empty if top-level)
 *    %сайт%              — site name from WP settings
 *
 * @package KinoBase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   Variables reference HTML block (reused in form fields + settings page)
   ============================================================ */

function kb_cat_vars_reference_html(): string
{
    $vars = [
        '%название_рубрики%'     => __('Название текущей рубрики (например: «Боевики»)', 'kinobase'),
        '%родительская_рубрика%' => __('Название родительской рубрики (например: «Жанры»). Пусто, если рубрика верхнего уровня.', 'kinobase'),
        '%сайт%'                 => __('Название сайта из настроек WordPress (Параметры → Общие)', 'kinobase'),
    ];

    $rows = '';
    foreach ($vars as $var => $desc) {
        $rows .= '<tr>'
               . '<td style="padding:3px 12px 3px 0;white-space:nowrap"><code style="background:#eee;padding:1px 5px;border-radius:3px">'
               . esc_html($var) . '</code></td>'
               . '<td style="padding:3px 0;color:#555;font-size:0.85em">' . esc_html($desc) . '</td>'
               . '</tr>';
    }

    return '<div style="margin-top:0.75rem;border:1px solid #ddd;border-radius:4px;padding:0.75rem 1rem;background:#f9f9f9">'
         . '<strong style="display:block;margin-bottom:0.5rem;font-size:0.85em">'
         . esc_html__('Поддерживаемые переменные:', 'kinobase')
         . '</strong>'
         . '<table style="border-collapse:collapse">' . $rows . '</table>'
         . '</div>';
}

/* ============================================================
   Variable replacement for category pages
   ============================================================ */

function kb_replace_cat_vars(string $tpl, WP_Term $term): string
{
    $parent_name = '';
    if ($term->parent) {
        $parent = get_term((int) $term->parent, 'category');
        if ($parent instanceof WP_Term) {
            $parent_name = $parent->name;
        }
    }

    return str_replace(
        ['%название_рубрики%', '%родительская_рубрика%', '%сайт%'],
        [$term->name, $parent_name, get_bloginfo('name')],
        $tpl
    );
}

/* ============================================================
   1. CATEGORY EDIT FORM — «Текст внизу страницы»
   ============================================================ */

add_action('category_edit_form_fields', 'kb_cat_bottom_desc_field');

function kb_cat_bottom_desc_field(WP_Term $term): void
{
    $value = (string) get_term_meta($term->term_id, 'kb_bottom_description', true);
    wp_nonce_field('kb_cat_meta_save', 'kb_cat_meta_nonce');
    ?>
    <tr class="form-field">
        <th scope="row">
            <label for="kb_bottom_description"><?php esc_html_e('Текст внизу страницы', 'kinobase'); ?></label>
        </th>
        <td>
            <textarea id="kb_bottom_description" name="kb_bottom_description"
                      rows="7" cols="50" style="width:100%"><?php echo esc_textarea($value); ?></textarea>
            <p class="description">
                <?php esc_html_e('Отображается внизу страницы рубрики, после списка фильмов. Поддерживает HTML.', 'kinobase'); ?>
            </p>
        </td>
    </tr>
    <?php
}

/* ============================================================
   2. CATEGORY EDIT FORM — per-category SEO overrides
   ============================================================ */

add_action('category_edit_form_fields', 'kb_cat_seo_fields');

function kb_cat_seo_fields(WP_Term $term): void
{
    $seo_title = (string) get_term_meta($term->term_id, '_kb_cat_seo_title', true);
    $seo_desc  = (string) get_term_meta($term->term_id, '_kb_cat_seo_desc',  true);
    ?>
    <tr class="form-field">
        <th scope="row">
            <label for="_kb_cat_seo_title"><?php esc_html_e('SEO Title', 'kinobase'); ?></label>
        </th>
        <td>
            <input type="text" id="_kb_cat_seo_title" name="_kb_cat_seo_title"
                   value="<?php echo esc_attr($seo_title); ?>" style="width:100%">
            <p class="description">
                <?php esc_html_e('Оставьте пустым — будет использован глобальный шаблон из Параметры → SEO рубрик.', 'kinobase'); ?>
            </p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row">
            <label for="_kb_cat_seo_desc"><?php esc_html_e('SEO Description', 'kinobase'); ?></label>
        </th>
        <td>
            <textarea id="_kb_cat_seo_desc" name="_kb_cat_seo_desc"
                      rows="3" cols="50" style="width:100%"><?php echo esc_textarea($seo_desc); ?></textarea>
            <p class="description">
                <?php esc_html_e('Оставьте пустым — будет использован глобальный шаблон из Параметры → SEO рубрик.', 'kinobase'); ?>
            </p>
            <?php echo kb_cat_vars_reference_html(); ?>
        </td>
    </tr>
    <?php
}

/* ============================================================
   Save term meta (bottom description + SEO overrides)
   ============================================================ */

add_action('edited_category',  'kb_cat_save_meta');
add_action('created_category', 'kb_cat_save_meta');

function kb_cat_save_meta(int $term_id): void
{
    if (
        !isset($_POST['kb_cat_meta_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['kb_cat_meta_nonce'])),
            'kb_cat_meta_save'
        )
    ) {
        return;
    }

    if (!current_user_can('manage_categories')) {
        return;
    }

    if (isset($_POST['kb_bottom_description'])) {
        update_term_meta(
            $term_id,
            'kb_bottom_description',
            wp_kses_post(wp_unslash($_POST['kb_bottom_description']))
        );
    }

    if (isset($_POST['_kb_cat_seo_title'])) {
        update_term_meta(
            $term_id,
            '_kb_cat_seo_title',
            sanitize_text_field(wp_unslash($_POST['_kb_cat_seo_title']))
        );
    }

    if (isset($_POST['_kb_cat_seo_desc'])) {
        update_term_meta(
            $term_id,
            '_kb_cat_seo_desc',
            sanitize_textarea_field(wp_unslash($_POST['_kb_cat_seo_desc']))
        );
    }
}

/* ============================================================
   3. GLOBAL SEO TEMPLATES — Параметры → SEO рубрик
   ============================================================ */

add_action('admin_menu', 'kb_cat_seo_admin_menu');

function kb_cat_seo_admin_menu(): void
{
    add_options_page(
        __('SEO рубрик', 'kinobase'),
        __('SEO рубрик', 'kinobase'),
        'manage_options',
        'kb_cat_seo',
        'kb_cat_seo_settings_page'
    );
}

add_action('admin_init', 'kb_cat_seo_register_settings');

function kb_cat_seo_register_settings(): void
{
    register_setting('kb_cat_seo_group', 'kb_cat_seo_title_tpl', [
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '%название_рубрики% — смотреть онлайн | %сайт%',
    ]);
    register_setting('kb_cat_seo_group', 'kb_cat_seo_desc_tpl', [
        'sanitize_callback' => 'sanitize_textarea_field',
        'default'           => '',
    ]);
}

function kb_cat_seo_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $title_default = '%название_рубрики% — смотреть онлайн | %сайт%';
    $title_val     = (string) get_option('kb_cat_seo_title_tpl', $title_default);
    $desc_val      = (string) get_option('kb_cat_seo_desc_tpl',  '');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('SEO шаблоны страниц рубрик', 'kinobase'); ?></h1>
        <p style="max-width:680px;color:#555">
            <?php esc_html_e(
                'Шаблоны применяются ко всем страницам рубрик. Для конкретной рубрики можно задать '
                . 'индивидуальные значения прямо в её редакторе (Рубрики → [название рубрики]).',
                'kinobase'
            ); ?>
        </p>

        <?php settings_errors('kb_cat_seo_group'); ?>

        <form method="post" action="options.php">
            <?php settings_fields('kb_cat_seo_group'); ?>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="kb_cat_seo_title_tpl"><?php esc_html_e('Шаблон &lt;title&gt;', 'kinobase'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="kb_cat_seo_title_tpl" name="kb_cat_seo_title_tpl"
                               value="<?php echo esc_attr($title_val); ?>" class="large-text">
                        <p class="description">
                            <?php printf(
                                esc_html__('Пример: %s', 'kinobase'),
                                '<code>' . esc_html('%название_рубрики% — смотреть онлайн | %сайт%') . '</code>'
                            ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="kb_cat_seo_desc_tpl"><?php esc_html_e('Шаблон Description', 'kinobase'); ?></label>
                    </th>
                    <td>
                        <textarea id="kb_cat_seo_desc_tpl" name="kb_cat_seo_desc_tpl"
                                  rows="4" class="large-text"><?php echo esc_textarea($desc_val); ?></textarea>
                        <p class="description">
                            <?php printf(
                                esc_html__('Пример: %s', 'kinobase'),
                                '<code>' . esc_html('Смотрите %название_рубрики% онлайн на %сайт% в хорошем качестве.') . '</code>'
                            ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php echo kb_cat_vars_reference_html(); ?>
            <br>

            <?php submit_button(__('Сохранить шаблоны', 'kinobase')); ?>
        </form>
    </div>
    <?php
}

/* ============================================================
   SEO hooks — apply templates to category archive pages
   ============================================================ */

add_filter('pre_get_document_title', 'kb_cat_seo_title', 5);

function kb_cat_seo_title(string $title): string
{
    if (!is_category()) {
        return $title;
    }

    $term = get_queried_object();
    if (!($term instanceof WP_Term)) {
        return $title;
    }

    // Per-category override takes priority over global template
    $custom = (string) get_term_meta($term->term_id, '_kb_cat_seo_title', true);
    $tpl    = $custom ?: (string) get_option('kb_cat_seo_title_tpl', '');

    if (!$tpl) {
        return $title;
    }

    return kb_replace_cat_vars($tpl, $term);
}

add_action('wp_head', 'kb_cat_seo_description', 1);

function kb_cat_seo_description(): void
{
    if (!is_category()) {
        return;
    }

    $term = get_queried_object();
    if (!($term instanceof WP_Term)) {
        return;
    }

    $custom = (string) get_term_meta($term->term_id, '_kb_cat_seo_desc', true);
    $tpl    = $custom ?: (string) get_option('kb_cat_seo_desc_tpl', '');

    if (!$tpl) {
        return;
    }

    $desc = kb_replace_cat_vars($tpl, $term);
    echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
}
