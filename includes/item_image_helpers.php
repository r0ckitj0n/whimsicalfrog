<?php

/**
 * Item Image Helper Functions
 * Provides utility functions for managing item images
 */

require_once __DIR__ . '/../api/config.php';
// getPrimaryImageBySku function moved to image_helper.php for centralization
// Load canonical image helpers; guard duplicates below per-function
require_once __DIR__ . '/image_helper.php';

/**
 * Get all images for an item by SKU
 */
if (!function_exists('getAllImagesBySku')) {
function getAllImagesBySku($sku)
{
    try {
        $images = Database::queryAll("\n            SELECT * FROM item_images \n            WHERE sku = ? \n            ORDER BY is_primary DESC, sort_order ASC, id ASC\n        ", [$sku]);

        // Convert boolean values
        foreach ($images as &$image) {
            $image['is_primary'] = (bool)$image['is_primary'];
            $image['sort_order'] = (int)$image['sort_order'];
        }

        return $images;

    } catch (Exception $e) {
        error_log("Database error in getAllImagesBySku: " . $e->getMessage());
        return [];
    }
}
}

/**
 * Check if an item has images
 */
if (!function_exists('hasImagesBySku')) {
function hasImagesBySku($sku)
{
    $images = getAllImagesBySku($sku);
    return !empty($images);
}
}

/**
 * Get count of images for an item
 */
if (!function_exists('getImageCountBySku')) {
function getImageCountBySku($sku)
{
    $images = getAllImagesBySku($sku);
    return count($images);
}
}

/**
 * Get placeholder image info - DEPRECATED: Use CSS-only solution instead
 */
if (!function_exists('getPlaceholderImage')) {
function getPlaceholderImage()
{
    return [
        'image_path' => null, // No longer using placeholder image
        'alt_text' => 'No image available',
        'css_fallback' => true // Flag to use CSS-only display
    ];
}
}

/**
 * Get image URL with fallback to image server if needed
 */
if (!function_exists('getImageUrlWithFallback')) {
function getImageUrlWithFallback($imagePath, $sku = null)
{
    if (empty($imagePath)) {
        return null; // Let CSS handle the fallback
    }

    // Check if the image file exists
    $fullPath = __DIR__ . '/../' . ltrim($imagePath, '/');
    if (file_exists($fullPath)) {
        // Return web-accessible path (absolute from web root)
        return '/' . ltrim($imagePath, '/');
    }

    // Strict: do not attempt to guess alternative files; surface missing asset
    return null;
}
}
// getImageWithFallback function moved to image_helper.php for centralization
// getDbConnection function moved to database.php for centralization

/**
 * Get fallback image from old system
 */
// Strict: deprecated; do not synthesize images from filesystem
if (!function_exists('getFallbackItemImage')) {
function getFallbackItemImage($sku)
{
    return null;
}
}

/**
 * Get all images for an item
 */
if (!function_exists('getItemImages')) {
function getItemImages($sku, $pdo = null)
{
    // Use Database singleton if no PDO provided
    if (!$pdo) {
        try {
            $pdo = Database::getInstance();
        } catch (Exception $e) {
            error_log("Failed to get database instance in getItemImages: " . $e->getMessage());
            return [];
        }
    }

    try {
        // Check if item_images table exists
        $rows = Database::queryAll("SHOW TABLES LIKE 'item_images'");
        if (count($rows) === 0) {
            return [];
        }

        $images = Database::queryAll("\n            SELECT \n                id,\n                sku,\n                image_path,\n                is_primary,\n                sort_order,\n                alt_text,\n                created_at,\n                updated_at\n            FROM item_images \n            WHERE sku = ? \n            ORDER BY is_primary DESC, sort_order ASC\n        ", [$sku]);

        // Strict: if none, return empty

        // Convert boolean values
        foreach ($images as &$image) {
            $image['is_primary'] = (bool)$image['is_primary'];
            $image['sort_order'] = (int)$image['sort_order'];

            // Check if file exists
            $fullPath = __DIR__ . '/../' . $image['image_path'];
            $image['file_exists'] = file_exists($fullPath);
        }

        return $images;

    } catch (PDOException $e) {
        error_log("Database error in getItemImages: " . $e->getMessage());
        // Strict: surface by returning empty
        return [];
    }
}
}

/**
 * Get primary image for an item
 */
