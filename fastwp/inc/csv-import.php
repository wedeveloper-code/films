<?php
/**
 * FastWP — CSV Import for Movies
 *
 * Admin page: Инструменты → Импорт фильмов
 *
 * Images are placed by the admin via FTP into:
 *   {WP_CONTENT_DIR}/uploads/fastwp-import/
 * and referenced in the CSV by filename only.
 *
 * @package FastWP
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   Admin menu
   ============================================================ */

add_action('admin_menu', 'fastwp_csv_import_menu');

function fastwp_csv_import_menu(): void
{
    add_management_page(
        __('Импорт фильмов (CSV)', 'fastwp'),
        __('Импорт фильмов', 'fastwp'),
        'manage_options',
        'fastwp-csv-import',
        'fastwp_csv_import_page'
    );
}

/* ============================================================
   Import directory helpers
   ============================================================ */

function fastwp_csv_import_dir(): string
{
    return WP_CONTENT_DIR . '/uploads/fastwp-import';
}

function fastwp_csv_ensure_import_dir(): bool
{
    $dir = fastwp_csv_import_dir();
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
        @file_put_contents($dir . '/.htaccess', "Options -Indexes\n");
    }
    return is_dir($dir) && is_writable($dir);
}

/* ============================================================
   Image registration
   Finds an existing attachment by filename, or copies the file
   from the import directory to wp-content/uploads/ and creates
   a new attachment record.
   Returns attachment ID (>0), 0 on failure, -1 on dry-run.
   ============================================================ */

function fastwp_csv_register_image(string $filename, int $post_id, bool $dry_run): int
{
    $filename = sanitize_file_name(trim($filename));
    if ($filename === '') {
        return 0;
    }

    $source = fastwp_csv_import_dir() . '/' . $filename;
    if (!file_exists($source)) {
        return 0;
    }

    // Check if already in the media library (match by file basename in guid)
    global $wpdb;
    $existing_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = 'attachment'
           AND (post_title = %s OR guid LIKE %s)
         LIMIT 1",
        pathinfo($filename, PATHINFO_FILENAME),
        '%/' . $wpdb->esc_like($filename)
    ));
    if ($existing_id > 0) {
        return $existing_id;
    }

    if ($dry_run) {
        return -1; // would create new attachment
    }

    // Copy file to current uploads sub-directory
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $upload_dir  = wp_upload_dir();
    $unique_name = wp_unique_filename($upload_dir['path'], $filename);
    $dest_path   = $upload_dir['path'] . '/' . $unique_name;

    if (!@copy($source, $dest_path)) {
        return 0;
    }

    $filetype  = wp_check_filetype($unique_name, null);
    $attach_id = wp_insert_attachment([
        'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
        'post_title'     => pathinfo($unique_name, PATHINFO_FILENAME),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ], $dest_path, $post_id);

    if (is_wp_error($attach_id)) {
        return 0;
    }

    $meta = wp_generate_attachment_metadata($attach_id, $dest_path);
    wp_update_attachment_metadata($attach_id, $meta);

    return (int) $attach_id;
}

/* ============================================================
   Category resolver
   Finds a category by slug or name; creates it if missing.
   ============================================================ */

function fastwp_csv_resolve_category(string $slug_or_name): int
{
    $slug_or_name = trim($slug_or_name);
    if ($slug_or_name === '') {
        return 0;
    }

    // Try by slug
    $term = get_category_by_slug(sanitize_title($slug_or_name));
    if ($term instanceof WP_Term) {
        return (int) $term->term_id;
    }

    // Try by name (exact match)
    $found = get_terms([
        'taxonomy'   => 'category',
        'name'       => $slug_or_name,
        'hide_empty' => false,
        'number'     => 1,
    ]);
    if (!is_wp_error($found) && !empty($found)) {
        return (int) $found[0]->term_id;
    }

    // Create category
    $result = wp_insert_term($slug_or_name, 'category');
    if (is_wp_error($result)) {
        return 0;
    }
    return (int) $result['term_id'];
}

/* ============================================================
   Process one CSV row → create/update a movie post
   Returns array with 'status' (created|updated|skipped|error)
   and 'message'.
   ============================================================ */

