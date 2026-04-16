<?php
/**
 * FastWP Asset Enqueue
 *
 * @package FastWP
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'fastwp_enqueue_assets');

function fastwp_enqueue_assets(): void
{
    $css_ver = filemtime(FASTWP_DIR . '/assets/css/main.css') ?: FASTWP_VERSION;
    $js_ver  = filemtime(FASTWP_DIR . '/assets/js/main.js') ?: FASTWP_VERSION;

    // Main stylesheet (no external dependencies)
    wp_enqueue_style(
        'fastwp-main',
        FASTWP_URI . '/assets/css/main.css',
        [],
        (string) $css_ver
    );

    // Main JS — defer, in footer
    wp_enqueue_script(
        'fastwp-main',
        FASTWP_URI . '/assets/js/main.js',
        [],
        (string) $js_ver,
        true
    );

    // Pass data to JS (base + theme settings merged via filter)
    $js_data = apply_filters('fastwp_js_data', [
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('fastwp_views'),
        'contactNonce' => wp_create_nonce('fastwp_contact'),
    ]);
    wp_localize_script('fastwp-main', 'FastWP', $js_data);
}

// Preload Inter font for performance
add_action('wp_head', 'fastwp_preload_font', 1);

function fastwp_preload_font(): void
{
    $font_path = FASTWP_URI . '/assets/fonts/inter/InterVariable.woff2';
    echo '<link rel="preload" href="' . esc_url($font_path) . '" as="font" type="font/woff2" crossorigin>' . "\n";
}

// Remove unnecessary wp_head items for performance
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');

// Disable Gutenberg block styles we don't use
add_action('wp_enqueue_scripts', function (): void {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('classic-theme-styles');
    wp_dequeue_style('global-styles');
}, 20);
