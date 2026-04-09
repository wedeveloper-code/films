<?php
/**
 * KinoBase Theme Functions
 *
 * @package KinoBase
 * @version 1.0.0
 * @requires PHP 8.1+
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('KINOBASE_VERSION', '1.0.0');
define('KINOBASE_DIR', get_template_directory());
define('KINOBASE_URI', get_template_directory_uri());

// Load theme modules
require_once KINOBASE_DIR . '/inc/setup.php';
require_once KINOBASE_DIR . '/inc/post-types.php';
require_once KINOBASE_DIR . '/inc/enqueue.php';
require_once KINOBASE_DIR . '/inc/meta-boxes.php';
require_once KINOBASE_DIR . '/inc/meta-boxes-actor.php';
require_once KINOBASE_DIR . '/inc/image-optimizer.php';
require_once KINOBASE_DIR . '/inc/ajax-handlers.php';
