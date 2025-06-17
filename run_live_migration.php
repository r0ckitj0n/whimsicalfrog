<?php
// Script to run migration on live server and test results
echo "<h1>Live Server Migration and Test</h1>";
echo "<pre>";

// Run the migration
echo "=== RUNNING MIGRATION ===\n";
include 'migrate_product_images_to_sku.php';

echo "\n\n=== TESTING RESULTS ===\n";

// Test the shop page image loading
require_once 'includes/product_image_helpers.php';
require_once 'api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get some sample SKUs from items table
    $stmt = $pdo->query("SELECT sku, name FROM items LIMIT 3");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Testing image loading for " . count($items) . " items:\n\n";
    
    foreach ($items as $item) {
        $sku = $item['sku'];
        $name = $item['name'];
        
        echo "--- Testing SKU: $sku ($name) ---\n";
        
        // Test the database-driven function
        $primaryImageData = getPrimaryProductImage($sku);
        
        if ($primaryImageData) {
            echo "✅ Primary image found: " . $primaryImageData['image_path'] . "\n";
            echo "   - Is Primary: " . ($primaryImageData['is_primary'] ? 'Yes' : 'No') . "\n";
            echo "   - File Exists: " . ($primaryImageData['file_exists'] ? 'Yes' : 'No') . "\n";
            echo "   - Alt Text: " . ($primaryImageData['alt_text'] ?: 'None') . "\n";
        } else {
            echo "❌ No primary image found\n";
        }
        echo "\n";
    }
    
    // Check database status
    echo "--- Database Status ---\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM product_images WHERE sku IS NOT NULL");
    $mappedImages = $stmt->fetchColumn();
    echo "Images with SKUs: $mappedImages\n";
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT sku) FROM product_images WHERE sku IS NOT NULL");
    $uniqueSkus = $stmt->fetchColumn();
    echo "Unique SKUs with images: $uniqueSkus\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><strong>Migration and test completed!</strong></p>";
echo "<p><a href='/?page=shop'>Test Shop Page</a> | <a href='/?page=admin&section=inventory'>Test Admin Inventory</a></p>";
?> 