if (!function_exists('getPrimaryItemImage')) {
function getPrimaryItemImage($sku, $pdo = null)
{
    $images = getItemImages($sku, $pdo);

    foreach ($images as $image) {
        if ($image['is_primary']) {
            return $image;
        }
    }

    // If no primary image, return first image
    return !empty($images) ? $images[0] : null;
}
}

/**
 * Get fallback image path for an item (for backward compatibility)
 */
if (!function_exists('getItemImagePath')) {
function getItemImagePath($sku, $pdo = null)
{
    $primaryImage = getPrimaryItemImage($sku, $pdo);

    if ($primaryImage && $primaryImage['file_exists']) {
        return $primaryImage['image_path'];
    }

    return null; // No placeholder needed - use CSS fallback
}
}

/**
 * Check if an item has multiple images
 */
if (!function_exists('hasMultipleImages')) {
function hasMultipleImages($sku, $pdo = null)
{
    $images = getItemImages($sku, $pdo);
    return count($images) > 1;
}
}

/**
 * Render item image display (single image or carousel)
 */
if (!function_exists('renderItemImageDisplay')) {
function renderItemImageDisplay($sku, $options = [])
{
    $images = getItemImages($sku);

    $defaults = [
        'showCarousel' => true,
        'height' => '200px',
        'className' => '',
        'showThumbnails' => false,
        'showControls' => true,
        'autoplay' => false
    ];

    $opts = array_merge($defaults, $options);

    if (empty($images)) {
        // Strict: do not inject placeholders; return empty
        error_log('[ItemImages] No images for SKU ' . $sku . '; rendering empty');
        return '';
    }

    if (count($images) === 1 || !$opts['showCarousel']) {
        // Single image display
        $image = $images[0];
        return '<div class="item-single-image ' . $opts['className'] . '" data-height="' . htmlspecialchars($opts['height'], ENT_QUOTES) . '">' .
               '<img src="' . htmlspecialchars($image['image_path']) . '" alt="' . htmlspecialchars($image['alt_text'] ?: 'Item image') . '">' .
               '</div>';
    }

    // Multiple images - use simple carousel (simplified implementation)
    $carouselHtml = '<div class="item-image-carousel" data-height="' . htmlspecialchars($opts['height'], ENT_QUOTES) . '">';
    foreach ($images as $index => $image) {
        $activeClass = $index === 0 ? ' active' : '';
        $carouselHtml .= '<img src="' . htmlspecialchars($image['image_path']) . '" alt="' . htmlspecialchars($image['alt_text'] ?: 'Item image') . '" class="' . $activeClass . '">';
    }
    $carouselHtml .= '</div>';
    return $carouselHtml;
}
}

if (!function_exists('getPrimaryImageUrl')) {
function getPrimaryImageUrl($sku)
{
    try {
        $result = Database::queryOne("SELECT image_path FROM item_images WHERE sku = ? AND is_primary = 1 LIMIT 1", [$sku]);

        if ($result) {
            return getImageUrlWithFallback($result['image_path'], $sku);
        }

        // Try to get any image for this SKU
        $result = Database::queryOne("SELECT image_path FROM item_images WHERE sku = ? LIMIT 1", [$sku]);

        if ($result) {
            return getImageUrlWithFallback($result['image_path'], $sku);
        }

        // Return null - let CSS handle the fallback
        return null;
    } catch (Exception $e) {
        error_log("Error getting primary image for SKU {$sku}: " . $e->getMessage());
        return null;
    }
}
}

if (!function_exists('getAllImagesForSku')) {
function getAllImagesForSku($sku)
{
    try {
        $images = Database::queryAll("SELECT * FROM item_images WHERE sku = ? ORDER BY is_primary DESC, id ASC", [$sku]);

        // Process each image through the fallback system
        foreach ($images as &$image) {
            $image['url'] = getImageUrlWithFallback($image['image_path'], $sku);
        }

        return $images;
    } catch (Exception $e) {
        error_log("Error getting images for SKU {$sku}: " . $e->getMessage());
        return [];
    }
}

}

if (!function_exists('getItemImageForDisplay')) {
function getItemImageForDisplay($item)
{
    // Try database images first
    if (!empty($item['sku'])) {
        $image_url = getPrimaryImageUrl($item['sku']);
        if ($image_url) {
            return $image_url;
        }
    }

    // Try image_url field as fallback
    if (!empty($item['image_url'])) {
        $image_url = getImageUrlWithFallback($item['image_url'], $item['sku'] ?? null);
        if ($image_url) {
            return $image_url;
        }
    }

    // Return null - let CSS handle the fallback
    return null;
}
}
