<?php
/**
 * Script to move images from images/products/ to images/items/
 * and update database references
 */

require_once 'config.php';

echo "Starting image migration from products to items directory...\n";

try {
    // Create database connection
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    $oldDir = __DIR__ . '/../images/products/';
    $newDir = __DIR__ . '/../images/items/';
    
    // Create items directory if it doesn't exist
    if (!is_dir($newDir)) {
        mkdir($newDir, 0755, true);
        echo "Created items directory: $newDir\n";
    }
    
    $movedFiles = 0;
    $errors = [];
    
    // Move all files from products to items directory
    if (is_dir($oldDir)) {
        $files = scandir($oldDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $oldPath = $oldDir . $file;
            $newPath = $newDir . $file;
            
            if (is_file($oldPath)) {
                // Only move if file doesn't already exist in items directory
                if (!file_exists($newPath)) {
                    if (copy($oldPath, $newPath)) {
                        chmod($newPath, 0644);
                        echo "Moved: $file\n";
                        $movedFiles++;
                    } else {
                        $errors[] = "Failed to copy: $file";
                    }
                } else {
                    echo "Already exists in items directory: $file\n";
                }
            }
        }
    }
    
    // Update database references from images/products/ to images/items/
    echo "\nUpdating database references...\n";
    
    // Update product_images table
    $stmt = $pdo->prepare("UPDATE product_images SET image_path = REPLACE(image_path, 'images/products/', 'images/items/') WHERE image_path LIKE 'images/products/%'");
    $productImagesUpdated = $stmt->execute();
    $productImagesRows = $stmt->rowCount();
    echo "Updated $productImagesRows rows in product_images table\n";
    
    // Update items table
    $stmt = $pdo->prepare("UPDATE items SET imageUrl = REPLACE(imageUrl, 'images/products/', 'images/items/') WHERE imageUrl LIKE 'images/products/%'");
    $itemsUpdated = $stmt->execute();
    $itemsRows = $stmt->rowCount();
    echo "Updated $itemsRows rows in items table\n";
    
    // Update products table (if it still exists)
    try {
        $stmt = $pdo->prepare("UPDATE products SET image = REPLACE(image, 'images/products/', 'images/items/') WHERE image LIKE 'images/products/%'");
        $productsUpdated = $stmt->execute();
        $productsRows = $stmt->rowCount();
        echo "Updated $productsRows rows in products table\n";
    } catch (Exception $e) {
        echo "Products table doesn't exist or error updating: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Migration Summary ===\n";
    echo "Files moved: $movedFiles\n";
    echo "Database updates:\n";
    echo "  - product_images: $productImagesRows rows\n";
    echo "  - items: $itemsRows rows\n";
    
    if (!empty($errors)) {
        echo "\nErrors encountered:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
    echo "You can now remove the old images/products/ directory if all files were moved successfully.\n";
    
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
?> 