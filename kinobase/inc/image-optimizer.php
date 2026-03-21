<?php
/**
 * KinoBase Image Optimizer
 *
 * Auto-converts uploaded images to WebP and compresses to max 200KB.
 * Requires GD extension (available in PHP 8.3).
 *
 * @package KinoBase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Only run if GD is available
if (!function_exists('imagecreatefrompng')) {
    return;
}

add_filter('wp_handle_upload', 'kinobase_optimize_uploaded_image');

/**
 * Convert uploaded image to WebP and compress to max 200KB.
 *
 * @param array<string,string> $upload Upload data from WordPress.
 * @return array<string,string>
 */
function kinobase_optimize_uploaded_image(array $upload): array
{
    // Only process images
    if (!isset($upload['type']) || !str_starts_with($upload['type'], 'image/')) {
        return $upload;
    }

    $file = $upload['file'] ?? '';
    if (!$file || !is_readable($file)) {
        return $upload;
    }

    // Skip SVG and GIF
    $mime = $upload['type'];
    if (in_array($mime, ['image/svg+xml', 'image/gif'], true)) {
        return $upload;
    }

    // Create GD resource from source
    $image = kinobase_gd_create($file, $mime);
    if (!$image) {
        return $upload;
    }

    // Build WebP output path
    $info     = pathinfo($file);
    $webp_file = $info['dirname'] . '/' . $info['filename'] . '.webp';

    // Try quality 85 → 75 → 65 until under 200KB, then scale down
    $max_bytes = 200 * 1024;
    $quality   = 85;
    $saved     = false;

    while ($quality >= 50) {
        imagewebp($image, $webp_file, $quality);

        if (file_exists($webp_file) && filesize($webp_file) <= $max_bytes) {
            $saved = true;
            break;
        }
        $quality -= 10;
    }

    // If still over 200KB, scale down dimensions
    if (!$saved || (file_exists($webp_file) && filesize($webp_file) > $max_bytes)) {
        $image = kinobase_scale_image($image, $max_bytes, $webp_file);
    }

    imagedestroy($image);

    if (!file_exists($webp_file)) {
        return $upload;
    }

    // Replace original file with WebP
    @unlink($file);
    rename($webp_file, $file);

    // Update MIME type and url extension if different
    $upload['type'] = 'image/webp';

    // If original had different extension, rename the file
    if (strtolower($info['extension'] ?? '') !== 'webp') {
        $new_file = $info['dirname'] . '/' . $info['filename'] . '.webp';
        rename($file, $new_file);
        $upload['file'] = $new_file;
        $upload['url']  = str_replace('.' . $info['extension'], '.webp', $upload['url'] ?? '');
    }

    return $upload;
}

/**
 * Create a GD image resource from a file.
 *
 * @param string $file Path to image.
 * @param string $mime MIME type.
 * @return \GdImage|false
 */
function kinobase_gd_create(string $file, string $mime): \GdImage|false
{
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file),
        'image/png'  => @imagecreatefrompng($file),
        'image/webp' => @imagecreatefromwebp($file),
        'image/bmp'  => @imagecreatefrombmp($file),
        default      => false,
    };
}

/**
 * Scale down image dimensions until the WebP output is under max_bytes.
 *
 * @param \GdImage $src Source image.
 * @param int $max_bytes Maximum file size in bytes.
 * @param string $dest Destination file path.
 * @return \GdImage Resulting (possibly scaled) image.
 */
function kinobase_scale_image(\GdImage $src, int $max_bytes, string $dest): \GdImage
{
    $w = imagesx($src);
    $h = imagesy($src);

    for ($scale = 0.9; $scale >= 0.3; $scale -= 0.1) {
        $nw = (int) ($w * $scale);
        $nh = (int) ($h * $scale);

        $resized = imagecreatetruecolor($nw, $nh);
        if (!$resized) break;

        // Preserve transparency for PNG-origin images
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled($resized, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagewebp($resized, $dest, 80);

        if (file_exists($dest) && filesize($dest) <= $max_bytes) {
            imagedestroy($src);
            return $resized;
        }

        imagedestroy($resized);
    }

    // Last resort: save with quality 60 at original size
    imagewebp($src, $dest, 60);
    return $src;
}
