<?php
/**
 * KinoBase Asset Enqueue
 *
 * @package KinoBase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'kinobase_enqueue_assets');

function kinobase_enqueue_assets(): void
{
    $css_ver = filemtime(KINOBASE_DIR . '/assets/css/main.css') ?: KINOBASE_VERSION;
    $js_ver  = filemtime(KINOBASE_DIR . '/assets/js/main.js') ?: KINOBASE_VERSION;

    // Main stylesheet (no external dependencies)
    wp_enqueue_style(
        'kinobase-main',
        KINOBASE_URI . '/assets/css/main.css',
        [],
        (string) $css_ver
    );

    // Main JS — defer, in footer
    wp_enqueue_script(
        'kinobase-main',
        KINOBASE_URI . '/assets/js/main.js',
        [],
        (string) $js_ver,
        true
    );

    // Pass data to JS (base + theme settings merged via filter)
    $js_data = apply_filters('kinobase_js_data', [
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('kinobase_views'),
        'contactNonce' => wp_create_nonce('kinobase_contact'),
    ]);
    wp_localize_script('kinobase-main', 'KinoBase', $js_data);
}

// Preload Inter font for performance
add_action('wp_head', 'kinobase_preload_font', 1);

function kinobase_preload_font(): void
{
    $font_path = KINOBASE_URI . '/assets/fonts/inter/inter-var.woff2';
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