function fastwp_csv_process_row(array $row, bool $dry_run): array
{
    $title = trim($row['title'] ?? '');
    if ($title === '') {
        return ['status' => 'skipped', 'message' => 'Пустое название — строка пропущена'];
    }

    $slug            = sanitize_title(trim($row['slug'] ?? '')) ?: sanitize_title($title);
    $update_existing = strtolower(trim($row['update_existing'] ?? '')) === 'yes';

    // Check for existing post by slug
    $existing = get_page_by_path($slug, OBJECT, 'movie');
    if ($existing) {
        if (!$update_existing) {
            return ['status' => 'skipped', 'message' => "«{$title}» уже существует (слаг: {$slug}). Для обновления укажите update_existing=yes"];
        }
        $post_id = (int) $existing->ID;
    } else {
        $post_id = 0;
    }

    if ($dry_run) {
        $action = $post_id > 0 ? 'обновлён' : 'создан';
        return ['status' => 'dry_run', 'message' => "«{$title}» будет {$action}"];
    }

    // --- Build post data ---
    $status = in_array(trim($row['status'] ?? ''), ['publish', 'draft', 'private'], true)
        ? trim($row['status'])
        : 'publish';

    $post_data = [
        'post_type'    => 'movie',
        'post_title'   => wp_strip_all_tags($title),
        'post_name'    => $slug,
        'post_content' => wp_kses_post(wp_unslash($row['content'] ?? '')),
        'post_excerpt' => wp_strip_all_tags($row['excerpt'] ?? ''),
        'post_status'  => $status,
    ];

    $date = trim($row['date'] ?? '');
    if ($date !== '' && strtotime($date) !== false) {
        $post_data['post_date']     = date('Y-m-d H:i:s', strtotime($date));
        $post_data['post_date_gmt'] = get_gmt_from_date($post_data['post_date']);
    }

    if ($post_id > 0) {
        $post_data['ID'] = $post_id;
        $result = wp_update_post($post_data, true);
    } else {
        $result = wp_insert_post($post_data, true);
    }

    if (is_wp_error($result)) {
        return ['status' => 'error', 'message' => "«{$title}»: " . $result->get_error_message()];
    }
    $post_id = (int) $result;

    // --- Categories ---
    $cat_ids = [];
    $cats_raw = trim($row['categories'] ?? '');
    if ($cats_raw !== '') {
        foreach (array_map('trim', explode(',', $cats_raw)) as $cat) {
            $cat_id = fastwp_csv_resolve_category($cat);
            if ($cat_id > 0) {
                $cat_ids[] = $cat_id;
            }
        }
    }
    if (!empty($cat_ids)) {
        wp_set_post_categories($post_id, $cat_ids);
    }

    // --- Tags ---
    $tags_raw = trim($row['tags'] ?? '');
    if ($tags_raw !== '') {
        wp_set_post_tags($post_id, $tags_raw);
    }

    // --- Gallery images ---
    $gallery_cols = ['poster', 'gallery_2', 'gallery_3', 'gallery_4'];
    $gallery_ids  = [];

    foreach ($gallery_cols as $col) {
        $filename = trim($row[$col] ?? '');
        if ($filename !== '') {
            $img_id = fastwp_csv_register_image($filename, $post_id, false);
            if ($img_id > 0) {
                $gallery_ids[] = $img_id;
            }
        } else {
            $gallery_ids[] = 0;
        }
    }

    // Poster (first image) → featured image
    if ($gallery_ids[0] > 0) {
        set_post_thumbnail($post_id, $gallery_ids[0]);
    }

    // Store full gallery (all 4 slots, 0 = empty slot)
    update_post_meta($post_id, 'movie_gallery', $gallery_ids);

    // --- Fixed meta fields ---
    $simple_meta = [
        'movie_actors'      => sanitize_textarea_field($row['actors']      ?? ''),
        'movie_directors'   => sanitize_textarea_field($row['directors']   ?? ''),
        'movie_coupon'      => sanitize_text_field($row['coupon']          ?? ''),
        'movie_rent_1'      => (int) ($row['rent_1']    ?? 0),
        'movie_rent_3'      => (int) ($row['rent_3']    ?? 0),
        'movie_rent_5'      => (int) ($row['rent_5']    ?? 0),
        'movie_buy_week'    => (int) ($row['buy_week']  ?? 0),
        'movie_buy_month'   => (int) ($row['buy_month'] ?? 0),
        'movie_buy_forever' => (int) ($row['buy_forever'] ?? 0),
        'movie_rating'      => (float) ($row['rating']  ?? 0),
    ];

    foreach ($simple_meta as $key => $value) {
        if ($value !== '' && $value !== 0 && $value !== 0.0) {
            update_post_meta($post_id, $key, $value);
        }
    }

    // --- Dynamic meta fields (from fastwp_get_movie_fields()) ---
    foreach (fastwp_get_movie_fields() as $field) {
        $csv_col = str_replace('movie_', '', $field['key']); // e.g. movie_duration → duration
        $raw     = trim($row[$csv_col] ?? '');
        if ($raw !== '') {
            $value = ($field['type'] === 'number')
                ? (float) $raw
                : sanitize_text_field($raw);
            update_post_meta($post_id, $field['key'], $value);
        }
    }

    $action = isset($post_data['ID']) ? 'updated' : 'created';
    return [
        'status'  => $action,
        'message' => "«{$title}» — " . ($action === 'created' ? 'создан' : 'обновлён') . " (ID: {$post_id})",
        'post_id' => $post_id,
    ];
}

