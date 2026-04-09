<?php
/**
 * KinoBase Actor Meta Boxes
 *
 * Registers and renders meta boxes for the 'actor' CPT:
 *  - Gallery (shared key 'movie_gallery', reuses movie logic)
 *  - Social links
 *  - Filmography panel (read-only, shows linked movies)
 *  - Cast picker on 'movie'/'post' (actor selector)
 *
 * @package KinoBase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   Register meta boxes
   ============================================================ */
add_action('add_meta_boxes', 'kinobase_register_actor_meta_boxes');

function kinobase_register_actor_meta_boxes(): void
{
    // Actor gallery (photos)
    add_meta_box(
        'kinobase_actor_gallery',
        __('Фотографии актёра', 'kinobase'),
        'kinobase_render_actor_gallery_box',
        'actor',
        'normal',
        'high'
    );

    // Social links
    add_meta_box(
        'kinobase_actor_social',
        __('Социальные сети', 'kinobase'),
        'kinobase_render_actor_social_box',
        'actor',
        'normal',
        'default'
    );

    // Filmography (read-only, links to movies)
    add_meta_box(
        'kinobase_actor_films',
        __('Фильмография (фильмы с этим актёром)', 'kinobase'),
        'kinobase_render_actor_films_box',
        'actor',
        'normal',
        'low'
    );

    // Cast picker on movie/post: choose actors
    foreach (['movie', 'post'] as $type) {
        add_meta_box(
            'kinobase_movie_cast',
            __('Актёры в фильме', 'kinobase'),
            'kinobase_render_cast_box',
            $type,
            'normal',
            'default'
        );
    }
}

/* ============================================================
   Actor Gallery Meta Box
   ============================================================ */
function kinobase_render_actor_gallery_box(WP_Post $post): void
{
    wp_nonce_field('kinobase_save_actor_meta', 'kinobase_actor_nonce');
    $gallery = get_post_meta($post->ID, 'movie_gallery', true);
    if (!is_array($gallery)) {
        $gallery = [];
    }
    ?>
    <div class="kinobase-gallery-meta">
        <p style="margin-bottom:10px;color:#666;">
            <?php esc_html_e('Первая фотография — главная (отображается на карточке). Добавьте сколько угодно фото.', 'kinobase'); ?>
        </p>
        <div id="kb-actor-gallery-wrap" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
            <?php foreach ($gallery as $i => $img_id) :
                $img_id = (int) $img_id;
                if (!$img_id) continue;
                $thumb = wp_get_attachment_image_src($img_id, 'thumbnail');
                if (!$thumb) continue;
            ?>
            <div class="kb-gallery-slot" style="position:relative;border:2px solid #ccc;border-radius:6px;overflow:hidden;width:90px;height:90px;">
                <img src="<?php echo esc_url($thumb[0]); ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                <input type="hidden" name="movie_gallery[]" value="<?php echo esc_attr((string) $img_id); ?>">
                <button type="button" class="kb-remove-gallery-img"
                        style="position:absolute;top:2px;right:2px;background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:12px;line-height:1;padding:0;">✕</button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" id="kb-actor-add-photos">
            <?php esc_html_e('+ Добавить фото', 'kinobase'); ?>
        </button>
    </div>
    <script>
    jQuery(function($) {
        $('#kb-actor-add-photos').on('click', function() {
            var frame = wp.media({
                title: 'Выберите фото',
                button: { text: 'Добавить' },
                multiple: true,
                library: { type: 'image' }
            });
            frame.on('select', function() {
                frame.state().get('selection').each(function(att) {
                    var a = att.toJSON();
                    var thumb = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
                    var slot = $('<div class="kb-gallery-slot" style="position:relative;border:2px solid #ccc;border-radius:6px;overflow:hidden;width:90px;height:90px;">'
                        + '<img src="' + thumb + '" style="width:100%;height:100%;object-fit:cover;" alt="">'
                        + '<input type="hidden" name="movie_gallery[]" value="' + a.id + '">'
                        + '<button type="button" class="kb-remove-gallery-img" style="position:absolute;top:2px;right:2px;background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:50%;width:20px;height:20px;cursor:pointer;font-size:12px;line-height:1;padding:0;">✕</button>'
                        + '</div>');
                    $('#kb-actor-gallery-wrap').append(slot);
                });
            });
            frame.open();
        });
        $(document).on('click', '.kb-remove-gallery-img', function() {
            $(this).closest('.kb-gallery-slot').remove();
        });
    });
    </script>
    <?php
}

