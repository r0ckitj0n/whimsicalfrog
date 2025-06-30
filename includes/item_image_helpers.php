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
        $pdo = getDbConnection();
        if (!$pdo) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            SELECT * FROM item_images 
            WHERE sku = ? AND is_primary = 1 
            ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute([$sku]);
        $primaryImage = $stmt->fetch();
        
        if ($primaryImage) {
            // Add file existence check
            $primaryImage['file_exists'] = file_exists($primaryImage['image_path']);
            return $primaryImage;
        }
        
        // If no primary image, get the first available image
        $stmt = $pdo->prepare("
            SELECT * FROM item_images 
            WHERE sku = ? 
            ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute([$sku]);
        $image = $stmt->fetch();
        
        if ($image) {
            // Add file existence check
            $image['file_exists'] = file_exists($image['image_path']);
            return $image;
        }
        
        return false;
        
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
 * Get placeholder image info - DEPRECATED: Use CSS-only solution instead
 */
function getPlaceholderImage() {
    return [
        'image_path' => null, // No longer using placeholder image
        'alt_text' => 'No image available',
        'css_fallback' => true // Flag to use CSS-only display
    ];
}

/**
 * Get image URL with fallback to image server if needed
 */
function getImageUrlWithFallback($imagePath, $sku = null) {
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

/**
 * Get image with fallback handling
 */
function getImageWithFallback($sku) {
    $primaryImage = getPrimaryImageBySku($sku);
    if ($primaryImage && !empty($primaryImage['image_path'])) {
        return $primaryImage['image_path'];
    }
    return null; // No placeholder needed - use CSS fallback
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
            
            try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
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
    
    return null; // No placeholder needed - use CSS fallback
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
        // Show elegant CSS-only fallback instead of placeholder image
        return '<div class="item-image-placeholder" style="height: ' . $opts['height'] . '; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px; color: #6b7280;">
            <div style="font-size: 3rem; margin-bottom: 0.5rem;">📷</div>
            <div style="font-size: 0.875rem; font-weight: 500;">No Image Available</div>
        </div>';
    }
    
    if (count($images) === 1 || !$opts['showCarousel']) {
        // Single image display
        $image = $images[0];
        return '<div class="item-single-image ' . $opts['className'] . '" style="height: ' . $opts['height'] . ';">
            <img src="' . htmlspecialchars($image['image_path']) . '" 
                 alt="' . htmlspecialchars($image['alt_text'] ?: 'Item image') . '" 
                 style="width: 100%; height: 100%; object-fit: contain; background: white; border-radius: 8px;"
                 onerror="this.style.display=\'none\'; this.parentElement.innerHTML=\'<div style=\\\'height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px; color: #6b7280;\\\'><div style=\\\'font-size: 3rem; margin-bottom: 0.5rem;\\\'>📷</div><div style=\\\'font-size: 0.875rem; font-weight: 500;\\\'>Image Not Found</div></div>\';">
        </div>';
    }
    
    // Multiple images - use simple carousel (simplified implementation)
    $carouselHtml = '<div class="item-image-carousel" style="height: ' . $opts['height'] . ';">';
    foreach ($images as $index => $image) {
        $display = $index === 0 ? 'block' : 'none';
        $carouselHtml .= '<img src="' . htmlspecialchars($image['image_path']) . '" 
                             alt="' . htmlspecialchars($image['alt_text'] ?: 'Item image') . '" 
                             style="width: 100%; height: 100%; object-fit: contain; background: white; border-radius: 8px; display: ' . $display . ';"
                             onerror="this.style.display=\'none\'; this.parentElement.innerHTML+=\'<div style=\\\'height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px; color: #6b7280; display: ' . $display . '\\\'><div style=\\\'font-size: 3rem; margin-bottom: 0.5rem;\\\'>📷</div><div style=\\\'font-size: 0.875rem; font-weight: 500;\\\'>Image Not Found</div></div>\';">';
    }
    $carouselHtml .= '</div>';
    return $carouselHtml;
}

function getPrimaryImageUrl($sku) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT image_path FROM item_images WHERE item_sku = ? AND is_primary = 1 LIMIT 1");
        $stmt->execute([$sku]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return getImageUrlWithFallback($result['image_path'], $sku);
        }
        
        // Try to get any image for this SKU
        $stmt = $pdo->prepare("SELECT image_path FROM item_images WHERE item_sku = ? LIMIT 1");
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

function getAllImagesForSku($sku) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM item_images WHERE item_sku = ? ORDER BY is_primary DESC, id ASC");
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

function getItemImageForDisplay($item) {
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
?>