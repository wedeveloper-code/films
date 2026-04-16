<?php
/**
 * Movie Mini Card — used in related blocks on single.php
 *
 * @package FastWP
 */

$mini_id      = get_the_ID();
$mini_title   = get_the_title();
$mini_year    = (string) get_post_meta($mini_id, 'movie_year', true);
$mini_quality = (string) get_post_meta($mini_id, 'movie_quality', true);
$mini_rating  = get_post_meta($mini_id, 'movie_rating', true);
$mini_gallery = get_post_meta($mini_id, 'movie_gallery', true);
if (!is_array($mini_gallery)) $mini_gallery = [];

// Poster image
$mini_thumb_url = '';
if (!empty($mini_gallery[0])) {
    $mini_thumb_url = (string) wp_get_attachment_image_url((int) $mini_gallery[0], 'movie-poster-sm');
}
if (!$mini_thumb_url && has_post_thumbnail()) {
    $mini_thumb_url = (string) get_the_post_thumbnail_url($mini_id, 'movie-poster-sm');
}
?>
<article class="mini-card">
    <a href="<?php the_permalink(); ?>" class="mini-card-link" aria-label="<?php echo esc_attr($mini_title); ?>">
        <div class="mini-card-poster">
            <?php if ($mini_thumb_url) : ?>
            <img src="<?php echo esc_url($mini_thumb_url); ?>"
                 alt="<?php echo esc_attr($mini_title); ?>"
                 loading="lazy">
            <?php else : ?>
            <div class="mini-card-no-img">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <rect x="2" y="2" width="20" height="20" rx="2"/><path d="M7 8h10M7 12h6"/>
                </svg>
            </div>
            <?php endif; ?>

            <?php if ($mini_quality) : ?>
            <span class="mini-card-quality"><?php echo esc_html($mini_quality); ?></span>
            <?php endif; ?>

            <?php if ($mini_rating !== '' && $mini_rating !== false) : ?>
            <span class="mini-card-rating">★ <?php echo number_format((float) $mini_rating, 1); ?></span>
            <?php endif; ?>
        </div>
        <div class="mini-card-body">
            <div class="mini-card-title"><?php echo esc_html($mini_title); ?></div>
            <?php if ($mini_year) : ?>
            <div class="mini-card-year"><?php echo esc_html($mini_year); ?></div>
            <?php endif; ?>
        </div>
    </a>
</article>
