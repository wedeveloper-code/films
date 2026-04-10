<?php
/**
 * Movie Card Template Part
 *
 * Context variables expected:
 *   $args['is_first_block'] bool — skip lazy loading for first 5 cards (LCP)
 *   $args['card_index']     int  — position in current section (0-based)
 *
 * @package KinoBase
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id     = get_the_ID();
$title       = get_the_title();
$permalink   = get_permalink();
$is_priority = !empty($args['is_first_block']) && ($args['card_index'] ?? 99) < 5;
$loading_attr = $is_priority ? 'eager' : 'lazy';
$priority_attr = $is_priority ? ' fetchpriority="high"' : '';

// Gallery images (up to 4)
$gallery_ids = get_post_meta($post_id, 'movie_gallery', true);
if (!is_array($gallery_ids)) {
    $gallery_ids = [];
}
$gallery_ids = array_filter(array_slice($gallery_ids, 0, 4));

// Fallback: use thumbnail
if (empty($gallery_ids) && has_post_thumbnail()) {
    $gallery_ids = [get_post_thumbnail_id()];
}

// Category-based info: year, quality, promo tags (Акция/Скидки/Бонусы)
$card_year        = '';
$card_quality_cat = '';
$card_promo_tags  = [];

$_promo_names = ['акция', 'акции', 'скидки', 'скидка', 'бонусы', 'бонус'];

foreach (get_the_category() as $cat) {
    if ($cat->parent === 0) continue;
    $parent = get_term((int) $cat->parent, 'category');
    if (!$parent || is_wp_error($parent)) continue;
    $pname = mb_strtolower($parent->name);
    if (in_array($pname, ['год', 'year', 'годы'], true) && !$card_year) {
        $card_year = $cat->name;
    } elseif (in_array($pname, ['качество', 'quality'], true) && !$card_quality_cat) {
        $card_quality_cat = $cat->name;
    } elseif (in_array($pname, $_promo_names, true)) {
        $card_promo_tags[] = $cat;
    }
}

// Movie meta (from meta-boxes)
$quality     = get_post_meta($post_id, 'movie_quality', true) ?: 'HD';
$views       = (int) get_post_meta($post_id, 'movie_views', true);

// Prices
$rent1        = (int) get_post_meta($post_id, 'movie_rent_1', true);
$rent3        = (int) get_post_meta($post_id, 'movie_rent_3', true);
$rent5        = (int) get_post_meta($post_id, 'movie_rent_5', true);
$buy_week     = (int) get_post_meta($post_id, 'movie_buy_week', true);
$buy_month    = (int) get_post_meta($post_id, 'movie_buy_month', true);
$buy_forever  = (int) get_post_meta($post_id, 'movie_buy_forever', true);

// Coupon
$coupon = get_post_meta($post_id, 'movie_coupon', true);

// Rating
$rating_raw = get_post_meta($post_id, 'movie_rating', true);
$rating_val = ($rating_raw !== '' && $rating_raw !== false) ? (float) $rating_raw : null;

// Format views
$views_fmt = $views >= 1000
    ? round($views / 1000, 1) . 'K'
    : (string) $views;

// Year+Quality one-liner with parent category labels
$card_info_parts = [];
if ($card_year) {
    $card_info_parts[] = __('Год', 'kinobase') . ': ' . $card_year;
}
$q_val = $card_quality_cat ?: $quality;
if ($q_val) {
    $card_info_parts[] = __('Качество', 'kinobase') . ': ' . $q_val;
}

?>
<article class="movie-card" data-post-id="<?php echo esc_attr((string) $post_id); ?>">

    <!-- Gallery -->
    <div class="movie-gallery" data-href="<?php echo esc_url($permalink); ?>">

        <?php
        $img_count = count($gallery_ids);

        if ($img_count === 0) {
            // No images placeholder
            echo '<div style="position:absolute;inset:0;background:#1a1d24;display:flex;align-items:center;justify-content:center;color:#555;font-size:0.75rem;">'
                . esc_html__('Нет изображения', 'kinobase') . '</div>';
        } else {
            foreach ($gallery_ids as $i => $img_id) {
                $src = wp_get_attachment_image_url((int) $img_id, 'movie-poster');
                if (!$src) continue;
                $active = $i === 0 ? ' active' : '';
                $lazy   = ($i === 0 && $is_priority) ? 'eager' : 'lazy';
                $prio   = ($i === 0 && $is_priority) ? ' fetchpriority="high"' : '';
                echo '<img src="' . esc_url($src) . '" class="gallery-img' . $active . '"'
                    . ' alt="' . esc_attr($title . ' — ' . __('фото', 'kinobase') . ' ' . ($i + 1)) . '"'
                    . ' loading="' . $lazy . '"' . $prio . '>';
            }

            // Hover zones
            echo '<div class="gallery-zones">';
            for ($z = 0; $z < min($img_count, 4); $z++) {
                echo '<div class="gallery-zone"></div>';
            }
            echo '</div>';
        }
        ?>

        <!-- Quality badge + Rating -->
        <div class="poster-badges" aria-hidden="true">
            <span class="badge"><?php echo esc_html($quality); ?></span>
            <?php if ($rating_val !== null) : ?>
            <span class="badge badge-rating">★ <?php echo number_format($rating_val, 1); ?></span>
            <?php endif; ?>
        </div>

        <!-- Actions: Favorite + Views -->
        <div class="poster-actions">
            <button
                class="fav-btn"
                data-id="<?php echo esc_attr((string) $post_id); ?>"
                aria-label="<?php esc_attr_e('Добавить в избранное', 'kinobase'); ?>"
                title="<?php esc_attr_e('В избранное', 'kinobase'); ?>"
            >
                <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" aria-hidden="true">
                    <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
            </button>

            <div class="views-badge" title="<?php esc_attr_e('Просмотры', 'kinobase'); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
                <span class="views-count"><?php echo esc_html($views_fmt); ?></span>
            </div>
        </div>

        <!-- Image indicators -->
        <?php if ($img_count > 1) : ?>
        <div class="img-indicators" aria-hidden="true">
            <?php for ($ind = 0; $ind < min($img_count, 4); $ind++) : ?>
            <div class="indicator-track">
                <div class="indicator-fill"></div>
            </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    </div>
    <!-- /Gallery -->

    <!-- Card body -->
    <div class="card-body">

        <h3 class="movie-title">
            <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
        </h3>

        <!-- Year · Quality (one line from categories) -->
        <?php if (!empty($card_info_parts)) : ?>
        <div class="card-year-quality"><?php echo esc_html(implode(' · ', $card_info_parts)); ?></div>
        <?php endif; ?>

        <!-- Pricing -->
        <div class="pricing">

            <!-- Tabs -->
            <div class="price-tabs" role="tablist">
                <button class="price-tab active" data-tab="rent" role="tab" aria-selected="true">
                    <?php esc_html_e('Аренда', 'kinobase'); ?>
                </button>
                <button class="price-tab" data-tab="buy" role="tab" aria-selected="false">
                    <?php esc_html_e('Покупка', 'kinobase'); ?>
                </button>
            </div>

            <!-- Rent grid -->
            <div class="price-grid" data-type="rent" role="tabpanel">
                <button class="price-cell" title="<?php esc_attr_e('1 просмотр', 'kinobase'); ?>">
                    <?php esc_html_e('1 пр.', 'kinobase'); ?><strong><?php echo esc_html(kb_price($rent1)); ?></strong>
                </button>
                <button class="price-cell" title="<?php esc_attr_e('3 просмотра', 'kinobase'); ?>">
                    <?php esc_html_e('3 пр.', 'kinobase'); ?><strong><?php echo esc_html(kb_price($rent3)); ?></strong>
                </button>
                <button class="price-cell" title="<?php esc_attr_e('5 просмотров', 'kinobase'); ?>">
                    <?php esc_html_e('5 пр.', 'kinobase'); ?><strong><?php echo esc_html(kb_price($rent5)); ?></strong>
                </button>
            </div>

            <!-- Buy grid -->
            <div class="price-grid hidden" data-type="buy" role="tabpanel">
                <button class="price-cell" title="<?php esc_attr_e('1 неделя', 'kinobase'); ?>">
                    <?php esc_html_e('1 нед.', 'kinobase'); ?><strong><?php echo esc_html(kb_price($buy_week)); ?></strong>
                </button>
                <button class="price-cell" title="<?php esc_attr_e('1 месяц', 'kinobase'); ?>">
                    <?php esc_html_e('1 мес.', 'kinobase'); ?><strong><?php echo esc_html(kb_price($buy_month)); ?></strong>
                </button>
                <button class="price-cell" title="<?php esc_attr_e('Навсегда', 'kinobase'); ?>">
                    ∞<strong><?php echo esc_html(kb_price($buy_forever)); ?></strong>
                </button>
            </div>

            <!-- Coupon -->
            <?php if ($coupon) : ?>
            <div class="coupon-wrap">
                <button class="coupon-btn" data-coupon="<?php echo esc_attr(strtoupper($coupon)); ?>">
                    <?php esc_html_e('Показать купон', 'kinobase'); ?>
                </button>
            </div>
            <?php endif; ?>

        </div>
        <!-- /Pricing -->

        <!-- Promo tags (Акция / Скидки / Бонусы from categories) -->
        <?php if (!empty($card_promo_tags)) : ?>
        <div class="card-promo-tags">
            <?php foreach ($card_promo_tags as $t) : ?>
            <a href="<?php echo esc_url(get_category_link($t->term_id)); ?>"
               class="card-promo-tag"><?php echo esc_html($t->name); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
    <!-- /Card body -->

</article>
