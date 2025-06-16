<?php
/**
 * Product Image Helper Functions
 * 
 * Helper functions for retrieving and managing product images
 */

/**
 * Get database connection
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        // Determine environment and set database credentials directly
        $isLocalhost = false;
        
        // Check if running locally
        if (PHP_SAPI === 'cli' || 
            (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) ||
            (isset($_SERVER['SERVER_NAME']) && (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false || strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false))) {
            $isLocalhost = true;
        }
        
        // Override via environment variable if set
        if (isset($_SERVER['WHF_ENV']) && $_SERVER['WHF_ENV'] === 'prod') {
            $isLocalhost = false;
        }
        if (isset($_SERVER['WHF_ENV']) && $_SERVER['WHF_ENV'] === 'local') {
            $isLocalhost = true;
        }
        
        // Set database credentials based on environment
        if ($isLocalhost) {
            // Local database credentials
            $host = 'localhost';
            $db   = 'whimsicalfrog';
            $user = 'root';
            $pass = 'Palz2516';
        } else {
            // Production database credentials
            $host = 'db5017975223.hosting-data.io';
            $db   = 'dbs14295502';
            $user = 'dbu2826619';
            $pass = 'Palz2516!';
        }
        
        $charset = 'utf8mb4';
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        try {
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
function getFallbackProductImage($sku) {
    // Try common image patterns based on SKU
    $possibleImages = [
        "images/products/{$sku}A.webp",
        "images/products/{$sku}A.png",
        "images/products/{$sku}.webp", 
        "images/products/{$sku}.png"
    ];
    
    // Also try old Product ID patterns for backward compatibility
    // Extract potential Product ID from SKU (if it follows WF-XX-### pattern, try P### format)
    if (preg_match('/^WF-[A-Z]{2}-(\d{3})$/', $sku, $matches)) {
        $productId = 'P' . $matches[1];
        $possibleImages = array_merge($possibleImages, [
            "images/products/{$productId}A.webp",
            "images/products/{$productId}A.png",
            "images/products/{$productId}.webp", 
            "images/products/{$productId}.png"
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
                'alt_text' => "Product image for {$sku}",
                'file_exists' => true,
                'created_at' => null,
                'updated_at' => null
            ];
        }
    }
    
    return null;
}

/**
 * Get all images for a product
 */
function getProductImages($sku, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
        if (!$pdo) {
            // Fallback to file system check
            $fallbackImage = getFallbackProductImage($sku);
            return $fallbackImage ? [$fallbackImage] : [];
        }
    }
    
    try {
        // Check if product_images table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'product_images'");
        if ($stmt->rowCount() === 0) {
            // Table doesn't exist, use fallback
            $fallbackImage = getFallbackProductImage($sku);
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
            FROM product_images 
            WHERE sku = ? 
            ORDER BY is_primary DESC, sort_order ASC
        ");
        
        $stmt->execute([$sku]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no images found in database, try fallback
        if (empty($images)) {
            $fallbackImage = getFallbackProductImage($sku);
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
        error_log("Database error in getProductImages: " . $e->getMessage());
        // Fallback to file system check
        $fallbackImage = getFallbackProductImage($sku);
        return $fallbackImage ? [$fallbackImage] : [];
    }
}

/**
 * Get primary image for a product
 */
function getPrimaryProductImage($sku, $pdo = null) {
    $images = getProductImages($sku, $pdo);
    
    foreach ($images as $image) {
        if ($image['is_primary']) {
            return $image;
        }
    }
    
    // If no primary image, return first image
    return !empty($images) ? $images[0] : null;
}

/**
 * Get fallback image path for a product (for backward compatibility)
 */
function getProductImagePath($sku, $pdo = null) {
    $primaryImage = getPrimaryProductImage($sku, $pdo);
    
    if ($primaryImage && $primaryImage['file_exists']) {
        return $primaryImage['image_path'];
    }
    
    return 'images/products/placeholder.png';
}

/**
 * Check if a product has multiple images
 */
function hasMultipleImages($sku, $pdo = null) {
    $images = getProductImages($sku, $pdo);
    return count($images) > 1;
}

/**
 * Render product image display (single image or carousel)
 */
function renderProductImageDisplay($sku, $options = []) {
    $images = getProductImages($sku);
    
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
        return '<div class="product-image-placeholder" style="height: ' . $opts['height'] . '; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px;">
            <img src="images/products/placeholder.png" alt="No image available" style="max-width: 100%; max-height: 100%; object-fit: contain;">
        </div>';
    }
    
    if (count($images) === 1 || !$opts['showCarousel']) {
        // Single image display
        $image = $images[0];
        return '<div class="product-single-image ' . $opts['className'] . '" style="height: ' . $opts['height'] . ';">
            <img src="' . htmlspecialchars($image['image_path']) . '" 
                 alt="' . htmlspecialchars($image['alt_text'] ?: 'Product image') . '" 
                 style="width: 100%; height: 100%; object-fit: contain; background: white; border-radius: 8px;"
                 onerror="this.src=\'images/products/placeholder.png\'">
        </div>';
    }
    
    // Multiple images - use carousel
    return renderImageCarousel($sku, $images, $opts);
}
?> 