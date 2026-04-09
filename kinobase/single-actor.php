<?php
/**
 * Single Actor Template
 *
 * @package KinoBase
 */

get_header();

while (have_posts()) :
    the_post();

    $actor_id   = get_the_ID();
    $actor_name = get_the_title();

    // Gallery
    $gallery_ids = get_post_meta($actor_id, 'movie_gallery', true);
    if (!is_array($gallery_ids)) $gallery_ids = [];
    $gallery_ids = array_filter(array_map('intval', $gallery_ids));
    if (empty($gallery_ids) && has_post_thumbnail()) {
        $gallery_ids = [get_post_thumbnail_id()];
    }

    // Social links
    $social = get_post_meta($actor_id, 'actor_social', true);
    if (!is_array($social)) $social = [];

    // Actor attributes taxonomy
    $actor_terms = get_the_terms($actor_id, 'actor_attr');
    $labels      = [];   // badges (children of "Ярлыки" group)
    $char_groups = [];   // characteristics grouped by parent

    $labels_parent = get_term_by('name', 'Ярлыки', 'actor_attr');

    if ($actor_terms && !is_wp_error($actor_terms)) {
        foreach ($actor_terms as $term) {
            if ($term->parent === 0) continue; // skip top-level groups themselves
            if ($labels_parent && $term->parent === (int) $labels_parent->term_id) {
                $labels[] = $term;
            } else {
                $parent = get_term((int) $term->parent, 'actor_attr');
                if ($parent && !is_wp_error($parent)) {
                    $char_groups[$parent->term_id]['parent'] = $parent;
                    $char_groups[$parent->term_id]['terms'][] = $term;
                }
            }
        }
    }

    // Filmography: movies where _movie_cast contains this actor's ID
    $filmography = new WP_Query([
        'post_type'      => ['post', 'movie'],
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [[
            'key'     => '_movie_cast',
            'value'   => '"' . $actor_id . '"',
            'compare' => 'LIKE',
        ]],
    ]);

    $social_icons = [
        'instagram' => '📸',
        'twitter'   => '🐦',
        'facebook'  => '📘',
        'youtube'   => '▶️',
        'tiktok'    => '🎵',
        'vk'        => '💬',
        'telegram'  => '✈️',
        'website'   => '🌐',
    ];
    $social_labels = [
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
    <main class="site-content" id="main" role="main">
        <div class="container">
        <div class="single-movie single-actor">

            <!-- Breadcrumb -->
            <nav class="single-breadcrumb" aria-label="<?php esc_attr_e('Хлебные крошки', 'kinobase'); ?>">
                <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Главная', 'kinobase'); ?></a>
                <span aria-hidden="true">›</span>
                <a href="<?php echo esc_url(get_post_type_archive_link('actor')); ?>"><?php esc_html_e('Актёры', 'kinobase'); ?></a>
                <span aria-hidden="true">›</span>
                <span><?php echo esc_html($actor_name); ?></span>
            </nav>

            <!-- Main layout (same structure as single movie) -->
            <div class="single-layout">

                <!-- LEFT: Photo gallery -->
                <div class="single-poster-col">
                    <div class="single-poster-viewer" id="single-poster-viewer">
                        <button class="poster-nav poster-nav-prev" id="poster-prev" aria-label="<?php esc_attr_e('Предыдущее фото', 'kinobase'); ?>">&#8249;</button>
                        <button class="poster-nav poster-nav-next" id="poster-next" aria-label="<?php esc_attr_e('Следующее фото', 'kinobase'); ?>">&#8250;</button>

                        <?php if (!empty($gallery_ids)) :
                            foreach (array_values($gallery_ids) as $idx => $img_id) :
                                $full_url = wp_get_attachment_image_url((int) $img_id, 'full');
                                echo wp_get_attachment_image((int) $img_id, 'movie-poster', false, [
                                    'class'         => 'single-poster-img' . ($idx === 0 ? ' active' : ''),
                                    'alt'           => esc_attr($actor_name . ($idx > 0 ? ' — фото ' . ($idx + 1) : '')),
                                    'loading'       => $idx === 0 ? 'eager' : 'lazy',
                                    'fetchpriority' => $idx === 0 ? 'high' : 'auto',
                                    'data-index'    => (string) $idx,
                                    'data-full'     => esc_url($full_url ?: ''),
                                ]);
                            endforeach;
                        else : ?>
                            <div class="single-poster-placeholder"></div>
                        <?php endif; ?>

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

                    <?php if (count($gallery_ids) > 1) : ?>
                    <div class="single-thumbs" id="single-thumbs">
                        <?php foreach (array_values($gallery_ids) as $idx => $img_id) :
                            $thumb_url = wp_get_attachment_image_url((int) $img_id, 'thumbnail');
                            if ($thumb_url) : ?>
                            <button class="single-thumb<?php echo $idx === 0 ? ' active' : ''; ?>"
                                    data-index="<?php echo $idx; ?>"
                                    aria-label="<?php printf(esc_attr__('Фото %d', 'kinobase'), $idx + 1); ?>">
                                <img src="<?php echo esc_url($thumb_url); ?>" alt="" loading="lazy">
                            </button>
                            <?php endif; endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- /LEFT -->

                <!-- RIGHT: Info -->
                <div class="single-info-col">
                    <div class="single-title-row">
                        <h1 class="single-title"><?php echo esc_html($actor_name); ?></h1>
                    </div>

                    <!-- Labels / badges -->
                    <?php if (!empty($labels)) : ?>
                    <div class="actor-labels">
                        <?php foreach ($labels as $label) : ?>
                        <a href="<?php echo esc_url(get_term_link($label, 'actor_attr')); ?>"
                           class="actor-badge">
                            <?php echo esc_html($label->name); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Characteristics table -->
                    <?php if (!empty($char_groups)) : ?>
                    <table class="single-meta-table actor-chars-table">
                        <tbody>
                        <tr>
                            <td class="smeta-label"><?php esc_html_e('На сайте с', 'kinobase'); ?></td>
                            <td class="smeta-value"><?php echo esc_html(get_the_date('d.m.Y')); ?></td>
                        </tr>
                        <?php foreach ($char_groups as $group) :
                            $parent_term = $group['parent'];
                            $group_terms = $group['terms'];
                        ?>
                        <tr>
                            <td class="smeta-label"><?php echo esc_html($parent_term->name); ?></td>
                            <td class="smeta-value">
                                <?php foreach ($group_terms as $i => $t) : ?>
                                <a href="<?php echo esc_url(get_term_link($t, 'actor_attr')); ?>"
                                   class="actor-attr-link">
                                    <?php echo esc_html($t->name); ?>
                                </a><?php if ($i < count($group_terms) - 1) echo ', '; ?>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                    <table class="single-meta-table actor-chars-table">
                        <tbody>
                        <tr>
                            <td class="smeta-label"><?php esc_html_e('На сайте с', 'kinobase'); ?></td>
                            <td class="smeta-value"><?php echo esc_html(get_the_date('d.m.Y')); ?></td>
                        </tr>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <!-- Biography (post content) -->
                    <?php if (get_the_content()) : ?>
                    <div class="single-description">
                        <h3 class="single-section-label"><?php esc_html_e('Биография', 'kinobase'); ?></h3>
                        <div class="single-description-text">
                            <?php the_content(); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Social links -->
                    <?php
                    $social_filtered = array_filter($social);
                    if (!empty($social_filtered)) : ?>
                    <div class="actor-social">
                        <h3 class="single-section-label"><?php esc_html_e('Социальные сети', 'kinobase'); ?></h3>
                        <div class="actor-social-links">
                            <?php foreach ($social_filtered as $platform => $url) : ?>
                            <a href="<?php echo esc_url($url); ?>"
                               class="actor-social-link"
                               target="_blank" rel="noopener noreferrer"
                               aria-label="<?php echo esc_attr($social_labels[$platform] ?? $platform); ?>">
                                <span class="actor-social-icon"><?php echo $social_icons[$platform] ?? '🔗'; ?></span>
                                <span><?php echo esc_html($social_labels[$platform] ?? $platform); ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <!-- /RIGHT -->
            </div>
            <!-- /Main layout -->

            <!-- Filmography -->
            <?php if ($filmography->have_posts()) : ?>
            <section class="actor-filmography">
                <div class="section-header">
                    <h2 class="section-title"><?php esc_html_e('Фильмография', 'kinobase'); ?></h2>
                </div>
                <div class="movie-grid">
                    <?php
                    $card_i = 0;
                    while ($filmography->have_posts()) :
                        $filmography->the_post();
                        get_template_part('template-parts/movie-card', null, [
                            'is_first_block' => true,
                            'card_index'     => $card_i++,
                        ]);
                    endwhile;
                    wp_reset_postdata();
                    ?>
                </div>
            </section>
            <?php endif; ?>

        </div><!-- /.single-actor -->
        </div><!-- /.container -->
    </main>

    <!-- Lightbox overlay -->
    <div class="kb-lightbox" id="kb-lightbox" role="dialog" aria-modal="true"
         aria-label="<?php esc_attr_e('Просмотр фото', 'kinobase'); ?>">
        <button class="kb-lb-close" id="kb-lb-close" aria-label="<?php esc_attr_e('Закрыть', 'kinobase'); ?>">✕</button>
        <span class="kb-lb-counter" id="kb-lb-counter"></span>

        <div class="kb-lb-inner">
            <button class="kb-lb-nav kb-lb-prev" id="kb-lb-prev" aria-label="<?php esc_attr_e('Предыдущее', 'kinobase'); ?>">&#8249;</button>
            <img src="" alt="" class="kb-lb-img" id="kb-lb-img">
            <button class="kb-lb-nav kb-lb-next" id="kb-lb-next" aria-label="<?php esc_attr_e('Следующее', 'kinobase'); ?>">&#8250;</button>
        </div>

        <div class="kb-lb-thumbs" id="kb-lb-thumbs"></div>
    </div>

    <?php
endwhile;

get_footer();
?>
