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

function kb_cat_vars_reference_html(bool $with_filter = false): string
{
    $vars = [
        '%название_рубрики%'     => __('Название текущей рубрики (например: «Боевики»)', 'kinobase'),
        '%родительская_рубрика%' => __('Название родительской рубрики (например: «Жанры»). Пусто, если рубрика верхнего уровня.', 'kinobase'),
        '%сайт%'                 => __('Название сайта из настроек WordPress (Параметры → Общие)', 'kinobase'),
    ];
    if ($with_filter) {
        $vars['%фильтр%'] = __('Название термина фильтра (например: «2020», «HD»). Только для шаблонов страниц с фильтром.', 'kinobase');
    }

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

function kb_replace_cat_vars(string $tpl, WP_Term $term, ?WP_Term $filter_term = null): string
{
    $parent_name = '';
    if ($term->parent) {
        $parent = get_term((int) $term->parent, 'category');
        if ($parent instanceof WP_Term) {
            $parent_name = $parent->name;
        }
    }

    return str_replace(
        ['%название_рубрики%', '%родительская_рубрика%', '%сайт%', '%фильтр%'],
        [$term->name, $parent_name, get_bloginfo('name'), $filter_term ? $filter_term->name : ''],
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
    $h1        = (string) get_term_meta($term->term_id, '_kb_cat_h1',        true);
    $seo_title = (string) get_term_meta($term->term_id, '_kb_cat_seo_title', true);
    $seo_desc  = (string) get_term_meta($term->term_id, '_kb_cat_seo_desc',  true);
    ?>
    <tr class="form-field">
        <th scope="row">
            <label for="_kb_cat_h1"><?php esc_html_e('H1 (заголовок страницы)', 'kinobase'); ?></label>
        </th>
        <td>
            <input type="text" id="_kb_cat_h1" name="_kb_cat_h1"
                   value="<?php echo esc_attr($h1); ?>" style="width:100%">
            <p class="description">
                <?php esc_html_e('Оставьте пустым — будет использован глобальный шаблон из Параметры → SEO рубрик.', 'kinobase'); ?>
            </p>
        </td>
    </tr>
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

    if (isset($_POST['_kb_cat_h1'])) {
        update_term_meta(
            $term_id,
            '_kb_cat_h1',
            sanitize_text_field(wp_unslash($_POST['_kb_cat_h1']))
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
    register_setting('kb_cat_seo_group', 'kb_cat_h1_tpl', [
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
    ]);
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

    $h1_val        = (string) get_option('kb_cat_h1_tpl',         '');
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
                        <label for="kb_cat_h1_tpl"><?php esc_html_e('Шаблон H1', 'kinobase'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="kb_cat_h1_tpl" name="kb_cat_h1_tpl"
                               value="<?php echo esc_attr($h1_val); ?>" class="large-text">
                        <p class="description">
                            <?php printf(
                                esc_html__('Пример: %s', 'kinobase'),
                                '<code>' . esc_html('Смотреть %название_рубрики% онлайн') . '</code>'
                            ); ?>
                        </p>
                    </td>
                </tr>
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
   H1 helper — returns the H1 text for a category page
   ============================================================ */

/**
 * Finds the most specific combination rule whose category set is a subset
 * of the currently active categories (queried object + kb_filter term).
 * "Most specific" = rule with the most categories.
 *
 * @return array{cats:int[],h1:string,title:string,desc:string}|null
 */
function kb_find_filter_combination(): ?array
{
    $combinations = (array) get_option('kb_filter_combinations', []);
    if (empty($combinations)) {
        return null;
    }

    // Collect active term IDs: queried object (base category) + all filter terms
    $active  = [];
    $queried = get_queried_object();
    if ($queried instanceof WP_Term) {
        $active[$queried->term_id] = true;
    }
    foreach (kb_get_active_filter_terms() as $ft) {
        $active[$ft->term_id] = true;
    }
    if (empty($active)) {
        return null;
    }

    $best       = null;
    $best_count = 0;

    foreach ($combinations as $rule) {
        $rule_cats = array_map('intval', (array) ($rule['cats'] ?? []));
        if (empty($rule_cats)) {
            continue;
        }
        // Every cat in the rule must be active on this page
        foreach ($rule_cats as $cat_id) {
            if (!isset($active[$cat_id])) {
                continue 2;
            }
        }
        // Most specific match (most cats) wins
        if (count($rule_cats) > $best_count) {
            $best       = $rule;
            $best_count = count($rule_cats);
        }
    }

    return $best;
}

/**
 * @param WP_Term   $term         The queried (base) category.
 * @param WP_Term[] $filter_terms Active filter terms (may be empty).
 */
function kb_cat_h1(WP_Term $term, array $filter_terms = []): string
{
    if (!empty($filter_terms)) {
        $combo = kb_find_filter_combination();
        if ($combo && !empty($combo['h1'])) {
            // %фильтр% resolves to first filter term name
            return kb_replace_cat_vars($combo['h1'], $term, $filter_terms[0]);
        }
        // No rule — join all filter names as generic fallback
        $names = implode(' ', array_map(static fn($t) => $t->name, $filter_terms));
        return $term->name . ' ' . $names;
    }

    $custom = (string) get_term_meta($term->term_id, '_kb_cat_h1', true);
    $tpl    = $custom ?: (string) get_option('kb_cat_h1_tpl', '');

    if (!$tpl) {
        return $term->name;
    }

    return kb_replace_cat_vars($tpl, $term);
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

    // Filter page: /category/films/2020/action/
    $filter_terms = kb_get_active_filter_terms();
    if (!empty($filter_terms)) {
        $combo = kb_find_filter_combination();
        if ($combo && !empty($combo['title'])) {
            return kb_replace_cat_vars($combo['title'], $term, $filter_terms[0]);
        }
        $names = implode(' ', array_map(static fn($t) => $t->name, $filter_terms));
        return $term->name . ' ' . $names . ' — ' . get_bloginfo('name');
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

    // Filter page: /category/films/2020/action/
    $filter_terms = kb_get_active_filter_terms();
    if (!empty($filter_terms)) {
        $combo = kb_find_filter_combination();
        if ($combo && !empty($combo['desc'])) {
            $desc = kb_replace_cat_vars($combo['desc'], $term, $filter_terms[0]);
            echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
        }
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

/* ============================================================
   4. SEO FILTER TEMPLATES — Параметры → SEO фильтров
   A dedicated settings page where the admin picks filter-parent
   categories (Год, Качество, …) and sets H1/Title/Description
   templates for each. Stored as a single option 'kb_filter_templates'.
   ============================================================ */

add_action('admin_menu', 'kb_filter_seo_admin_menu');

function kb_filter_seo_admin_menu(): void
{
    add_options_page(
        __('SEO фильтров', 'kinobase'),
        __('SEO фильтров', 'kinobase'),
        'manage_options',
        'kb_filter_seo',
        'kb_filter_seo_settings_page'
    );
}

function kb_filter_seo_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $nonce_action = 'kb_filter_seo_save';
    $combinations = (array) get_option('kb_filter_combinations', []);
    $notice       = '';

    // ---- handle POST ----
    if (
        isset($_POST['kb_filter_seo_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kb_filter_seo_nonce'])), $nonce_action)
    ) {
        // Add new rule
        if (isset($_POST['kb_new_cats']) && is_array($_POST['kb_new_cats'])) {
            $new_cats = array_unique(array_map('intval', $_POST['kb_new_cats']));
            sort($new_cats);
            if (count($new_cats) >= 1) {
                $key              = implode(',', $new_cats);
                $combinations[$key] = [
                    'cats'  => $new_cats,
                    'h1'    => sanitize_text_field(wp_unslash($_POST['kb_new_h1']    ?? '')),
                    'title' => sanitize_text_field(wp_unslash($_POST['kb_new_title'] ?? '')),
                    'desc'  => sanitize_textarea_field(wp_unslash($_POST['kb_new_desc'] ?? '')),
                ];
            }
        }

        // Remove a rule
        if (!empty($_POST['kb_remove_combo'])) {
            $rk = sanitize_text_field(wp_unslash($_POST['kb_remove_combo']));
            unset($combinations[$rk]);
        }

        // Save edits to existing rules
        if (!empty($_POST['kb_combos']) && is_array($_POST['kb_combos'])) {
            foreach ($_POST['kb_combos'] as $key => $fields) {
                $key = sanitize_text_field(wp_unslash($key));
                if (!isset($combinations[$key])) {
                    continue;
                }
                $combinations[$key]['h1']    = sanitize_text_field(wp_unslash($fields['h1']    ?? ''));
                $combinations[$key]['title'] = sanitize_text_field(wp_unslash($fields['title'] ?? ''));
                $combinations[$key]['desc']  = sanitize_textarea_field(wp_unslash($fields['desc'] ?? ''));
            }
        }

        update_option('kb_filter_combinations', $combinations);
        $notice = 'success';
    }

    // ---- build category tree for the checkboxes ----
    $all_cats = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);
    $all_cats = is_array($all_cats) ? $all_cats : [];

    $top_level = [];
    $children  = [];
    foreach ($all_cats as $cat) {
        if ((int) $cat->parent === 0) {
            $top_level[] = $cat;
        } else {
            $children[(int) $cat->parent][] = $cat;
        }
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('SEO фильтров', 'kinobase'); ?></h1>
        <p style="max-width:680px;color:#555;margin-bottom:1rem">
            <?php esc_html_e(
                'Задайте уникальные H1, Title и Description для конкретных комбинаций рубрик. '
                . 'Если активные рубрики страницы содержат все рубрики правила — правило применяется. '
                . 'При нескольких совпадениях побеждает наиболее точное (с наибольшим числом рубрик).',
                'kinobase'
            ); ?>
        </p>
        <?php echo kb_cat_vars_reference_html(true); ?>

        <?php if ($notice === 'success') : ?>
        <div class="notice notice-success is-dismissible" style="margin-top:1rem">
            <p><?php esc_html_e('Сохранено.', 'kinobase'); ?></p>
        </div>
        <?php endif; ?>

        <form method="post" style="margin-top:1.5rem">
            <?php wp_nonce_field($nonce_action, 'kb_filter_seo_nonce'); ?>

            <?php /* ---- existing rules list ---- */ ?>
            <?php if (!empty($combinations)) : ?>
            <h2 style="font-size:1rem;margin-bottom:0.75rem">
                <?php esc_html_e('Правила', 'kinobase'); ?>
            </h2>
            <div style="display:flex;flex-direction:column;gap:1rem;max-width:860px">
                <?php foreach ($combinations as $key => $rule) :
                    $rule_cats = array_map('intval', (array) ($rule['cats'] ?? []));
                    $cat_labels = [];
                    foreach ($rule_cats as $cat_id) {
                        $c = get_term($cat_id, 'category');
                        if ($c instanceof WP_Term) {
                            $cat_labels[] = $c->name;
                        }
                    }
                ?>
                <div class="postbox" style="padding:1rem 1.5rem;margin:0">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:0.75rem">
                        <div style="display:flex;flex-wrap:wrap;gap:0.35rem;flex:1">
                            <?php foreach ($cat_labels as $lbl) : ?>
                            <span style="background:#0073aa;color:#fff;padding:2px 10px;border-radius:3px;font-size:.85em">
                                <?php echo esc_html($lbl); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" name="kb_remove_combo" value="<?php echo esc_attr($key); ?>"
                                class="button button-small"
                                onclick="return confirm('<?php esc_attr_e('Удалить правило?', 'kinobase'); ?>')">
                            ✕
                        </button>
                    </div>
                    <table style="width:100%;border-collapse:collapse">
                        <?php foreach (['h1' => 'H1', 'title' => 'Title', 'desc' => 'Description'] as $f => $lbl) : ?>
                        <tr>
                            <td style="width:100px;padding:4px 10px 4px 0;vertical-align:top;color:#555;font-size:.9em">
                                <?php echo esc_html($lbl); ?>
                            </td>
                            <td style="padding:4px 0">
                                <?php if ($f === 'desc') : ?>
                                <textarea name="kb_combos[<?php echo esc_attr($key); ?>][desc]"
                                          rows="2" class="large-text"><?php echo esc_textarea($rule['desc'] ?? ''); ?></textarea>
                                <?php else : ?>
                                <input type="text"
                                       name="kb_combos[<?php echo esc_attr($key); ?>][<?php echo esc_attr($f); ?>]"
                                       value="<?php echo esc_attr($rule[$f] ?? ''); ?>"
                                       class="large-text">
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>
            <?php submit_button(__('Сохранить изменения', 'kinobase'), 'primary', 'kb_save', false,
                ['style' => 'margin-top:1rem']); ?>
            <hr style="margin:2rem 0;max-width:860px">
            <?php endif; ?>

            <?php /* ---- add new rule ---- */ ?>
            <h2 style="font-size:1rem;margin-bottom:0.75rem">
                <?php esc_html_e('Новое правило', 'kinobase'); ?>
            </h2>
            <div style="max-width:860px;border:1px solid #ddd;border-radius:4px;padding:1.25rem 1.5rem;background:#fafafa">
                <p style="margin:0 0 1rem;color:#555;font-size:.9em">
                    <?php esc_html_e('Отметьте рубрики, составляющие комбинацию:', 'kinobase'); ?>
                </p>

                <div style="display:flex;flex-wrap:wrap;gap:2rem;margin-bottom:1.25rem">
                    <?php if (!empty($top_level)) : ?>
                    <div>
                        <div style="font-size:.75em;text-transform:uppercase;letter-spacing:.06em;color:#888;margin-bottom:0.5rem">
                            <?php esc_html_e('Разделы', 'kinobase'); ?>
                        </div>
                        <?php foreach ($top_level as $cat) : ?>
                        <label style="display:block;margin-bottom:0.35rem;cursor:pointer">
                            <input type="checkbox" name="kb_new_cats[]"
                                   value="<?php echo (int) $cat->term_id; ?>">
                            <?php echo esc_html($cat->name); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php foreach ($top_level as $parent) :
                        $kids = $children[(int) $parent->term_id] ?? [];
                        if (empty($kids)) continue;
                    ?>
                    <div>
                        <div style="font-size:.75em;text-transform:uppercase;letter-spacing:.06em;color:#888;margin-bottom:0.5rem">
                            <?php echo esc_html($parent->name); ?>
                        </div>
                        <?php foreach ($kids as $cat) : ?>
                        <label style="display:block;margin-bottom:0.35rem;cursor:pointer">
                            <input type="checkbox" name="kb_new_cats[]"
                                   value="<?php echo (int) $cat->term_id; ?>">
                            <?php echo esc_html($cat->name); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <table style="width:100%;border-collapse:collapse;margin-bottom:1rem">
                    <tr>
                        <td style="width:100px;padding:4px 10px 4px 0;vertical-align:top;color:#555;font-size:.9em">H1</td>
                        <td style="padding:4px 0">
                            <input type="text" name="kb_new_h1" class="large-text"
                                   placeholder="<?php esc_attr_e('например: %название_рубрики% %фильтр% года', 'kinobase'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:4px 10px 4px 0;vertical-align:top;color:#555;font-size:.9em">Title</td>
                        <td style="padding:4px 0">
                            <input type="text" name="kb_new_title" class="large-text"
                                   placeholder="<?php esc_attr_e('например: %название_рубрики% %фильтр% | %сайт%', 'kinobase'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:4px 10px 4px 0;vertical-align:top;color:#555;font-size:.9em">Description</td>
                        <td style="padding:4px 0">
                            <textarea name="kb_new_desc" rows="2" class="large-text"
                                      placeholder="<?php esc_attr_e('Описание для этой комбинации рубрик', 'kinobase'); ?>"></textarea>
                        </td>
                    </tr>
                </table>

                <button type="submit" name="kb_add_submit" class="button button-primary">
                    + <?php esc_html_e('Добавить правило', 'kinobase'); ?>
                </button>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Returns all active filter WP_Terms for the current filtered category page
 * (e.g. /category/films/2020/action/ → [WP_Term(2020), WP_Term(action)]).
 * Returns empty array when no filters are active.
 *
 * @return WP_Term[]
 */
function kb_get_active_filter_terms(): array
{
    $filter_path = sanitize_text_field((string) get_query_var('kb_filter_path', ''));
    if (!$filter_path) {
        return [];
    }
    $slugs = array_values(array_filter(
        array_map('sanitize_key', explode('/', trim($filter_path, '/')))
    ));
    $terms = [];
    foreach ($slugs as $slug) {
        $term = get_category_by_slug($slug);
        if ($term instanceof WP_Term) {
            $terms[] = $term;
        }
    }
    return $terms;
}

/**
 * Returns the first active filter WP_Term, or null when no filter is active.
 * For multi-filter pages use kb_get_active_filter_terms().
 */
function kb_get_active_filter_term(): ?WP_Term
{
    $terms = kb_get_active_filter_terms();
    return $terms[0] ?? null;
}
