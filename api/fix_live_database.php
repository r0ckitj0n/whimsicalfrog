<?php
header('Content-Type: application/json');

// Include config for database connection
require_once 'config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $results = [];
    
    // Check if we need to rename the table
    $checkTable = $pdo->query("SHOW TABLES LIKE 'product_images'");
    if ($checkTable->rowCount() > 0) {
        $results[] = "Found product_images table, renaming to item_images...";
        
        // Rename the table
        $pdo->exec("RENAME TABLE product_images TO item_images");
        $results[] = "✅ Successfully renamed product_images to item_images";
    } else {
        $results[] = "✅ item_images table already exists";
    }
    
    // Check item_images table structure
    $checkItemImages = $pdo->query("SHOW TABLES LIKE 'item_images'");
    if ($checkItemImages->rowCount() > 0) {
        $results[] = "✅ item_images table exists";
        
        // Check for images with old paths
        $oldPaths = $pdo->query("SELECT COUNT(*) as count FROM item_images WHERE image_path LIKE '%/products/%'")->fetch();
        if ($oldPaths['count'] > 0) {
            $results[] = "Found {$oldPaths['count']} images with old /products/ paths, updating...";
            
            // Update image paths from /products/ to /items/
            $pdo->exec("UPDATE item_images SET image_path = REPLACE(image_path, '/products/', '/items/') WHERE image_path LIKE '%/products/%'");
            $results[] = "✅ Updated image paths from /products/ to /items/";
        } else {
            $results[] = "✅ All image paths are using /items/ directory";
        }
        
        // Get image count
        $imageCount = $pdo->query("SELECT COUNT(*) as count FROM item_images")->fetch();
        $results[] = "📊 Total images in database: {$imageCount['count']}";
    } else {
        $results[] = "❌ item_images table does not exist";
    }
    
    // Check items table
    $itemCount = $pdo->query("SELECT COUNT(*) as count FROM items")->fetch();
    $results[] = "📊 Total items in database: {$itemCount['count']}";
    
    // Check for items without images
    $noImages = $pdo->query("
        SELECT COUNT(*) as count 
        FROM items i 
        LEFT JOIN item_images img ON i.sku = img.sku 
        WHERE img.sku IS NULL
    ")->fetch();
    $results[] = "📊 Items without images: {$noImages['count']}";
    
    // Check order_items foreign key issues
    $invalidOrderItems = $pdo->query("
        SELECT COUNT(*) as count 
        FROM order_items oi 
        LEFT JOIN items i ON oi.sku = i.sku 
        WHERE i.sku IS NULL AND oi.sku IS NOT NULL AND oi.sku != ''
    ")->fetch();
    
    if ($invalidOrderItems['count'] > 0) {
        $results[] = "⚠️ Found {$invalidOrderItems['count']} order items with invalid SKUs";
        
        // Get the invalid SKUs
        $invalidSkus = $pdo->query("
            SELECT DISTINCT oi.sku 
            FROM order_items oi 
            LEFT JOIN items i ON oi.sku = i.sku 
            WHERE i.sku IS NULL AND oi.sku IS NOT NULL AND oi.sku != ''
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        $results[] = "Invalid SKUs: " . implode(', ', $invalidSkus);
    } else {
        $results[] = "✅ All order items have valid SKU references";
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database check and fixes completed',
        'results' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'results' => $results ?? []
    ], JSON_PRETTY_PRINT);
}
?> 