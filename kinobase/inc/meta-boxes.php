<?php
/**
 * KinoBase Movie Meta Boxes
 *
 * @package KinoBase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', 'kinobase_register_meta_boxes');

function kinobase_register_meta_boxes(): void
{
    $post_types = ['post', 'movie'];

    add_meta_box(
        'kinobase_gallery',
        __('Постеры и фото фильма', 'kinobase'),
        'kinobase_render_gallery_box',
        $post_types,
        'normal',
        'high'
    );

    add_meta_box(
        'kinobase_movie_info',
        __('Информация о фильме', 'kinobase'),
        'kinobase_render_info_box',
        $post_types,
        'normal',
        'high'
    );

    add_meta_box(
        'kinobase_additional',
        __('Актёры, режиссёры, рейтинг, сборы', 'kinobase'),
        'kinobase_render_additional_box',
        $post_types,
        'normal',
        'high'
    );

    add_meta_box(
        'kinobase_pricing',
        __('Цены (Аренда и Покупка)', 'kinobase'),
        'kinobase_render_pricing_box',
        $post_types,
        'normal',
        'default'
    );

    add_meta_box(
        'kinobase_coupon',
        __('Купон на скидку', 'kinobase'),
        'kinobase_render_coupon_box',
        $post_types,
        'side',
        'default'
    );
}

/**
 * Gallery meta box — unlimited photos, drag-and-drop reorder
 */