/* ============================================================
   Main import function
   Accepts a path to an uploaded CSV file.
   Returns summary array.
   ============================================================ */

function fastwp_csv_run_import(string $csv_path, bool $dry_run): array
{
    // Load admin functions needed for media
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // Read file, strip UTF-8 BOM if present
    $raw = file_get_contents($csv_path);
    if ($raw === false) {
        return ['error' => 'Не удалось прочитать файл'];
    }
    $raw = ltrim($raw, "\xEF\xBB\xBF"); // strip BOM

    // Detect delimiter: semicolon (Excel/Russian locale) or comma
    $first_line = strtok($raw, "\n");
    $delimiter  = substr_count($first_line, ';') > substr_count($first_line, ',') ? ';' : ',';

    // Parse CSV into rows
    $rows      = [];
    $stream    = fopen('data://text/plain,' . rawurlencode($raw), 'r');
    $header    = null;

    while (($line = fgetcsv($stream, 0, $delimiter)) !== false) {
        if ($header === null) {
            $header = array_map('trim', $line);
            continue;
        }
        if (count($line) !== count($header)) {
            continue; // skip malformed rows
        }
        $rows[] = array_combine($header, $line);
    }
    fclose($stream);

    if (empty($rows)) {
        return ['error' => 'CSV не содержит строк данных (только заголовок или пуст)'];
    }

    $results  = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 0, 'details' => []];

    foreach ($rows as $i => $row) {
        $res = fastwp_csv_process_row($row, $dry_run);
        $results['details'][] = ['row' => $i + 2, 'result' => $res];
        $key = $res['status'];
        if ($key === 'dry_run') {
            $key = str_contains($res['message'], 'создан') ? 'created' : 'updated';
        }
        if (isset($results[$key])) {
            $results[$key]++;
        }
    }

    return $results;
}

/* ============================================================
   Sample CSV download
   ============================================================ */

add_action('admin_post_fastwp_csv_sample', 'fastwp_csv_download_sample');

