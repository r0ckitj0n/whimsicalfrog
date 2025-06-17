<?php
// Test script to verify shop page image loading
require_once 'includes/product_image_helpers.php';
require_once 'api/config.php';

echo "Testing shop page image loading...\n\n";

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get some sample SKUs from items table
    $stmt = $pdo->query("SELECT sku, name FROM items LIMIT 5");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($items) . " items to test:\n";
    
    foreach ($items as $item) {
        $sku = $item['sku'];
        $name = $item['name'];
        
        echo "\n--- Testing SKU: $sku ($name) ---\n";
        
        // Test the database-driven function
        $primaryImageData = getPrimaryProductImage($sku);
        
        if ($primaryImageData) {
            echo "✅ Primary image found: " . $primaryImageData['image_path'] . "\n";
            echo "   - Is Primary: " . ($primaryImageData['is_primary'] ? 'Yes' : 'No') . "\n";
            echo "   - File Exists: " . ($primaryImageData['file_exists'] ? 'Yes' : 'No') . "\n";
            echo "   - Alt Text: " . ($primaryImageData['alt_text'] ?: 'None') . "\n";
        } else {
            echo "❌ No primary image found\n";
            
            // Check if there are any images in the database for this SKU
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE sku = ?");
            $stmt->execute([$sku]);
            $count = $stmt->fetchColumn();
            echo "   Database images for this SKU: $count\n";
            
            // Check fallback system
            $fallbackImage = getFallbackProductImage($sku);
            if ($fallbackImage) {
                echo "   ✅ Fallback image found: " . $fallbackImage['image_path'] . "\n";
            } else {
                echo "   ❌ No fallback image found\n";
            }
        }
    }
    
    // Check product_images table status
    echo "\n--- Database Status ---\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM product_images");
    $totalImages = $stmt->fetchColumn();
    echo "Total images in database: $totalImages\n";
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT sku) FROM product_images");
    $uniqueSkus = $stmt->fetchColumn();
    echo "Unique SKUs with images: $uniqueSkus\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM product_images WHERE is_primary = 1");
    $primaryImages = $stmt->fetchColumn();
    echo "Primary images: $primaryImages\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?> 