<?php
/**
 * FastWP Theme Functions
 *
 * @package FastWP
 * @version 1.0.0
 * @requires PHP 8.1+
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('FASTWP_VERSION', '1.0.0');
define('FASTWP_DIR', get_template_directory());
define('FASTWP_URI', get_template_directory_uri());

// Load theme modules
require_once FASTWP_DIR . '/inc/setup.php';
require_once FASTWP_DIR . '/inc/post-types.php';
require_once FASTWP_DIR . '/inc/enqueue.php';
require_once FASTWP_DIR . '/inc/meta-boxes.php';
require_once FASTWP_DIR . '/inc/meta-boxes-actor.php';
require_once FASTWP_DIR . '/inc/image-optimizer.php';
require_once FASTWP_DIR . '/inc/ajax-handlers.php';
require_once FASTWP_DIR . '/inc/field-builder.php';
require_once FASTWP_DIR . '/inc/category-meta.php';
require_once FASTWP_DIR . '/inc/auth.php';
require_once FASTWP_DIR . '/inc/rotation.php';
require_once FASTWP_DIR . '/inc/sitemap.php';
require_once FASTWP_DIR . '/inc/settings.php';
require_once FASTWP_DIR . '/inc/csv-import.php';