/* ============================================================
   Social Links Meta Box
   ============================================================ */
function kinobase_render_actor_social_box(WP_Post $post): void
{
    $social = get_post_meta($post->ID, 'actor_social', true);
    if (!is_array($social)) {
        $social = [];
    }
    $platforms = [
        'instagram' => 'Instagram',
        'twitter'   => 'Twitter / X',
        'facebook'  => 'Facebook',
        'youtube'   => 'YouTube',
        'tiktok'    => 'TikTok',
        'vk'        => 'ВКонтакте',
        'telegram'  => 'Telegram',
        'website'   => 'Сайт',
    ];
    ?>
    <table style="width:100%;border-collapse:collapse;">
        <?php foreach ($platforms as $key => $label) :
            $val = esc_attr($social[$key] ?? '');
        ?>
        <tr>
            <td style="width:140px;padding:6px 12px 6px 0;font-weight:600;vertical-align:middle;">
                <?php echo esc_html($label); ?>
            </td>
            <td style="padding:4px 0;">
                <input type="url" name="actor_social[<?php echo esc_attr($key); ?>]"
                       value="<?php echo $val; ?>"
                       placeholder="https://..."
                       style="width:100%;" class="regular-text">
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
}

/* ============================================================
   Filmography Box (read-only on actor edit screen)
   ============================================================ */
function kinobase_render_actor_films_box(WP_Post $post): void
{
    $movies = new WP_Query([
        'post_type'      => ['post', 'movie'],
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        'meta_query'     => [[
            'key'     => '_movie_cast',
            'value'   => '"' . (int) $post->ID . '"',
            'compare' => 'LIKE',
        ]],
    ]);

    if (!$movies->have_posts()) {
        echo '<p style="color:#999;">'
            . esc_html__('Нет привязанных фильмов. Добавьте актёра в фильм через страницу редактирования фильма.', 'kinobase')
            . '</p>';
        return;
    }
    echo '<ul style="margin:0;padding:0;list-style:none;">';
    while ($movies->have_posts()) {
        $movies->the_post();
        echo '<li style="padding:4px 0;border-bottom:1px solid #eee;">'
            . '<a href="' . esc_url(get_edit_post_link()) . '">' . esc_html(get_the_title()) . '</a>'
            . ' <span style="color:#999;font-size:12px;">(' . get_the_date('Y') . ')</span>'
            . '</li>';
    }
    wp_reset_postdata();
    echo '</ul>';
}

/* ============================================================
   Cast Picker on Movie / Post edit screen
   ============================================================ */
