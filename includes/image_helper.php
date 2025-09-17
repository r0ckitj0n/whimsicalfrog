<?php

/**
 * WhimsicalFrog Image Handling and Display Utilities
 * Centralized functions to eliminate duplication and improve maintainability
 * Generated: 2025-07-01 23:15:56
 */

// Include utility functions
require_once __DIR__ . '/functions.php';


/**
 * Get image with fallback handling
 */
function getImageWithFallback($sku)
{
    $primaryImage = getPrimaryImageBySku($sku);
    if ($primaryImage && !empty($primaryImage['image_path'])) {
        return $primaryImage['image_path'];
    }
    return null; // No placeholder needed - use CSS fallback
}


/**
 * Get the primary image for an item by SKU
 */
function getPrimaryImageBySku($sku)
{
    try {
        $primaryImage = Database::queryOne("\n            SELECT * FROM item_images \n            WHERE sku = ? AND is_primary = 1 \n            ORDER BY id ASC LIMIT 1\n        ", [$sku]);

        if ($primaryImage) {
            // Add file existence check
            $primaryImage['file_exists'] = file_exists($primaryImage['image_path']);
            return $primaryImage;
        }

        // If no primary image, get the first available image
        $image = Database::queryOne("\n            SELECT * FROM item_images \n            WHERE sku = ? \n            ORDER BY id ASC LIMIT 1\n        ", [$sku]);

        if ($image) {
            // Add file existence check
            $image['file_exists'] = file_exists($image['image_path']);
            return $image;
        }

        return false;

    } catch (Exception $e) {
        error_log("Database error in getPrimaryImageBySku: " . $e->getMessage());
        return false;
    }
}


/**
 * Get all images for an item by SKU
 */
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


/**
 * Check if an item has images
 */
function hasImagesBySku($sku)
{
    $images = getAllImagesBySku($sku);
    return !empty($images);
}


/**
 * Get count of images for an item
 */
function getImageCountBySku($sku)
{
    $images = getAllImagesBySku($sku);
    return count($images);
}


/**
 * Get placeholder image info - DEPRECATED: Use CSS-only solution instead
 */
function getPlaceholderImage()
{
    return [
        'image_path' => null, // No longer using placeholder image
        'alt_text' => 'No image available',
        'css_fallback' => true // Flag to use CSS-only display
    ];
}


/**
 * Get image URL with fallback to image server if needed
 */
function getImageUrlWithFallback($imagePath, $sku = null)
{
    if (empty($imagePath)) {
        return null; // Let CSS handle the fallback
    }

    // Check if the image file exists
    $fullPath = __DIR__ . '/../' . ltrim($imagePath, '/');
    if (file_exists($fullPath)) {
        return $imagePath;
    }

    // Strict: do not attempt to guess alternative files; surface missing asset
    return null;
}


/**
 * Get all images for an item
 */
function getItemImages($sku, $pdo = null)
{
    if (!$pdo) { $pdo = getDbConnection(); }
    if (!$pdo) { return []; }

    try {
        // Check if item_images table exists
        $tables = Database::queryAll("SHOW TABLES LIKE 'item_images'");
        if (count($tables) === 0) { return []; }

        $images = Database::queryAll(
            "
            SELECT 
                id,
                sku,
                image_path,
                is_primary,
                sort_order,
                alt_text,
                created_at,
                updated_at
            FROM item_images 
            WHERE sku = ? 
            ORDER BY is_primary DESC, sort_order ASC
        ", [$sku]);

        // Strict: if no images in database, return empty

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


/**
 * Get primary image for an item
 */
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


/**
 * Check if an item has multiple images
 */
function hasMultipleImages($sku, $pdo = null)
{
    $images = getItemImages($sku, $pdo);
    return count($images) > 1;
}


/**
 * Render item image display (single image or carousel)
 */
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
        // Strict: do not inject placeholders. Emit a hidden marker div for admin diagnostics.
        error_log('[Images] No images found for SKU ' . $sku . '; rendering empty output');
        return '<div class="wf-missing-item-image" hidden></div>';
    }

    if (count($images) === 1 || !$opts['showCarousel']) {
        // Single image display
        $image = $images[0];
        return '<div class="item-single-image ' . $opts['className'] . '" data-height="' . htmlspecialchars($opts['height'], ENT_QUOTES) . '">\n            <img src="' . htmlspecialchars($image['image_path']) . '" \n                 alt="' . htmlspecialchars($image['alt_text'] ?: 'Item image') . '">\n        </div>';
    }

    // Multiple images - use simple carousel (simplified implementation)
    $carouselHtml = '<div class="item-image-carousel" data-height="' . htmlspecialchars($opts['height'], ENT_QUOTES) . '">';
    foreach ($images as $index => $image) {
        $activeClass = $index === 0 ? ' active' : '';
        $carouselHtml .= '<img src="' . htmlspecialchars($image['image_path']) . '" \n                             alt="' . htmlspecialchars($image['alt_text'] ?: 'Item image') . '" \n                             class="' . $activeClass . '">';
    }
    $carouselHtml .= '</div>';
    return $carouselHtml;
}


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


function getItemImageForDisplay($item)
{
    // Try database images first
    if (!empty($item['sku'])) {
        $imageUrl = getPrimaryImageUrl($item['sku']);
        if ($imageUrl) {
            return $imageUrl;
        }
    }

    // Try imageUrl field as fallback
    if (!empty($item['imageUrl'])) {
        $imageUrl = getImageUrlWithFallback($item['imageUrl'], $item['sku'] ?? null);
        if ($imageUrl) {
            return $imageUrl;
        }
    }

    // Return null - let CSS handle the fallback
    return null;
}
