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
    $post_type = 'post';

    add_meta_box(
        'kinobase_gallery',
        __('Галерея фильма (4 фото)', 'kinobase'),
        'kinobase_render_gallery_box',
        $post_type,
        'normal',
        'high'
    );

    add_meta_box(
        'kinobase_movie_info',
        __('Информация о фильме', 'kinobase'),
        'kinobase_render_info_box',
        $post_type,
        'normal',
        'high'
    );

    add_meta_box(
        'kinobase_pricing',
        __('Цены (Аренда и Покупка)', 'kinobase'),
        'kinobase_render_pricing_box',
        $post_type,
        'normal',
        'default'
    );

    add_meta_box(
        'kinobase_coupon',
        __('Купон на скидку', 'kinobase'),
        'kinobase_render_coupon_box',
        $post_type,
        'side',
        'default'
    );
}

/**
 * Gallery meta box
 */
function kinobase_render_gallery_box(WP_Post $post): void
{
    wp_nonce_field('kinobase_save_meta', 'kinobase_nonce');

    $gallery = get_post_meta($post->ID, 'movie_gallery', true);
    if (!is_array($gallery)) {
        $gallery = ['', '', '', ''];
    }
    $gallery = array_pad($gallery, 4, '');

    ?>
    <div class="kinobase-gallery-meta">
        <p style="margin-bottom:10px;color:#666;"><?php esc_html_e('Загрузите 4 фотографии фильма. Первая — главный постер.', 'kinobase'); ?></p>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
            <?php for ($i = 0; $i < 4; $i++) :
                $img_id = (int) ($gallery[$i] ?? 0);
                $thumb = $img_id ? wp_get_attachment_image_src($img_id, 'thumbnail') : null;
                ?>
                <div class="kinobase-gallery-slot" style="border:2px dashed #ccc;border-radius:6px;padding:8px;text-align:center;min-height:120px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;">
                    <?php if ($thumb) : ?>
                        <img src="<?php echo esc_url($thumb[0]); ?>" style="max-height:100px;object-fit:contain;" alt="Photo <?php echo $i + 1; ?>">
                    <?php else : ?>
                        <span style="font-size:12px;color:#999;"><?php printf(__('Фото %d', 'kinobase'), $i + 1); ?></span>
                    <?php endif; ?>
                    <input type="hidden" name="movie_gallery[<?php echo $i; ?>]" class="kb-img-id" value="<?php echo esc_attr((string) $img_id); ?>">
                    <button type="button" class="button button-small kb-upload-img" data-slot="<?php echo $i; ?>">
                        <?php echo $img_id ? __('Заменить', 'kinobase') : __('Загрузить', 'kinobase'); ?>
                    </button>
                    <?php if ($img_id) : ?>
                        <button type="button" class="button button-small kb-remove-img" style="color:#a00;" data-slot="<?php echo $i; ?>">
                            <?php esc_html_e('Удалить', 'kinobase'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    <script>
    jQuery(function($) {
        var frame;
        $('.kb-upload-img').on('click', function() {
            var btn = $(this);
            var slot = btn.data('slot');
            var slotEl = btn.closest('.kinobase-gallery-slot');

            frame = wp.media({
                title: 'Выберите фото ' + (slot+1),
                button: { text: 'Выбрать' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var att = frame.state().get('selection').first().toJSON();
                slotEl.find('.kb-img-id').val(att.id);
                var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                slotEl.find('img').remove();
                slotEl.find('span').remove();
                $('<img>').attr('src', thumb).css({maxHeight:'100px',objectFit:'contain'}).prependTo(slotEl);
                btn.text('Заменить');
                if (!slotEl.find('.kb-remove-img').length) {
                    $('<button type="button" class="button button-small kb-remove-img" style="color:#a00;" data-slot="'+slot+'">Удалить</button>')
                        .appendTo(slotEl);
                }
            });

            frame.open();
        });

        $(document).on('click', '.kb-remove-img', function() {
            var slotEl = $(this).closest('.kinobase-gallery-slot');
            var slot = $(this).data('slot');
            slotEl.find('.kb-img-id').val('');
            slotEl.find('img').remove();
            slotEl.prepend('<span style="font-size:12px;color:#999;">Фото ' + (slot+1) + '</span>');
            slotEl.find('.kb-upload-img').text('Загрузить');
            $(this).remove();
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
    if ($post->post_type !== 'post') return;

    // Gallery
    if (isset($_POST['movie_gallery']) && is_array($_POST['movie_gallery'])) {
        $gallery = array_map('absint', $_POST['movie_gallery']);
        update_post_meta($post_id, 'movie_gallery', $gallery);
    }

    // Text / number fields
    $text_fields = ['movie_year', 'movie_genre', 'movie_duration', 'movie_quality', 'movie_translation', 'movie_coupon'];
    foreach ($text_fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
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
