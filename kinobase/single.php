<?php
/**
 * Single Movie Template
 *
 * @package KinoBase
 */

get_header();

while (have_posts()) :
    the_post();

    $post_id      = get_the_ID();
    $title        = get_the_title();
    $year         = (string) get_post_meta($post_id, 'movie_year', true);
    $genre        = (string) get_post_meta($post_id, 'movie_genre', true);
    $duration     = (string) get_post_meta($post_id, 'movie_duration', true);
    $quality      = (string) get_post_meta($post_id, 'movie_quality', true);
    $translation  = (string) get_post_meta($post_id, 'movie_translation', true);
    $actors       = (string) get_post_meta($post_id, 'movie_actors', true);
    $directors    = (string) get_post_meta($post_id, 'movie_directors', true);
    $rating       = get_post_meta($post_id, 'movie_rating', true);
    $box_office   = get_post_meta($post_id, 'movie_box_office', true);
    $views        = (int) get_post_meta($post_id, 'movie_views', true);
    $coupon       = (string) get_post_meta($post_id, 'movie_coupon', true);

    $rent1        = (int) get_post_meta($post_id, 'movie_rent_1', true);
    $rent3        = (int) get_post_meta($post_id, 'movie_rent_3', true);
    $rent5        = (int) get_post_meta($post_id, 'movie_rent_5', true);
    $buy_week     = (int) get_post_meta($post_id, 'movie_buy_week', true);
    $buy_month    = (int) get_post_meta($post_id, 'movie_buy_month', true);
    $buy_forever  = (int) get_post_meta($post_id, 'movie_buy_forever', true);

    $gallery_ids  = get_post_meta($post_id, 'movie_gallery', true);
    if (!is_array($gallery_ids)) $gallery_ids = [];
    $gallery_ids  = array_filter(array_map('intval', $gallery_ids));
    if (empty($gallery_ids) && has_post_thumbnail()) {
        $gallery_ids = [get_post_thumbnail_id()];
    }

    $categories = get_the_category();

    // Math captcha for reviews
    $captcha_a   = wp_rand(1, 9);
    $captcha_b   = wp_rand(1, 9);
    $captcha_ans = $captcha_a + $captcha_b;
    $captcha_key = wp_generate_uuid4();
    set_transient('kb_captcha_' . $captcha_key, $captcha_ans, 15 * MINUTE_IN_SECONDS);

    // Rating as float
    $rating_val = ($rating !== '' && $rating !== false) ? (float) $rating : null;
    ?>
    <main class="site-content" id="main" role="main">
        <div class="container">
        <div class="single-movie">

            <!-- Breadcrumb -->
            <nav class="single-breadcrumb" aria-label="<?php esc_attr_e('Хлебные крошки', 'kinobase'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Главная', 'kinobase'); ?></a>
                <?php foreach ($categories as $cat) : ?>
                    <span aria-hidden="true">›</span>
                    <a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>"><?php echo esc_html($cat->name); ?></a>
                <?php endforeach; ?>
                <span aria-hidden="true">›</span>
                <span><?php echo esc_html($title); ?></span>
            </nav>

            <!-- Main layout -->
            <div class="single-layout">

                <!-- LEFT: Poster gallery -->
                <div class="single-poster-col">
                    <div class="single-poster-viewer" id="single-poster-viewer">
                        <!-- Prev / Next nav zones -->
                        <button class="poster-nav poster-nav-prev" id="poster-prev" aria-label="<?php esc_attr_e('Предыдущее фото', 'kinobase'); ?>">&#8249;</button>
                        <button class="poster-nav poster-nav-next" id="poster-next" aria-label="<?php esc_attr_e('Следующее фото', 'kinobase'); ?>">&#8250;</button>

                        <!-- Images -->
                        <?php if (!empty($gallery_ids)) :
                            foreach (array_values($gallery_ids) as $idx => $img_id) :
                                echo wp_get_attachment_image((int) $img_id, 'movie-poster', false, [
                                    'class'          => 'single-poster-img' . ($idx === 0 ? ' active' : ''),
                                    'alt'            => esc_attr($title . ($idx > 0 ? ' — кадр ' . ($idx + 1) : '')),
                                    'loading'        => $idx < 2 ? 'eager' : 'lazy',
                                    'fetchpriority'  => $idx === 0 ? 'high' : 'auto',
                                    'data-index'     => (string) $idx,
                                ]);
                            endforeach;
                        elseif (has_post_thumbnail()) :
                            the_post_thumbnail('movie-poster', ['class' => 'single-poster-img active', 'alt' => esc_attr($title)]);
                        else : ?>
                            <div class="single-poster-placeholder"></div>
                        <?php endif; ?>

                        <?php if ($quality) : ?>
                        <div class="single-quality-badge"><?php echo esc_html($quality); ?></div>
                        <?php endif; ?>

                        <!-- Indicators -->
                        <?php if (count($gallery_ids) > 1) : ?>
                        <div class="single-indicators">
                            <?php foreach (array_values($gallery_ids) as $idx => $img_id) : ?>
                            <button class="single-indicator<?php echo $idx === 0 ? ' active' : ''; ?>"
                                    data-index="<?php echo $idx; ?>"
                                    aria-label="<?php printf(esc_attr__('Фото %d', 'kinobase'), $idx + 1); ?>"></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Thumbnail strip -->
                    <?php if (count($gallery_ids) > 1) : ?>
                    <div class="single-thumbs" id="single-thumbs">
                        <?php foreach (array_values($gallery_ids) as $idx => $img_id) :
                            $thumb_url = wp_get_attachment_image_url((int) $img_id, 'thumbnail');
                            if ($thumb_url) : ?>
                            <button class="single-thumb<?php echo $idx === 0 ? ' active' : ''; ?>"
                                    data-index="<?php echo $idx; ?>"
                                    aria-label="<?php printf(esc_attr__('Фото %d', 'kinobase'), $idx + 1); ?>">
                                <img src="<?php echo esc_url($thumb_url); ?>"
                                     alt="<?php echo esc_attr($title . ' — кадр ' . ($idx + 1)); ?>"
                                     loading="lazy">
                            </button>
                            <?php endif; endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- /LEFT -->

                <!-- RIGHT: Info -->
                <div class="single-info-col">

                    <!-- Title + actions -->
                    <div class="single-title-row">
                        <h1 class="single-title"><?php echo esc_html($title); ?></h1>
                        <div class="single-title-actions">
                            <?php if ($views) : ?>
                            <span class="single-views-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <span><?php echo number_format($views); ?></span>
                            </span>
                            <?php endif; ?>
                            <button class="fav-btn single-fav-btn" data-id="<?php echo esc_attr((string) $post_id); ?>" aria-label="<?php esc_attr_e('В избранное', 'kinobase'); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Rating -->
                    <?php if ($rating_val !== null) : ?>
                    <div class="single-rating">
                        <div class="rating-stars" aria-label="<?php printf(esc_attr__('Рейтинг: %s из 10', 'kinobase'), number_format($rating_val, 1)); ?>">
                            <?php for ($s = 1; $s <= 10; $s++) :
                                $filled = $s <= round($rating_val);
                            ?>
                            <span class="rating-star<?php echo $filled ? ' filled' : ''; ?>" aria-hidden="true">★</span>
                            <?php endfor; ?>
                        </div>
                        <span class="rating-value"><?php echo number_format($rating_val, 1); ?></span>
                        <span class="rating-max">/10</span>
                    </div>
                    <?php endif; ?>

                    <!-- Category tags -->
                    <?php if (!empty($categories)) : ?>
                    <div class="single-tags">
                        <?php foreach ($categories as $cat) : ?>
                        <a href="<?php echo esc_url(get_category_link($cat->term_id)); ?>" class="single-tag">
                            <?php echo esc_html($cat->name); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Meta table -->
                    <table class="single-meta-table">
                        <tbody>
                        <?php
                        $rows = [
                            __('Год',        'kinobase') => $year,
                            __('Жанр',       'kinobase') => $genre,
                            __('Длительность','kinobase')=> $duration,
                            __('Перевод',    'kinobase') => $translation,
                            __('Режиссёр',   'kinobase') => $directors,
                            __('Актёры',     'kinobase') => $actors,
                        ];
                        foreach ($rows as $label => $value) :
                            if ($value === '') continue;
                            ?>
                            <tr>
                                <td class="smeta-label"><?php echo esc_html($label); ?></td>
                                <td class="smeta-value"><?php echo esc_html($value); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Box office -->
                    <?php if (!empty($box_office) && is_array($box_office)) : ?>
                    <div class="single-box-office">
                        <h3 class="single-section-label"><?php esc_html_e('Кассовые сборы', 'kinobase'); ?></h3>
                        <table class="box-office-table">
                            <tbody>
                            <?php foreach ($box_office as $row) :
                                $country = $row['country'] ?? '';
                                $amount  = $row['amount']  ?? '';
                                if (!$country && !$amount) continue;
                            ?>
                            <tr>
                                <td><?php echo esc_html($country); ?></td>
                                <td><?php echo esc_html($amount); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <!-- Pricing -->
                    <div class="single-pricing">
                        <h3 class="single-section-label"><?php esc_html_e('Доступ к фильму', 'kinobase'); ?></h3>

                        <div class="price-tabs" role="tablist">
                            <button class="price-tab active" data-tab="rent" role="tab" aria-selected="true">
                                <?php esc_html_e('Аренда', 'kinobase'); ?>
                            </button>
                            <button class="price-tab" data-tab="buy" role="tab" aria-selected="false">
                                <?php esc_html_e('Покупка', 'kinobase'); ?>
                            </button>
                        </div>

                        <div class="price-grid" data-type="rent" role="tabpanel">
                            <button class="price-cell">
                                <?php esc_html_e('1 просмотр', 'kinobase'); ?>
                                <strong><?php echo esc_html($rent1 > 0 ? number_format($rent1) . '₽' : '—'); ?></strong>
                            </button>
                            <button class="price-cell">
                                <?php esc_html_e('3 просмотра', 'kinobase'); ?>
                                <strong><?php echo esc_html($rent3 > 0 ? number_format($rent3) . '₽' : '—'); ?></strong>
                            </button>
                            <button class="price-cell">
                                <?php esc_html_e('5 просмотров', 'kinobase'); ?>
                                <strong><?php echo esc_html($rent5 > 0 ? number_format($rent5) . '₽' : '—'); ?></strong>
                            </button>
                        </div>

                        <div class="price-grid hidden" data-type="buy" role="tabpanel">
                            <button class="price-cell">
                                <?php esc_html_e('Неделя', 'kinobase'); ?>
                                <strong><?php echo esc_html($buy_week > 0 ? number_format($buy_week) . '₽' : '—'); ?></strong>
                            </button>
                            <button class="price-cell">
                                <?php esc_html_e('Месяц', 'kinobase'); ?>
                                <strong><?php echo esc_html($buy_month > 0 ? number_format($buy_month) . '₽' : '—'); ?></strong>
                            </button>
                            <button class="price-cell">
                                <?php esc_html_e('Навсегда', 'kinobase'); ?>
                                <strong><?php echo esc_html($buy_forever > 0 ? number_format($buy_forever) . '₽' : '—'); ?></strong>
                            </button>
                        </div>

                        <?php if ($coupon) : ?>
                        <div class="coupon-wrap" style="margin-top:0.75rem;">
                            <button class="coupon-btn" data-coupon="<?php echo esc_attr(strtoupper($coupon)); ?>">
                                <?php esc_html_e('Показать купон на скидку', 'kinobase'); ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <?php if (get_the_content()) : ?>
                    <div class="single-description">
                        <h3 class="single-section-label"><?php esc_html_e('Описание', 'kinobase'); ?></h3>
                        <div class="single-description-text">
                            <?php the_content(); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <!-- /RIGHT -->

            </div>
            <!-- /Main layout -->

            <!-- ===== Reviews ===== -->
            <div class="single-reviews" id="reviews">
                <h2 class="single-reviews-title">
                    <?php
                    $comment_count = get_comments_number($post_id);
                    printf(
                        esc_html(_n('Отзыв (%d)', 'Отзывы (%d)', (int) $comment_count, 'kinobase')),
                        (int) $comment_count
                    );
                    ?>
                </h2>

                <!-- Review Form -->
                <div class="review-form-wrap">
                    <h3 class="review-form-heading"><?php esc_html_e('Оставить отзыв', 'kinobase'); ?></h3>
                    <form class="review-form" id="review-form" novalidate>
                        <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $post_id); ?>">
                        <input type="hidden" name="captcha_key" value="<?php echo esc_attr($captcha_key); ?>">
                        <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('kinobase_review')); ?>">

                        <div class="review-fields">
                            <div class="review-field">
                                <label class="review-label" for="review-author"><?php esc_html_e('Имя', 'kinobase'); ?></label>
                                <input type="text" id="review-author" name="review_author" class="review-input"
                                       placeholder="<?php esc_attr_e('Ваше имя', 'kinobase'); ?>"
                                       maxlength="100" required
                                       value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->display_name) : ''; ?>">
                            </div>
                            <div class="review-field">
                                <label class="review-label" for="review-rating-select"><?php esc_html_e('Оценка', 'kinobase'); ?></label>
                                <select id="review-rating-select" name="review_rating" class="review-input review-select">
                                    <?php for ($r = 10; $r >= 1; $r--) : ?>
                                    <option value="<?php echo $r; ?>"><?php echo $r; ?>/10</option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="review-field">
                            <label class="review-label" for="review-text"><?php esc_html_e('Ваш отзыв', 'kinobase'); ?></label>
                            <textarea id="review-text" name="review_text" class="review-input review-textarea"
                                      placeholder="<?php esc_attr_e('Поделитесь впечатлениями о фильме…', 'kinobase'); ?>"
                                      required maxlength="3000" rows="5"></textarea>
                        </div>

                        <div class="review-captcha-row">
                            <label class="review-label" for="review-captcha">
                                <?php printf(esc_html__('Сколько будет %d + %d?', 'kinobase'), $captcha_a, $captcha_b); ?>
                            </label>
                            <input type="number" id="review-captcha" name="captcha_answer" class="review-input review-captcha-input"
                                   placeholder="<?php esc_attr_e('Ответ', 'kinobase'); ?>" required min="0" max="99">
                        </div>

                        <div id="review-alert" class="review-alert" role="alert" aria-live="polite"></div>

                        <button type="submit" class="review-submit" id="review-submit">
                            <span class="review-submit-text"><?php esc_html_e('Отправить отзыв', 'kinobase'); ?></span>
                            <span class="contact-submit-spinner" aria-hidden="true"></span>
                        </button>
                    </form>
                </div>

                <!-- Existing comments -->
                <?php
                $comments = get_comments([
                    'post_id'    => $post_id,
                    'status'     => 'approve',
                    'order'      => 'DESC',
                    'number'     => 20,
                    'type'       => 'comment',
                ]);
                if ($comments) : ?>
                <div class="review-list" id="review-list">
                    <?php foreach ($comments as $comment) :
                        $review_rating = (int) get_comment_meta($comment->comment_ID, 'review_rating', true);
                    ?>
                    <div class="review-item">
                        <div class="review-item-header">
                            <span class="review-author"><?php echo esc_html($comment->comment_author); ?></span>
                            <?php if ($review_rating) : ?>
                            <span class="review-score">
                                <span class="review-score-star">★</span>
                                <?php echo esc_html((string) $review_rating); ?>/10
                            </span>
                            <?php endif; ?>
                            <span class="review-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($comment->comment_date))); ?></span>
                        </div>
                        <div class="review-text"><?php echo nl2br(esc_html($comment->comment_content)); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <!-- /Reviews -->

            <?php
            // ===== Related blocks =====
            $related_blocks = [
                [
                    'title' => __('Сейчас смотрят', 'kinobase'),
                    'args'  => [
                        'posts_per_page'         => 5,
                        'post__not_in'           => [$post_id],
                        'meta_key'               => 'movie_views',
                        'orderby'                => 'meta_value_num',
                        'order'                  => 'DESC',
                        'no_found_rows'          => true,
                        'update_post_term_cache' => false,
                    ],
                ],
                [
                    'title' => __('Рекомендуем', 'kinobase'),
                    'args'  => [
                        'posts_per_page'         => 5,
                        'post__not_in'           => [$post_id],
                        'category__in'           => array_map(fn($c) => $c->term_id, $categories),
                        'orderby'                => 'rand',
                        'no_found_rows'          => true,
                        'update_post_term_cache' => false,
                    ],
                ],
                [
                    'title' => __('Новинки', 'kinobase'),
                    'args'  => [
                        'posts_per_page'         => 5,
                        'post__not_in'           => [$post_id],
                        'orderby'                => 'date',
                        'order'                  => 'DESC',
                        'no_found_rows'          => true,
                        'update_post_term_cache' => false,
                    ],
                ],
            ];

            foreach ($related_blocks as $block) :
                $q = new WP_Query($block['args']);
                if (!$q->have_posts()) continue;
            ?>
            <section class="related-section">
                <div class="section-header">
                    <h2 class="section-title"><?php echo esc_html($block['title']); ?></h2>
                </div>
                <div class="related-grid">
                    <?php while ($q->have_posts()) : $q->the_post();
                        get_template_part('template-parts/movie-mini-card');
                    endwhile; wp_reset_postdata(); ?>
                </div>
            </section>
            <?php endforeach; ?>

        </div><!-- /.single-movie -->
        </div><!-- /.container -->
    </main>
    <?php
endwhile;

get_footer();
?>