function fastwp_csv_download_sample(): void
{
    if (!current_user_can('manage_options')) {
        wp_die('Нет доступа', 403);
    }
    check_admin_referer('fastwp_csv_sample');

    // Collect dynamic field keys
    $dynamic_cols = array_map(
        static fn($f) => str_replace('movie_', '', $f['key']),
        fastwp_get_movie_fields()
    );

    $fixed_cols = [
        'title', 'slug', 'content', 'excerpt', 'status', 'date',
        'categories', 'tags',
        'poster', 'gallery_2', 'gallery_3', 'gallery_4',
        'actors', 'directors', 'rating',
        'rent_1', 'rent_3', 'rent_5',
        'buy_week', 'buy_month', 'buy_forever',
        'coupon', 'update_existing',
    ];
    $all_cols = array_merge($fixed_cols, array_diff($dynamic_cols, $fixed_cols));

    $sample_row = [
        'title'          => 'Пример фильма',
        'slug'           => 'primer-filma',
        'content'        => 'Описание фильма. Поддерживает <b>HTML</b>.',
        'excerpt'        => 'Краткое описание',
        'status'         => 'publish',
        'date'           => '2024-01-15',
        'categories'     => 'films,action',
        'tags'           => 'боевик,кино',
        'poster'         => 'primer_poster.jpg',
        'gallery_2'      => 'primer_frame2.jpg',
        'gallery_3'      => 'primer_frame3.jpg',
        'gallery_4'      => 'primer_frame4.jpg',
        'actors'         => 'Иван Иванов, Пётр Петров',
        'directors'      => 'Режиссёр Имя',
        'rating'         => '8.5',
        'rent_1'         => '99',
        'rent_3'         => '199',
        'rent_5'         => '299',
        'buy_week'       => '399',
        'buy_month'      => '699',
        'buy_forever'    => '999',
        'coupon'         => 'SKIDKA20',
        'update_existing' => 'no',
        'duration'       => '1ч 55м',
        'quality'        => 'FullHD',
        'translation'    => 'Дубляж',
    ];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="fastwp-import-sample.csv"');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $all_cols);

    $row_data = [];
    foreach ($all_cols as $col) {
        $row_data[] = $sample_row[$col] ?? '';
    }
    fputcsv($out, $row_data);
    fclose($out);
    exit;
}

/* ============================================================
   Admin page
   ============================================================ */