function kinobase_render_gallery_box(WP_Post $post): void
{
    wp_nonce_field('kinobase_save_meta', 'kinobase_nonce');

    $gallery = get_post_meta($post->ID, 'movie_gallery', true);
    if (!is_array($gallery)) {
        $gallery = [];
    }
    $gallery = array_filter(array_map('intval', $gallery));
    ?>
    <div class="kinobase-gallery-meta">
        <p style="margin-bottom:10px;color:#666;">
            <?php esc_html_e('Первое фото — главный постер. Добавьте сколько угодно фотографий.', 'kinobase'); ?>
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
            <?php esc_html_e('+ Добавить фото', 'kinobase'); ?>
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
 * Additional info meta box (rating, actors, directors, box office)
 */
function kinobase_render_additional_box(WP_Post $post): void
{
    $rating    = esc_attr((string) get_post_meta($post->ID, 'movie_rating', true));
    $actors    = esc_textarea((string) get_post_meta($post->ID, 'movie_actors', true));
    $directors = esc_textarea((string) get_post_meta($post->ID, 'movie_directors', true));
    $box_raw   = get_post_meta($post->ID, 'movie_box_office', true);
    $box_rows  = is_array($box_raw) ? $box_raw : [];
    ?>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px;">
        <tr>
            <td style="width:180px;padding:8px 12px 8px 0;font-weight:600;vertical-align:middle;">
                <?php esc_html_e('Рейтинг (0–10)', 'kinobase'); ?>
            </td>
            <td style="padding:4px 0;">
                <input type="number" name="movie_rating" value="<?php echo $rating; ?>"
                       min="0" max="10" step="0.1" style="width:100px;" class="regular-text">
            </td>
        </tr>
        <tr>
            <td style="padding:8px 12px 8px 0;font-weight:600;vertical-align:top;">
                <?php esc_html_e('Актёры (через запятую)', 'kinobase'); ?>
            </td>
            <td style="padding:4px 0;">
                <textarea name="movie_actors" rows="3" style="width:100%;"><?php echo $actors; ?></textarea>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 12px 8px 0;font-weight:600;vertical-align:top;">
                <?php esc_html_e('Режиссёры (через запятую)', 'kinobase'); ?>
            </td>
            <td style="padding:4px 0;">
                <textarea name="movie_directors" rows="2" style="width:100%;"><?php echo $directors; ?></textarea>
            </td>
        </tr>
    </table>

    <h4 style="margin-bottom:10px;border-bottom:2px solid #ddd;padding-bottom:6px;">
        <?php esc_html_e('Кассовые сборы (по странам)', 'kinobase'); ?>
    </h4>
    <div id="kb-box-office-rows">
        <?php foreach ($box_rows as $i => $row) :
            $country = esc_attr($row['country'] ?? '');
            $amount  = esc_attr($row['amount']  ?? '');
            ?>
            <div class="kb-bo-row" style="display:flex;gap:8px;margin-bottom:6px;">
                <input type="text" name="movie_box_office[<?php echo $i; ?>][country]"
                       value="<?php echo $country; ?>" placeholder="Страна" style="width:180px;">
                <input type="text" name="movie_box_office[<?php echo $i; ?>][amount]"
                       value="<?php echo $amount; ?>" placeholder="Сумма ($100M)" style="flex:1;">
                <button type="button" class="button button-small kb-bo-remove" style="color:#a00;">✕</button>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="button" id="kb-bo-add" style="margin-top:4px;">
        <?php esc_html_e('+ Добавить строку', 'kinobase'); ?>
    </button>
    <script>
    jQuery(function($) {
        var idx = <?php echo count($box_rows); ?>;
        $('#kb-bo-add').on('click', function() {
            var row = '<div class="kb-bo-row" style="display:flex;gap:8px;margin-bottom:6px;">'
                + '<input type="text" name="movie_box_office['+idx+'][country]" placeholder="Страна" style="width:180px;">'
                + '<input type="text" name="movie_box_office['+idx+'][amount]" placeholder="Сумма ($100M)" style="flex:1;">'
                + '<button type="button" class="button button-small kb-bo-remove" style="color:#a00;">✕</button>'
                + '</div>';
            $('#kb-box-office-rows').append(row);
            idx++;
        });
        $(document).on('click', '.kb-bo-remove', function() {
            $(this).closest('.kb-bo-row').remove();
        });
    });
    </script>
    <?php
}

/**
 * Movie info meta box
 */
function kinobase_render_info_box(WP_Post $post): void
{
    $fields = [
        'movie_year'        => __('Год выпуска', 'kinobase'),
        'movie_genre'       => __('Жанр(ы)', 'kinobase'),
        'movie_duration'    => __('Длительность', 'kinobase'),
        'movie_quality'     => __('Качество (HD, 4K…)', 'kinobase'),
        'movie_translation' => __('Перевод', 'kinobase'),
    ];

    echo '<table style="width:100%;border-collapse:collapse;">';
    foreach ($fields as $key => $label) {
        $value = esc_attr((string) get_post_meta($post->ID, $key, true));
        echo '<tr>';
        echo '<td style="width:180px;padding:8px 12px 8px 0;vertical-align:middle;font-weight:600;">'
            . esc_html($label) . '</td>';
        echo '<td style="padding:4px 0;"><input type="text" name="' . esc_attr($key) . '" value="'
            . $value . '" style="width:100%;" class="regular-text"></td>';
        echo '</tr>';
    }
    echo '</table>';
}

/**
 * Pricing meta box
 */
function kinobase_render_pricing_box(WP_Post $post): void
{
    $rent_fields = [
        'movie_rent_1' => __('Аренда 1 просмотр (₽)', 'kinobase'),
        'movie_rent_3' => __('Аренда 3 просмотра (₽)', 'kinobase'),
        'movie_rent_5' => __('Аренда 5 просмотров (₽)', 'kinobase'),
    ];
    $buy_fields = [
        'movie_buy_week'    => __('Покупка на неделю (₽)', 'kinobase'),
        'movie_buy_month'   => __('Покупка на месяц (₽)', 'kinobase'),
        'movie_buy_forever' => __('Покупка навсегда (₽)', 'kinobase'),
    ];

    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">';

    // Rent
    echo '<div><h4 style="margin-bottom:10px;border-bottom:2px solid #ddd;padding-bottom:6px;">'
        . __('Аренда', 'kinobase') . '</h4>';
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
        . __('Покупка', 'kinobase') . '</h4>';
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
function kinobase_render_coupon_box(WP_Post $post): void
{
    $coupon = esc_attr((string) get_post_meta($post->ID, 'movie_coupon', true));
    $views  = (int) get_post_meta($post->ID, 'movie_views', true);

    echo '<p><strong>' . __('Код купона:', 'kinobase') . '</strong></p>';
    echo '<input type="text" name="movie_coupon" value="' . $coupon
        . '" style="width:100%;text-transform:uppercase;" placeholder="SUPER10">';
    echo '<p style="margin-top:12px;"><strong>' . __('Просмотров карточки:', 'kinobase') . '</strong> '
        . number_format($views) . '</p>';
}

/**
 * Save all meta boxes
 */
add_action('save_post', 'kinobase_save_meta_boxes', 10, 2);

function kinobase_save_meta_boxes(int $post_id, WP_Post $post): void
{
    // Security checks
    if (!isset($_POST['kinobase_nonce'])) return;
    if (!wp_verify_nonce($_POST['kinobase_nonce'], 'kinobase_save_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (!in_array($post->post_type, ['post', 'movie'], true)) return;

    // Gallery (unlimited photos)
    $gallery = isset($_POST['movie_gallery']) && is_array($_POST['movie_gallery'])
        ? array_values(array_filter(array_map('absint', $_POST['movie_gallery'])))
        : [];
    update_post_meta($post_id, 'movie_gallery', $gallery);

    // Text / number fields
    $text_fields = ['movie_year', 'movie_genre', 'movie_duration', 'movie_quality', 'movie_translation', 'movie_coupon'];
    foreach ($text_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }

    // Textarea fields
    $textarea_fields = ['movie_actors', 'movie_directors'];
    foreach ($textarea_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_textarea_field($_POST[$field]));
        }
    }

    // Rating (float 0-10)
    if (isset($_POST['movie_rating'])) {
        $rating = (float) $_POST['movie_rating'];
        $rating = max(0.0, min(10.0, $rating));
        update_post_meta($post_id, 'movie_rating', round($rating, 1));
    }

    // Box office rows
    if (isset($_POST['movie_box_office']) && is_array($_POST['movie_box_office'])) {
        $rows = [];
        foreach ($_POST['movie_box_office'] as $row) {
            $country = sanitize_text_field($row['country'] ?? '');
            $amount  = sanitize_text_field($row['amount']  ?? '');
            if ($country !== '' || $amount !== '') {
                $rows[] = ['country' => $country, 'amount' => $amount];
            }
        }
        update_post_meta($post_id, 'movie_box_office', $rows);
    }

    // Price fields (integers)
    $price_fields = ['movie_rent_1', 'movie_rent_3', 'movie_rent_5', 'movie_buy_week', 'movie_buy_month', 'movie_buy_forever'];
    foreach ($price_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, absint($_POST[$field]));
        }
    }
}

// Enqueue media uploader on post edit screen
add_action('admin_enqueue_scripts', function (string $hook): void {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
    wp_enqueue_media();
});
