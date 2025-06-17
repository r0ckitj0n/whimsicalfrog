<?php
/**
 * Item Image Helper Functions
 * Provides utility functions for managing item images
 */

require_once __DIR__ . '/../api/config.php';

/**
 * Get the primary image for an item by SKU
 */
function getPrimaryImageBySku($sku) {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        $stmt = $pdo->prepare("
            SELECT * FROM item_images 
            WHERE sku = ? AND is_primary = 1 
            ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute([$sku]);
        $primaryImage = $stmt->fetch();
        
        if ($primaryImage) {
            return $primaryImage;
        }
        
        // If no primary image, get the first available image
        $stmt = $pdo->prepare("
            SELECT * FROM item_images 
            WHERE sku = ? 
            ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute([$sku]);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Database error in getPrimaryImageBySku: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all images for an item by SKU
 */
function getAllImagesBySku($sku) {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        
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
function hasImagesBySku($sku) {
    $images = getAllImagesBySku($sku);
    return !empty($images);
}

/**
 * Get count of images for an item
 */
function getImageCountBySku($sku) {
    $images = getAllImagesBySku($sku);
    return count($images);
}

/**
 * Get placeholder image info
 */
function getPlaceholderImage() {
    return [
        'image_path' => 'images/items/placeholder.png',
        'alt_text' => 'Placeholder image'
    ];
}

/**
 * Get image URL with fallback to image server if needed
 */
function getImageUrlWithFallback($imagePath) {
    // Check if we're on IONOS hosting
    $isIONOS = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'whimsicalfrog.us';
    
    if ($isIONOS) {
        // On IONOS, use image server as fallback
        $imageUrl = '/' . ltrim($imagePath, '/');
        $fallbackUrl = '/api/image_server.php?image=' . urlencode($imagePath);
        
        // Return the image URL with fallback
        return $imageUrl . '" onerror="this.src=\'' . $fallbackUrl . '\'';  
    } else {
        // Local development - direct path
        return '/' . ltrim($imagePath, '/');
    }
}

/**
 * Get image with fallback handling
 */
function getImageWithFallback($sku) {
    $primaryImage = getPrimaryImageBySku($sku);
    if ($primaryImage && !empty($primaryImage['image_path'])) {
        return $primaryImage['image_path'];
    }
    return 'images/items/placeholder.png';
}

/**
 * Get database connection
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // Detect environment
            $isLocalhost = false;
            
            // Check if running from command line
            if (PHP_SAPI === 'cli') {
                $isLocalhost = true;
            }
            
            // Check HTTP_HOST for localhost indicators
            if (isset($_SERVER['HTTP_HOST'])) {
                if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                    $isLocalhost = true;
                }
            }
            
            // Check SERVER_NAME for localhost indicators
            if (isset($_SERVER['SERVER_NAME'])) {
                if (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false || 
                    strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false) {
                    $isLocalhost = true;
                }
            }
            
            // Database configuration based on environment
            if ($isLocalhost) {
                // Local database credentials
                $host = 'localhost';
                $db   = 'whimsicalfrog';
                $user = 'root';
                $pass = 'Palz2516';
            } else {
                // Production database credentials - IONOS values
                $host = 'db5017975223.hosting-data.io';
                $db   = 'dbs14295502';
                $user = 'dbu2826619';
                $pass = 'Palz2516!';
            }
            
            // Create DSN and options
            $charset = 'utf8mb4';
            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log("Database connection error in getDbConnection: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

/**
 * Get fallback image from old system
 */
function getFallbackItemImage($sku) {
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
function getItemImages($sku, $pdo = null) {
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
function getPrimaryItemImage($sku, $pdo = null) {
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
function getItemImagePath($sku, $pdo = null) {
    $primaryImage = getPrimaryItemImage($sku, $pdo);
    
    if ($primaryImage && $primaryImage['file_exists']) {
        return $primaryImage['image_path'];
    }
    
    return 'images/items/placeholder.png';
}

/**
 * Check if an item has multiple images
 */
function hasMultipleImages($sku, $pdo = null) {
    $images = getItemImages($sku, $pdo);
    return count($images) > 1;
}

/**
 * Render item image display (single image or carousel)
 */
function renderItemImageDisplay($sku, $options = []) {
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
        // Show placeholder
        return '<div class="item-image-placeholder" style="height: ' . $opts['height'] . '; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px;">
            <img src="images/items/placeholder.png" alt="No image available" style="max-width: 100%; max-height: 100%; object-fit: contain;">
        </div>';
    }
    
    if (count($images) === 1 || !$opts['showCarousel']) {
        // Single image display
        $image = $images[0];
        return '<div class="item-single-image ' . $opts['className'] . '" style="height: ' . $opts['height'] . ';">
            <img src="' . htmlspecialchars($image['image_path']) . '" 
                 alt="' . htmlspecialchars($image['alt_text'] ?: 'Item image') . '" 
                 style="width: 100%; height: 100%; object-fit: contain; background: white; border-radius: 8px;"
                 onerror="this.src=\'images/items/placeholder.png\'">
        </div>';
    }
    
    // Multiple images - use carousel
    return renderImageCarousel($sku, $images, $opts);
}
?> 