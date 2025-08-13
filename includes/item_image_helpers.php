<?php

/**
 * Item Image Helper Functions
 * Provides utility functions for managing item images
 */

require_once __DIR__ . '/../api/config.php';
// getPrimaryImageBySku function moved to image_helper.php for centralization

/**
 * Get all images for an item by SKU
 */
function getAllImagesBySku($sku)
{
    try {
        $pdo = getDbConnection();
        if (!$pdo) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT * FROM item_images 
            WHERE sku = ? 
            ORDER BY is_primary DESC, sort_order ASC, id ASC
        ");
        $stmt->execute([$sku]);
        $images = $stmt->fetchAll();

        // Convert boolean values
        foreach ($images as &$image) {
            $image['is_primary'] = (bool)$image['is_primary'];
            $image['sort_order'] = (int)$image['sort_order'];
        }

        return $images;

    } catch (PDOException $e) {
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

    // If we have a SKU, try alternative formats
    if ($sku) {
        $formats = ['webp', 'png', 'jpg', 'jpeg'];
        foreach ($formats as $format) {
            $altPath = "/images/items/{$sku}A.{$format}";
            $altFullPath = __DIR__ . '/../' . ltrim($altPath, '/');
            if (file_exists($altFullPath)) {
                return $altPath;
            }
        }
    }

    // Return null - let CSS handle the fallback
    return null;
}
// getImageWithFallback function moved to image_helper.php for centralization
// getDbConnection function moved to database.php for centralization

/**
 * Get fallback image from old system
 */
function getFallbackItemImage($sku)
{
    // Try common image patterns based on SKU
    $possibleImages = [
        "images/items/{$sku}A.webp",
        "images/items/{$sku}A.png",
        "images/items/{$sku}.webp",
        "images/items/{$sku}.png"
    ];

    // Also try old Product ID patterns for backward compatibility
    // Extract potential Product ID from SKU (if it follows WF-XX-### pattern, try P### format)
    if (preg_match('/^WF-[A-Z]{2}-(\d{3})$/', $sku, $matches)) {
        $productId = 'P' . $matches[1];
        $possibleImages = array_merge($possibleImages, [
            "images/items/{$productId}A.webp",
            "images/items/{$productId}A.png",
            "images/items/{$productId}.webp",
            "images/items/{$productId}.png"
        ]);
    }

    foreach ($possibleImages as $imagePath) {
        $fullPath = __DIR__ . '/../' . $imagePath;
        if (file_exists($fullPath)) {
            return [
                'id' => null,
                'sku' => $sku,
                'image_path' => $imagePath,
                'is_primary' => true,
                'sort_order' => 0,
                'alt_text' => "Item image for {$sku}",
                'file_exists' => true,
                'created_at' => null,
                'updated_at' => null
            ];
        }
    }

    return null;
}

/**
 * Get all images for an item
 */
function getItemImages($sku, $pdo = null)
{
    if (!$pdo) {
        $pdo = getDbConnection();
        if (!$pdo) {
            // Fallback to file system check
            $fallbackImage = getFallbackItemImage($sku);
            return $fallbackImage ? [$fallbackImage] : [];
        }
    }

    try {
        // Check if item_images table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'item_images'");
        if ($stmt->rowCount() === 0) {
            // Table doesn't exist, use fallback
            $fallbackImage = getFallbackItemImage($sku);
            return $fallbackImage ? [$fallbackImage] : [];
        }

        $stmt = $pdo->prepare("
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
        ");

        $stmt->execute([$sku]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If no images found in database, try fallback
        if (empty($images)) {
            $fallbackImage = getFallbackItemImage($sku);
            if ($fallbackImage) {
                return [$fallbackImage];
            }
        }

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
        // Fallback to file system check
        $fallbackImage = getFallbackItemImage($sku);
        return $fallbackImage ? [$fallbackImage] : [];
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
 * Get fallback image path for an item (for backward compatibility)
 */
function getItemImagePath($sku, $pdo = null)
{
    $primaryImage = getPrimaryItemImage($sku, $pdo);

    if ($primaryImage && $primaryImage['file_exists']) {
        return $primaryImage['image_path'];
    }

    return null; // No placeholder needed - use CSS fallback
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
        // Show elegant CSS-only fallback instead of placeholder image
        return '<div class="item-image-placeholder" data-height="' . htmlspecialchars($opts['height'], ENT_QUOTES) . '">
<div class="placeholder-icon">📷</div>
<div class="placeholder-text">No Image Available</div>
</div>';
    }

    if (count($images) === 1 || !$opts['showCarousel']) {
        // Single image display
        $image = $images[0];
        return '<div class="item-single-image ' . $opts['className'] . '" data-height="' . htmlspecialchars($opts['height'], ENT_QUOTES) . '">' .
               '<img src="' . htmlspecialchars($image['image_path']) . '" alt="' . htmlspecialchars($image['alt_text'] ?: 'Item image') . '" data-fallback="placeholder">' .
               '</div>';
    }

    // Multiple images - use simple carousel (simplified implementation)
    $carouselHtml = '<div class="item-image-carousel" data-height="' . htmlspecialchars($opts['height'], ENT_QUOTES) . '">';
    foreach ($images as $index => $image) {
        $activeClass = $index === 0 ? ' active' : '';
        $carouselHtml .= '<img src="' . htmlspecialchars($image['image_path']) . '" alt="' . htmlspecialchars($image['alt_text'] ?: 'Item image') . '" class="' . $activeClass . '" data-fallback="placeholder">';
    }
    $carouselHtml .= '</div>';
    return $carouselHtml;
}

function getPrimaryImageUrl($sku)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT image_path FROM item_images WHERE sku = ? AND is_primary = 1 LIMIT 1");
        $stmt->execute([$sku]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return getImageUrlWithFallback($result['image_path'], $sku);
        }

        // Try to get any image for this SKU
        $stmt = $pdo->prepare("SELECT image_path FROM item_images WHERE sku = ? LIMIT 1");
        $stmt->execute([$sku]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

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
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT * FROM item_images WHERE sku = ? ORDER BY is_primary DESC, id ASC");
        $stmt->execute([$sku]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
