<?php
/**
 * Single Movie Template
 *
 * @package KinoBase
 */

get_header();

while (have_posts()) :
    the_post();

    $post_id     = get_the_ID();
    $title       = get_the_title();
    $year        = get_post_meta($post_id, 'movie_year', true);
    $genre       = get_post_meta($post_id, 'movie_genre', true);
    $duration    = get_post_meta($post_id, 'movie_duration', true);
    $quality     = get_post_meta($post_id, 'movie_quality', true);
    $translation = get_post_meta($post_id, 'movie_translation', true);
    $views       = (int) get_post_meta($post_id, 'movie_views', true);
    $coupon      = get_post_meta($post_id, 'movie_coupon', true);

    $rent1       = (int) get_post_meta($post_id, 'movie_rent_1', true);
    $rent3       = (int) get_post_meta($post_id, 'movie_rent_3', true);
    $rent5       = (int) get_post_meta($post_id, 'movie_rent_5', true);
    $buy_week    = (int) get_post_meta($post_id, 'movie_buy_week', true);
    $buy_month   = (int) get_post_meta($post_id, 'movie_buy_month', true);
    $buy_forever = (int) get_post_meta($post_id, 'movie_buy_forever', true);

    $gallery_ids = get_post_meta($post_id, 'movie_gallery', true);
    if (!is_array($gallery_ids)) $gallery_ids = [];
    if (empty($gallery_ids) && has_post_thumbnail()) {
        $gallery_ids = [get_post_thumbnail_id()];
    }

    $categories = get_the_category();
    ?>
    <main class="site-content" id="main" role="main">
        <div class="container">

            <div class="single-movie">
                <!-- Breadcrumb -->
                <nav aria-label="Хлебные крошки" style="margin-bottom:1rem;font-size:0.875rem;color:var(--text-muted);">
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Главная', 'kinobase'); ?></a>
                    <?php foreach ($categories as $cat) : ?>
                        &rsaquo; <a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>"><?php echo esc_html($cat->name); ?></a>
                    <?php endforeach; ?>
                    &rsaquo; <span><?php echo esc_html($title); ?></span>
                </nav>

                <div class="single-movie-inner">

                    <!-- Poster -->
                    <div>
                        <div class="single-poster">
                            <?php if (!empty($gallery_ids[0])) : ?>
                                <?php echo wp_get_attachment_image((int) $gallery_ids[0], 'movie-poster', false, [
                                    'alt'            => esc_attr($title),
                                    'fetchpriority'  => 'high',
                                    'loading'        => 'eager',
                                ]); ?>
                            <?php elseif (has_post_thumbnail()) : ?>
                                <?php the_post_thumbnail('movie-poster', ['alt' => esc_attr($title)]); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="single-info">
                        <h1><?php echo esc_html($title); ?></h1>

                        <!-- Gallery thumbnails -->
                        <?php if (count($gallery_ids) > 1) : ?>
                        <div class="single-gallery">
                            <?php foreach (array_slice($gallery_ids, 0, 4) as $i => $img_id) :
                                $src = wp_get_attachment_image_url((int) $img_id, 'movie-poster-sm');
                                if ($src) :
                                ?>
                                <img src="<?php echo esc_url($src); ?>"
                                     alt="<?php echo esc_attr($title . ' — кадр ' . ($i + 1)); ?>"
                                     loading="<?php echo $i < 2 ? 'eager' : 'lazy'; ?>">
                                <?php endif; endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Meta table -->
                        <table class="single-meta-table">
                            <?php
                            $meta_rows = array_filter([
                                __('Год:', 'kinobase')      => $year,
                                __('Жанр:', 'kinobase')     => $genre,
                                __('Длительность:', 'kinobase') => $duration,
                                __('Качество:', 'kinobase') => $quality,
                                __('Перевод:', 'kinobase')  => $translation,
                                __('Просмотры:', 'kinobase')=> $views ? number_format($views) : null,
                            ]);
                            foreach ($meta_rows as $label => $value) :
                            ?>
                            <tr>
                                <td><?php echo esc_html($label); ?></td>
                                <td><?php echo esc_html((string) $value); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>

                        <!-- Pricing -->
                        <div class="single-pricing">
                            <h3><?php esc_html_e('Доступ к фильму', 'kinobase'); ?></h3>

                            <div class="price-tabs" role="tablist" style="margin-bottom:0.75rem;">
                                <button class="price-tab active" data-tab="rent" role="tab" aria-selected="true">
                                    <?php esc_html_e('Аренда', 'kinobase'); ?>
                                </button>
                                <button class="price-tab" data-tab="buy" role="tab" aria-selected="false">
                                    <?php esc_html_e('Покупка', 'kinobase'); ?>
                                </button>
                            </div>

                            <div class="price-grid" data-type="rent" role="tabpanel">
                                <?php
                                $rent_options = [
                                    '1 просмотр'  => $rent1,
                                    '3 просмотра' => $rent3,
                                    '5 просмотров'=> $rent5,
                                ];
                                foreach ($rent_options as $label => $price) : ?>
                                <button class="price-cell" style="padding:0.75rem;">
                                    <?php echo esc_html($label); ?><strong><?php echo esc_html($price > 0 ? number_format($price) . '₽' : '—'); ?></strong>
                                </button>
                                <?php endforeach; ?>
                            </div>

                            <div class="price-grid hidden" data-type="buy" role="tabpanel">
                                <?php
                                $buy_options = [
                                    'Неделя'  => $buy_week,
                                    'Месяц'   => $buy_month,
                                    'Навсегда'=> $buy_forever,
                                ];
                                foreach ($buy_options as $label => $price) : ?>
                                <button class="price-cell" style="padding:0.75rem;">
                                    <?php echo esc_html($label); ?><strong><?php echo esc_html($price > 0 ? number_format($price) . '₽' : '—'); ?></strong>
                                </button>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($coupon) : ?>
                            <div class="coupon-wrap" style="margin-top:1rem;">
                                <button class="coupon-btn" data-coupon="<?php echo esc_attr(strtoupper($coupon)); ?>">
                                    <?php esc_html_e('Показать купон на скидку', 'kinobase'); ?>
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Description -->
                        <?php if (get_the_content()) : ?>
                        <div style="margin-top:1.5rem;">
                            <h3 style="font-size:1.125rem;font-weight:700;margin-bottom:0.75rem;"><?php esc_html_e('Описание', 'kinobase'); ?></h3>
                            <div style="color:var(--text-muted);line-height:1.7;">
                                <?php the_content(); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                    <!-- /Info -->

                </div>
            </div>

        </div>
    </main>
    <?php
endwhile;

get_footer();
?>
