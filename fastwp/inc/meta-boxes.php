<?php
/**
 * FastWP Movie Meta Boxes
 *
 * @package FastWP
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', 'fastwp_register_meta_boxes');

function fastwp_register_meta_boxes(): void
{
    $post_types = ['post', 'movie'];

    add_meta_box(
        'fastwp_gallery',
        __('Постеры и фото фильма', 'fastwp'),
        'fastwp_render_gallery_box',
        $post_types,
        'normal',
        'high'
    );

    add_meta_box(
        'fastwp_movie_info',
        __('Информация о фильме', 'fastwp'),
        'fastwp_render_info_box',
        $post_types,
        'normal',
        'high'
    );

    add_meta_box(
        'fastwp_rating',
        __('Рейтинг', 'fastwp'),
        'fastwp_render_rating_box',
        $post_types,
        'normal',
        'high'
    );

    add_meta_box(
        'fastwp_pricing',
        __('Цены (Аренда и Покупка)', 'fastwp'),
        'fastwp_render_pricing_box',
        $post_types,
        'normal',
        'default'
    );

    add_meta_box(
        'fastwp_coupon',
        __('Купон на скидку', 'fastwp'),
        'fastwp_render_coupon_box',
        $post_types,
        'normal',
        'default'
    );

    add_meta_box(
        'fastwp_seo',
        __('Метатеги (SEO)', 'fastwp'),
        'fastwp_render_seo_box',
        $post_types,
        'normal',
        'default'
    );
}

/**
 * Gallery meta box — unlimited photos, drag-and-drop reorder
 */
function fastwp_render_gallery_box(WP_Post $post): void
{
    wp_nonce_field('fastwp_save_meta', 'fastwp_nonce');

    $gallery = get_post_meta($post->ID, 'movie_gallery', true);
    if (!is_array($gallery)) {
        $gallery = [];
    }
    $gallery = array_filter(array_map('intval', $gallery));
    ?>
    <div class="fastwp-gallery-meta">
        <p style="margin-bottom:10px;color:#666;">
            <?php esc_html_e('Первое фото — главный постер. Добавьте сколько угодно фотографий.', 'fastwp'); ?>
        </p>
        <div id="kb-gallery-wrap" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
            <?php foreach ($gallery as $img_id) :
                $img_id = (int) $img_id;
                if (!$img_id) continue;
                $thumb = wp_get_attachment_image_src($img_id, 'thumbnail');
                if (!$thumb) continue;
            ?>
            <div class="kb-gallery-slot" style="position:relative;border:2px solid #ccc;border-radius:6px;overflow:hidden;width:90px;height:90px;cursor:move;">
                <img src="<?php echo esc_url($thumb[0]); ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                <input type="hidden" name="movie_gallery[]" value="<?php echo esc_attr((string) $img_id); ?>">
                <button type="button" class="kb-remove-gallery-img"
                        style="position:absolute;top:2px;right:2px;background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:12px;line-height:1;padding:0;">✕</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button button-primary" id="kb-gallery-add">
            <?php esc_html_e('+ Добавить фото', 'fastwp'); ?>
        </button>
    </div>
    <script>
    jQuery(function($) {
        $('#kb-gallery-add').on('click', function() {
            var frame = wp.media({
                title: 'Выберите фото',
                button: { text: 'Добавить выбранные' },
                multiple: true,
                library: { type: 'image' }
            });
            frame.on('select', function() {
                frame.state().get('selection').each(function(att) {
                    var a = att.toJSON();
                    var thumb = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
                    var slot = $('<div class="kb-gallery-slot" style="position:relative;border:2px solid #ccc;border-radius:6px;overflow:hidden;width:90px;height:90px;cursor:move;">'
                        + '<img src="' + thumb + '" style="width:100%;height:100%;object-fit:cover;" alt="">'
                        + '<input type="hidden" name="movie_gallery[]" value="' + a.id + '">'
                        + '<button type="button" class="kb-remove-gallery-img" style="position:absolute;top:2px;right:2px;background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:12px;line-height:1;padding:0;">✕</button>'
                        + '</div>');
                    $('#kb-gallery-wrap').append(slot);
                });
            });
            frame.open();
        });

        $(document).on('click', '.kb-remove-gallery-img', function() {
            $(this).closest('.kb-gallery-slot').remove();
        });

        // Sortable (drag-and-drop reorder) if jQuery UI available
        if ($.fn.sortable) {
            $('#kb-gallery-wrap').sortable({ items: '.kb-gallery-slot', tolerance: 'pointer' });
        }
    });
    </script>
    <?php
}

/**
 * Rating meta box
 */