function fastwp_csv_import_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    fastwp_csv_ensure_import_dir();

    $import_dir     = fastwp_csv_import_dir();
    $import_dir_url = content_url('uploads/fastwp-import');
    $results        = null;
    $dry_run        = false;
    $notice         = '';

    // Handle form submission
    if (
        isset($_POST['fastwp_csv_import_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fastwp_csv_import_nonce'])), 'fastwp_csv_import')
    ) {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $notice = 'error:Ошибка загрузки файла. Убедитесь, что файл выбран и его размер не превышает лимит PHP.';
        } else {
            $file      = $_FILES['csv_file'];
            $ext       = strtolower(pathinfo(sanitize_file_name($file['name']), PATHINFO_EXTENSION));
            $dry_run   = !empty($_POST['dry_run']);

            if (!in_array($ext, ['csv', 'txt'], true)) {
                $notice = 'error:Допускаются только файлы CSV (.csv или .txt).';
            } else {
                $tmp_path = $file['tmp_name'];
                $results  = fastwp_csv_run_import($tmp_path, $dry_run);
                if (isset($results['error'])) {
                    $notice = 'error:' . esc_html($results['error']);
                    $results = null;
                } else {
                    $notice = 'success:Импорт ' . ($dry_run ? '(пробный) ' : '') . 'завершён.';
                }
            }
        }
    }

    // Collect dynamic field columns for documentation table
    $dynamic_fields = fastwp_get_movie_fields();

    ?>
    <div class="wrap" style="max-width:960px">
        <h1><?php esc_html_e('Импорт фильмов из CSV', 'fastwp'); ?></h1>

        <?php if ($notice) :
            [$type, $msg] = explode(':', $notice, 2); ?>
        <div class="notice notice-<?php echo $type === 'error' ? 'error' : 'success'; ?> is-dismissible">
            <p><?php echo esc_html($msg); ?></p>
        </div>
        <?php endif; ?>

        <!-- ===== STEP 1: Prepare images ===== -->
        <div class="card" style="max-width:100%;margin-bottom:1.5rem;padding:1.25rem 1.5rem">
            <h2 style="margin-top:0;font-size:1rem">
                <?php esc_html_e('Шаг 1 — Загрузите изображения на сервер', 'fastwp'); ?>
            </h2>
            <p style="margin:0 0 0.75rem">
                <?php esc_html_e('Загрузите постеры и кадры через FTP в специальную папку для импорта:', 'fastwp'); ?>
            </p>

            <table style="border-collapse:collapse;width:100%;max-width:720px">
                <tr>
                    <td style="width:130px;padding:6px 12px 6px 0;color:#555;font-size:.875em;vertical-align:top;white-space:nowrap">
                        <?php esc_html_e('Путь на сервере:', 'fastwp'); ?>
                    </td>
                    <td style="padding:6px 0">
                        <code style="display:block;padding:.4rem .75rem;background:#f0f0f0;border-radius:3px;font-size:.9em;word-break:break-all">
                            <?php echo esc_html($import_dir); ?>
                        </code>
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 12px 6px 0;color:#555;font-size:.875em;vertical-align:top;white-space:nowrap">
                        <?php esc_html_e('Относительный путь:', 'fastwp'); ?>
                    </td>
                    <td style="padding:6px 0">
                        <code style="display:block;padding:.4rem .75rem;background:#f0f0f0;border-radius:3px;font-size:.9em">
                            wp-content/uploads/fastwp-import/
                        </code>
                        <span style="font-size:.8em;color:#555">
                            <?php esc_html_e('(относительно корневой папки WordPress)', 'fastwp'); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 12px 6px 0;color:#555;font-size:.875em;vertical-align:top;white-space:nowrap">
                        <?php esc_html_e('Файлов в папке:', 'fastwp'); ?>
                    </td>
                    <td style="padding:6px 0;font-size:.875em">
                        <?php
                        $image_count = 0;
                        if (is_dir($import_dir)) {
                            $image_count = count(glob($import_dir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: []);
                        }
                        if ($image_count > 0) {
                            echo '<span style="color:#00a32a;font-weight:600">✔ ' . $image_count . ' ' . esc_html__('изображений найдено', 'fastwp') . '</span>';
                        } else {
                            echo '<span style="color:#555">0 — ' . esc_html__('папка пуста', 'fastwp') . '</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:6px 12px 6px 0;color:#555;font-size:.875em;vertical-align:top;white-space:nowrap">
                        <?php esc_html_e('Права доступа:', 'fastwp'); ?>
                    </td>
                    <td style="padding:6px 0;font-size:.875em">
                        <?php if (is_writable($import_dir)) : ?>
                        <span style="color:#00a32a;font-weight:600">✔ <?php esc_html_e('Папка доступна для записи', 'fastwp'); ?></span>
                        <?php else : ?>
                        <span style="color:#d63638;font-weight:600">⚠ <?php esc_html_e('Нет прав на запись', 'fastwp'); ?></span>
                        <code style="margin-left:.5rem;font-size:.85em">chmod 755 wp-content/uploads/fastwp-import</code>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <div style="margin-top:1rem;padding:.75rem 1rem;background:#e8f4fd;border-left:4px solid #0073aa;border-radius:0 3px 3px 0;max-width:720px">
                <strong style="font-size:.875em"><?php esc_html_e('Как подключиться по FTP:', 'fastwp'); ?></strong>
                <ol style="margin:.5rem 0 0;padding-left:1.25rem;font-size:.875em;color:#333">
                    <li><?php esc_html_e('Откройте FTP-клиент (FileZilla, WinSCP и др.)', 'fastwp'); ?></li>
                    <li><?php printf(
                        esc_html__('Перейдите в папку: %s', 'fastwp'),
                        '<code>wp-content/uploads/fastwp-import/</code>'
                    ); ?></li>
                    <li><?php esc_html_e('Загрузите все изображения (jpg, png, webp)', 'fastwp'); ?></li>
                    <li><?php esc_html_e('В CSV-файле указывайте только имя файла без пути:', 'fastwp'); ?>
                        <code>poster.jpg</code> &nbsp;—&nbsp; <?php esc_html_e('не', 'fastwp'); ?> <code>/uploads/fastwp-import/poster.jpg</code>
                    </li>
                </ol>
            </div>
        </div>

        <!-- ===== STEP 2: Prepare CSV ===== -->
        <div class="card" style="max-width:100%;margin-bottom:1.5rem;padding:1.25rem 1.5rem">
            <h2 style="margin-top:0;font-size:1rem">
                <?php esc_html_e('Шаг 2 — Подготовьте CSV файл', 'fastwp'); ?>
            </h2>
            <p><?php esc_html_e('Первая строка файла — обязательный заголовок. Кодировка: UTF-8. Разделитель: запятая (,) или точка с запятой (;).', 'fastwp'); ?></p>

            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fastwp_csv_sample'), 'fastwp_csv_sample')); ?>"
               class="button">
                ↓ <?php esc_html_e('Скачать пример CSV', 'fastwp'); ?>
            </a>

            <h3 style="margin:1.25rem 0 0.5rem;font-size:.95rem"><?php esc_html_e('Описание колонок:', 'fastwp'); ?></h3>
            <table class="widefat striped" style="font-size:.85em">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Колонка', 'fastwp'); ?></th>
                        <th><?php esc_html_e('Обязательная', 'fastwp'); ?></th>
                        <th><?php esc_html_e('Описание', 'fastwp'); ?></th>
                        <th><?php esc_html_e('Пример', 'fastwp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cols = [
                        ['title',          'Да',  'Название фильма', 'Крёстный отец'],
                        ['slug',           'Нет', 'URL слаг. Если пусто — генерируется из названия', 'kryostny-otec'],
                        ['content',        'Нет', 'Полное описание. Поддерживает HTML теги', '<b>Легендарная</b> семейная сага.'],
                        ['excerpt',        'Нет', 'Краткое описание (без HTML)', 'Классика мирового кино.'],
                        ['status',         'Нет', 'Статус: publish, draft, private. По умолчанию: publish', 'publish'],
                        ['date',           'Нет', 'Дата публикации в формате YYYY-MM-DD', '2024-01-15'],
                        ['categories',     'Нет', 'Слаги или названия рубрик через запятую', 'films,drama'],
                        ['tags',           'Нет', 'Теги через запятую', 'классика,драма'],
                        ['poster',         'Нет', 'Имя файла постера (из папки fastwp-import)', 'godfather_poster.jpg'],
                        ['gallery_2',      'Нет', 'Кадр 2 — имя файла', 'godfather_frame2.jpg'],
                        ['gallery_3',      'Нет', 'Кадр 3 — имя файла', 'godfather_frame3.jpg'],
                        ['gallery_4',      'Нет', 'Кадр 4 — имя файла', 'godfather_frame4.jpg'],
                        ['actors',         'Нет', 'Актёры через запятую или с новой строки', 'Аль Пачино, Марлон Брандо'],
                        ['directors',      'Нет', 'Режиссёры', 'Фрэнсис Форд Коппола'],
                        ['rating',         'Нет', 'Рейтинг (число, 0–10)', '9.2'],
                        ['rent_1',         'Нет', 'Цена аренды 1 просмотр (₽)', '99'],
                        ['rent_3',         'Нет', 'Цена аренды 3 просмотра (₽)', '199'],
                        ['rent_5',         'Нет', 'Цена аренды 5 просмотров (₽)', '299'],
                        ['buy_week',       'Нет', 'Покупка на неделю (₽)', '399'],
                        ['buy_month',      'Нет', 'Покупка на месяц (₽)', '699'],
                        ['buy_forever',    'Нет', 'Покупка навсегда (₽)', '999'],
                        ['coupon',         'Нет', 'Промокод на скидку', 'SUMMER20'],
                        ['update_existing','Нет', 'Обновить фильм если уже существует: yes / no', 'no'],
                    ];

                    foreach ($dynamic_fields as $field) {
                        $csv_col = str_replace('movie_', '', $field['key']);
                        $cols[] = [$csv_col, 'Нет', esc_html($field['label']), ''];
                    }

                    foreach ($cols as [$col, $req, $desc, $ex]) : ?>
                    <tr>
                        <td><code><?php echo esc_html($col); ?></code></td>
                        <td><?php echo $req === 'Да' ? '<strong>Да</strong>' : 'Нет'; ?></td>
                        <td><?php echo esc_html($desc); ?></td>
                        <td style="color:#555"><?php echo esc_html($ex); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:0.75rem;font-size:.85em;color:#555">
                <strong><?php esc_html_e('Подсказка:', 'fastwp'); ?></strong>
                <?php esc_html_e('Для создания CSV откройте Excel, заполните таблицу и сохраните как «CSV UTF-8 (с разделителями-запятыми)». В LibreOffice Calc при экспорте выберите UTF-8 и разделитель «;» или «,».', 'fastwp'); ?>
            </p>
        </div>

        <!-- ===== STEP 3: Upload & Import ===== -->
        <div class="card" style="max-width:100%;margin-bottom:1.5rem;padding:1.25rem 1.5rem">
            <h2 style="margin-top:0;font-size:1rem">
                <?php esc_html_e('Шаг 3 — Загрузите CSV и запустите импорт', 'fastwp'); ?>
            </h2>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('fastwp_csv_import', 'fastwp_csv_import_nonce'); ?>
                <table class="form-table" role="presentation" style="max-width:600px">
                    <tr>
                        <th scope="row"><?php esc_html_e('CSV файл', 'fastwp'); ?></th>
                        <td>
                            <input type="file" name="csv_file" accept=".csv,.txt" required style="max-width:400px">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Режим', 'fastwp'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="dry_run" value="1">
                                <?php esc_html_e('Пробный запуск (только проверить, ничего не создавать)', 'fastwp'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <p>
                    <button type="submit" class="button button-primary button-large">
                        <?php esc_html_e('Начать импорт', 'fastwp'); ?>
                    </button>
                </p>
            </form>
        </div>

        <!-- ===== Import results ===== -->
        <?php if ($results !== null) : ?>
        <div class="card" style="max-width:100%;padding:1.25rem 1.5rem">
            <h2 style="margin-top:0;font-size:1rem">
                <?php echo $dry_run
                    ? esc_html__('Результаты пробного запуска', 'fastwp')
                    : esc_html__('Результаты импорта', 'fastwp'); ?>
            </h2>

            <p>
                <strong style="color:#00a32a">
                    ✔ <?php echo esc_html__('Создано:', 'fastwp'); ?> <?php echo (int) $results['created']; ?>
                </strong>
                &nbsp;&nbsp;
                <strong style="color:#0073aa">
                    ↺ <?php echo esc_html__('Обновлено:', 'fastwp'); ?> <?php echo (int) $results['updated']; ?>
                </strong>
                &nbsp;&nbsp;
                <span style="color:#555">
                    — <?php echo esc_html__('Пропущено:', 'fastwp'); ?> <?php echo (int) $results['skipped']; ?>
                </span>
                &nbsp;&nbsp;
                <strong style="color:#d63638">
                    ✘ <?php echo esc_html__('Ошибок:', 'fastwp'); ?> <?php echo (int) $results['error']; ?>
                </strong>
            </p>

            <table class="widefat striped" style="font-size:.85em">
                <thead>
                    <tr>
                        <th style="width:60px"><?php esc_html_e('Строка', 'fastwp'); ?></th>
                        <th style="width:90px"><?php esc_html_e('Статус', 'fastwp'); ?></th>
                        <th><?php esc_html_e('Сообщение', 'fastwp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results['details'] as $detail) :
                        $r      = $detail['result'];
                        $status = $r['status'];
                        $color  = match($status) {
                            'created', 'dry_run' => '#00a32a',
                            'updated'            => '#0073aa',
                            'error'              => '#d63638',
                            default              => '#555',
                        };
                        $icon = match($status) {
                            'created', 'dry_run' => '✔',
                            'updated'            => '↺',
                            'error'              => '✘',
                            default              => '—',
                        };
                    ?>
                    <tr>
                        <td><?php echo (int) $detail['row']; ?></td>
                        <td style="color:<?php echo $color; ?>;font-weight:600">
                            <?php echo esc_html($icon . ' ' . ucfirst($status)); ?>
                        </td>
                        <td><?php echo esc_html($r['message']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
    <?php
}
