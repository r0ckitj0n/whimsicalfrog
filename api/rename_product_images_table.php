<?php
/**
 * Migration script to rename product_images table to item_images
 * This completes the product-to-item terminology migration
 */

require_once __DIR__ . '/config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "=== WhimsicalFrog: Rename product_images to item_images ===\n";
    echo "Starting migration...\n\n";
    
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Check if product_images table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'product_images'");
    if ($stmt->rowCount() === 0) {
        echo "❌ product_images table does not exist - migration not needed\n";
        exit;
    }
    
    // Check if item_images already exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'item_images'");
    if ($stmt->rowCount() > 0) {
        echo "❌ item_images table already exists - migration already completed\n";
        exit;
    }
    
    // Get current table structure and data count
    $stmt = $pdo->query("SELECT COUNT(*) FROM product_images");
    $imageCount = $stmt->fetchColumn();
    echo "📊 Found $imageCount images in product_images table\n";
    
    // Rename the table
    echo "🔄 Renaming product_images to item_images...\n";
    $pdo->exec("RENAME TABLE product_images TO item_images");
    
    // Verify the rename worked
    $stmt = $pdo->query("SHOW TABLES LIKE 'item_images'");
    if ($stmt->rowCount() === 0) {
        throw new Exception("Table rename failed - item_images table not found");
    }
    
    // Verify data is intact
    $stmt = $pdo->query("SELECT COUNT(*) FROM item_images");
    $newCount = $stmt->fetchColumn();
    
    if ($newCount !== $imageCount) {
        throw new Exception("Data count mismatch after rename: expected $imageCount, got $newCount");
    }
    
    echo "✅ Successfully renamed product_images to item_images\n";
    echo "✅ All $imageCount image records preserved\n";
    echo "\n=== Migration Complete ===\n";
    echo "Database table naming is now consistent with item terminology\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 