function fastwp_render_rating_box(WP_Post $post): void
{
    $rating = esc_attr((string) get_post_meta($post->ID, 'movie_rating', true));
    ?>
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="width:180px;padding:8px 12px 8px 0;font-weight:600;vertical-align:middle;">
                <?php esc_html_e('Рейтинг (0–10)', 'fastwp'); ?>
            </td>
            <td style="padding:4px 0;">
                <input type="number" name="movie_rating" value="<?php echo $rating; ?>"
                       min="0" max="10" step="0.1" style="width:100px;" class="regular-text">
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Movie info meta box — renders fields from field-builder (dynamic).
 */
function fastwp_render_info_box(WP_Post $post): void
{
    $fields = fastwp_get_movie_fields();

    if (empty($fields)) {
        echo '<p style="color:#666;">' . esc_html__('Нет полей. Добавьте их в Фильмы → Мета-боксы.', 'fastwp') . '</p>';
        return;
    }

    echo '<table style="width:100%;border-collapse:collapse;">';
    foreach ($fields as $f) {
        $key   = esc_attr($f['key']);
        $label = esc_html($f['label']);
        $type  = $f['type'] ?? 'text';
        $value = get_post_meta($post->ID, $f['key'], true);

        echo '<tr>';
        echo '<td style="width:180px;padding:8px 12px 8px 0;vertical-align:middle;font-weight:600;">' . $label . '</td>';
        echo '<td style="padding:4px 0;">';

        if ($type === 'textarea') {
            echo '<textarea name="' . $key . '" rows="3" style="width:100%;">'
                . esc_textarea((string) $value) . '</textarea>';
        } elseif ($type === 'number') {
            echo '<input type="number" name="' . $key . '" value="' . esc_attr((string) $value)
                . '" style="width:120px;" class="regular-text">';
        } else {
            echo '<input type="text" name="' . $key . '" value="' . esc_attr((string) $value)
                . '" style="width:100%;" class="regular-text">';
        }

        echo '</td>';
        echo '</tr>';
    }
    echo '</table>';
}

/**
 * Pricing meta box
 */
function fastwp_render_pricing_box(WP_Post $post): void
{
    $rent_fields = [
        'movie_rent_1' => __('Аренда 1 просмотр (₽)', 'fastwp'),
        'movie_rent_3' => __('Аренда 3 просмотра (₽)', 'fastwp'),
        'movie_rent_5' => __('Аренда 5 просмотров (₽)', 'fastwp'),
    ];
    $buy_fields = [
        'movie_buy_week'    => __('Покупка на неделю (₽)', 'fastwp'),
        'movie_buy_month'   => __('Покупка на месяц (₽)', 'fastwp'),
        'movie_buy_forever' => __('Покупка навсегда (₽)', 'fastwp'),
    ];

    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">';

    // Rent
    echo '<div><h4 style="margin-bottom:10px;border-bottom:2px solid #ddd;padding-bottom:6px;">'
        . __('Аренда', 'fastwp') . '</h4>';
    echo '<table style="width:100%;">';
    foreach ($rent_fields as $key => $label) {
        $value = esc_attr((string) get_post_meta($post->ID, $key, true));
        echo '<tr><td style="padding:6px 8px 6px 0;font-size:13px;width:160px;">' . esc_html($label) . '</td>';
        echo '<td><input type="number" name="' . esc_attr($key) . '" value="' . $value
            . '" min="0" style="width:100px;"></td></tr>';
    }
    echo '</table></div>';

    // Buy
    echo '<div><h4 style="margin-bottom:10px;border-bottom:2px solid #ddd;padding-bottom:6px;">'
        . __('Покупка', 'fastwp') . '</h4>';
    echo '<table style="width:100%;">';
    foreach ($buy_fields as $key => $label) {
        $value = esc_attr((string) get_post_meta($post->ID, $key, true));
        echo '<tr><td style="padding:6px 8px 6px 0;font-size:13px;width:160px;">' . esc_html($label) . '</td>';
        echo '<td><input type="number" name="' . esc_attr($key) . '" value="' . $value
            . '" min="0" style="width:100px;"></td></tr>';
    }
    echo '</table></div>';

    echo '</div>';
}

/**
 * Coupon meta box
 */
function fastwp_render_coupon_box(WP_Post $post): void
{
    $coupon = esc_attr((string) get_post_meta($post->ID, 'movie_coupon', true));
    ?>
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="width:180px;padding:8px 12px 8px 0;font-weight:600;vertical-align:middle;">
                <?php esc_html_e('Код купона:', 'fastwp'); ?>
            </td>
            <td style="padding:4px 0;">
                <input type="text" name="movie_coupon" value="<?php echo $coupon; ?>"
                       style="width:100%;text-transform:uppercase;" class="regular-text"
                       placeholder="SUPER10">
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Save all meta boxes
 */
add_action('save_post', 'fastwp_save_meta_boxes', 10, 2);

function fastwp_save_meta_boxes(int $post_id, WP_Post $post): void
{
    // Security checks
    if (!isset($_POST['fastwp_nonce'])) return;
    if (!wp_verify_nonce($_POST['fastwp_nonce'], 'fastwp_save_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!in_array($post->post_type, ['post', 'movie'], true)) return;

    // Gallery (unlimited photos)
    $gallery = isset($_POST['movie_gallery']) && is_array($_POST['movie_gallery'])
        ? array_values(array_filter(array_map('absint', $_POST['movie_gallery'])))
        : [];
    update_post_meta($post_id, 'movie_gallery', $gallery);

    // Text / number fields (fixed)
    $text_fields = ['movie_coupon'];
    foreach ($text_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }

    // Dynamic fields from field-builder
    foreach (fastwp_get_movie_fields() as $f) {
        $key  = $f['key'];
        $type = $f['type'] ?? 'text';
        if (!isset($_POST[$key])) continue;
        if ($type === 'textarea') {
            update_post_meta($post_id, $key, sanitize_textarea_field($_POST[$key]));
        } else {
            update_post_meta($post_id, $key, sanitize_text_field($_POST[$key]));
        }
    }

    // Rating (float 0-10)
    if (isset($_POST['movie_rating'])) {
        $rating = (float) $_POST['movie_rating'];
        $rating = max(0.0, min(10.0, $rating));
        update_post_meta($post_id, 'movie_rating', round($rating, 1));
    }

    // Price fields (integers)
    $price_fields = ['movie_rent_1', 'movie_rent_3', 'movie_rent_5', 'movie_buy_week', 'movie_buy_month', 'movie_buy_forever'];
    foreach ($price_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, absint($_POST[$field]));
        }
    }

    // SEO fields
    if (isset($_POST['kb_seo_title'])) {
        update_post_meta($post_id, '_kb_seo_title', sanitize_text_field($_POST['kb_seo_title']));
    }
    if (isset($_POST['kb_seo_description'])) {
        update_post_meta($post_id, '_kb_seo_description', sanitize_textarea_field($_POST['kb_seo_description']));
    }
}

/**
 * SEO meta box — custom title and description with variable support.
 */
function fastwp_render_seo_box(WP_Post $post): void
{
    $seo_title = esc_attr((string) get_post_meta($post->ID, '_kb_seo_title', true));
    $seo_desc  = esc_textarea((string) get_post_meta($post->ID, '_kb_seo_description', true));
    ?>
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="width:120px;padding:8px 12px 8px 0;font-weight:600;vertical-align:middle;">Title</td>
            <td style="padding:4px 0;">
                <input type="text" name="kb_seo_title" value="<?php echo $seo_title; ?>"
                       style="width:100%;" class="regular-text"
                       placeholder="<?php esc_attr_e('SEO заголовок страницы…', 'fastwp'); ?>">
                <p class="description" style="margin-top:4px;">
                    <?php esc_html_e('50–60 символов. Оставьте пустым — будет «Название · Сайт».', 'fastwp'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 12px 8px 0;font-weight:600;vertical-align:top;">Description</td>
            <td style="padding:4px 0;">
                <textarea name="kb_seo_description" rows="3"
                          style="width:100%;"
                          placeholder="<?php esc_attr_e('SEO описание для поисковиков…', 'fastwp'); ?>"><?php echo $seo_desc; ?></textarea>
                <p class="description" style="margin-top:4px;">
                    <?php esc_html_e('120–160 символов.', 'fastwp'); ?>
                </p>
            </td>
        </tr>
    </table>
    <div style="margin-top:12px;padding:10px 14px;background:rgba(255,77,77,0.07);border:1px solid rgba(255,77,77,0.25);border-radius:6px;font-size:0.875rem;line-height:1.8;">
        <strong><?php esc_html_e('Доступные переменные:', 'fastwp'); ?></strong><br>
        <code>%название%</code> — <?php esc_html_e('название фильма', 'fastwp'); ?> &nbsp;·&nbsp;
        <code>%год%</code> — <?php esc_html_e('год из рубрик', 'fastwp'); ?> &nbsp;·&nbsp;
        <code>%жанр%</code> — <?php esc_html_e('жанр из рубрик', 'fastwp'); ?> &nbsp;·&nbsp;
        <code>%качество%</code> — <?php esc_html_e('качество из рубрик/мета', 'fastwp'); ?> &nbsp;·&nbsp;
        <code>%длительность%</code> — <?php esc_html_e('длительность', 'fastwp'); ?> &nbsp;·&nbsp;
        <code>%сайт%</code> — <?php esc_html_e('название сайта', 'fastwp'); ?>
    </div>
    <p style="margin-top:10px;font-style:italic;color:#666;font-size:0.8125rem;">
        <?php esc_html_e('Пример: %название% — %год% — смотреть онлайн на %сайт%', 'fastwp'); ?>
    </p>
    <?php
}

// Enqueue media uploader on post edit screen
add_action('admin_enqueue_scripts', function (string $hook): void {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
    wp_enqueue_media();
});