function kinobase_render_cast_box(WP_Post $post): void
{
    wp_nonce_field('kinobase_save_cast', 'kinobase_cast_nonce');

    $cast_ids = get_post_meta($post->ID, '_movie_cast', true);
    if (!is_array($cast_ids)) {
        $cast_ids = [];
    }
    $cast_ids = array_filter(array_map('intval', $cast_ids));

    // All actors for the search list
    $all_actors = get_posts([
        'post_type'      => 'actor',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ]);
    ?>
    <p style="color:#666;margin-bottom:10px;">
        <?php esc_html_e('Начните вводить имя актёра для поиска. Нажмите «Добавить», чтобы привязать.', 'kinobase'); ?>
    </p>
    <div style="display:flex;gap:8px;margin-bottom:12px;">
        <input type="text" id="kb-cast-search" placeholder="<?php esc_attr_e('Имя актёра…', 'kinobase'); ?>"
               style="flex:1;" class="regular-text"
               list="kb-cast-datalist">
        <datalist id="kb-cast-datalist">
            <?php foreach ($all_actors as $actor) : ?>
            <option value="<?php echo esc_attr($actor->post_title); ?>" data-id="<?php echo esc_attr((string) $actor->ID); ?>">
            <?php endforeach; ?>
        </datalist>
        <button type="button" id="kb-cast-add" class="button">
            <?php esc_html_e('Добавить', 'kinobase'); ?>
        </button>
    </div>

    <div id="kb-cast-list" style="display:flex;flex-wrap:wrap;gap:8px;min-height:32px;">
        <?php foreach ($cast_ids as $actor_id) :
            $actor = get_post($actor_id);
            if (!$actor) continue;
            $thumb = get_the_post_thumbnail_url($actor_id, 'thumbnail');
        ?>
        <div class="kb-cast-chip" data-id="<?php echo esc_attr((string) $actor_id); ?>"
             style="display:inline-flex;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:999px;padding:4px 10px 4px 4px;">
            <?php if ($thumb) : ?>
            <img src="<?php echo esc_url($thumb); ?>" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">
            <?php endif; ?>
            <span><?php echo esc_html($actor->post_title); ?></span>
            <input type="hidden" name="_movie_cast[]" value="<?php echo esc_attr((string) $actor_id); ?>">
            <button type="button" class="kb-cast-remove"
                    style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:14px;line-height:1;padding:0;margin-left:2px;">✕</button>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    jQuery(function($) {
        var allActors = <?php
            $map = [];
            foreach ($all_actors as $a) {
                $map[] = [
                    'id'    => $a->ID,
                    'title' => $a->post_title,
                    'thumb' => get_the_post_thumbnail_url($a->ID, 'thumbnail') ?: '',
                ];
            }
            echo wp_json_encode($map);
        ?>;

        $('#kb-cast-add').on('click', function() {
            var val = $('#kb-cast-search').val().trim();
            if (!val) return;
            var found = null;
            for (var i = 0; i < allActors.length; i++) {
                if (allActors[i].title.toLowerCase() === val.toLowerCase()) {
                    found = allActors[i];
                    break;
                }
            }
            if (!found) { alert('Актёр не найден. Проверьте имя.'); return; }
            // Prevent duplicates
            if ($('#kb-cast-list [data-id="' + found.id + '"]').length) {
                $('#kb-cast-search').val('');
                return;
            }
            var chip = $('<div class="kb-cast-chip" data-id="' + found.id + '"'
                + ' style="display:inline-flex;align-items:center;gap:6px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:999px;padding:4px 10px 4px 4px;">'
                + (found.thumb ? '<img src="' + found.thumb + '" alt="" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">' : '')
                + '<span>' + $('<span>').text(found.title).html() + '</span>'
                + '<input type="hidden" name="_movie_cast[]" value="' + found.id + '">'
                + '<button type="button" class="kb-cast-remove" style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:14px;line-height:1;padding:0;margin-left:2px;">✕</button>'
                + '</div>');
            $('#kb-cast-list').append(chip);
            $('#kb-cast-search').val('');
        });

        $(document).on('click', '.kb-cast-remove', function() {
            $(this).closest('.kb-cast-chip').remove();
        });
    });
    </script>
    <?php
}

/* ============================================================
   Save actor meta
   ============================================================ */
add_action('save_post_actor', 'kinobase_save_actor_meta', 10, 2);

function kinobase_save_actor_meta(int $post_id, WP_Post $post): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['kinobase_actor_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kinobase_actor_nonce'])), 'kinobase_save_actor_meta')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) return;

    // Gallery
    $gallery = isset($_POST['movie_gallery']) ? (array) $_POST['movie_gallery'] : [];
    $gallery = array_values(array_filter(array_map('absint', $gallery)));
    update_post_meta($post_id, 'movie_gallery', $gallery);

    // Social links
    $social = [];
    $allowed_platforms = ['instagram', 'twitter', 'facebook', 'youtube', 'tiktok', 'vk', 'telegram', 'website'];
    if (!empty($_POST['actor_social']) && is_array($_POST['actor_social'])) {
        foreach ($allowed_platforms as $key) {
            $val = sanitize_url(wp_unslash($_POST['actor_social'][$key] ?? ''));
            if ($val) $social[$key] = $val;
        }
    }
    update_post_meta($post_id, 'actor_social', $social);
}

/* ============================================================
   Save cast meta on movie/post
   ============================================================ */
add_action('save_post', 'kinobase_save_cast_meta', 10, 2);

function kinobase_save_cast_meta(int $post_id, WP_Post $post): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!in_array($post->post_type, ['post', 'movie'], true)) return;
    if (!isset($_POST['kinobase_cast_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kinobase_cast_nonce'])), 'kinobase_save_cast')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) return;

    $cast = isset($_POST['_movie_cast']) ? (array) $_POST['_movie_cast'] : [];
    $cast = array_values(array_unique(array_filter(array_map('absint', $cast))));
    update_post_meta($post_id, '_movie_cast', $cast);
}
