<?php
/**
 * Migration script to convert existing item colors and sizes to the new global hierarchical system
 * This script will:
 * 1. Migrate existing item colors to global colors
 * 2. Migrate existing item sizes to global sizes  
 * 3. Create item assignments linking items to their sizes and colors
 * 4. Handle items with only colors (create "One Size" default)
 */

require_once __DIR__ . '/config.php';

// Create PDO connection
try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Global Color & Size System Migration ===\n";
echo "Starting migration process...\n\n";

try {
    // Initialize counters
    $stats = [
        'global_colors_created' => 0,
        'global_sizes_created' => 0,
        'item_size_assignments' => 0,
        'item_color_assignments' => 0,
        'items_processed' => 0,
        'errors' => []
    ];

    // Step 1: Get all existing item colors
    echo "Step 1: Migrating item colors to global colors...\n";
    $colorQuery = "SELECT DISTINCT color_name, color_code FROM item_colors WHERE color_name IS NOT NULL AND color_name != ''";
    $colorStmt = $pdo->query($colorQuery);
    $existingColors = $colorStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($existingColors) . " unique colors to migrate\n";
    
    // Insert global colors (ignore duplicates)
    foreach ($existingColors as $color) {
        try {
            $insertColorSql = "INSERT IGNORE INTO global_colors (color_name, color_code, category, is_active) VALUES (?, ?, 'Basic', 1)";
            $insertColorStmt = $pdo->prepare($insertColorSql);
            $insertColorStmt->execute([$color['color_name'], $color['color_code']]);
            
            if ($insertColorStmt->rowCount() > 0) {
                $stats['global_colors_created']++;
                echo "  ✓ Created global color: {$color['color_name']} ({$color['color_code']})\n";
            }
        } catch (Exception $e) {
            $stats['errors'][] = "Failed to create global color {$color['color_name']}: " . $e->getMessage();
        }
    }

    // Step 2: Get all existing item sizes
    echo "\nStep 2: Migrating item sizes to global sizes...\n";
    $sizeQuery = "SELECT DISTINCT size_name FROM item_sizes WHERE size_name IS NOT NULL AND size_name != ''";
    $sizeStmt = $pdo->query($sizeQuery);
    $existingSizes = $sizeStmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($existingSizes) . " unique sizes to migrate\n";
    
    // Insert global sizes (ignore duplicates)
    foreach ($existingSizes as $sizeName) {
        try {
            // Determine category based on size name
            $category = 'Apparel'; // Default
            if (strpos(strtolower($sizeName), 'youth') !== false) {
                $category = 'Youth';
            } elseif (preg_match('/\d+oz/', $sizeName)) {
                $category = 'Drinkware';
            } elseif (preg_match('/\d+x\d+/', $sizeName)) {
                $category = 'Prints';
            }
            
            $insertSizeSql = "INSERT IGNORE INTO global_sizes (size_name, category, display_order, is_active) VALUES (?, ?, 0, 1)";
            $insertSizeStmt = $pdo->prepare($insertSizeSql);
            $insertSizeStmt->execute([$sizeName, $category]);
            
            if ($insertSizeStmt->rowCount() > 0) {
                $stats['global_sizes_created']++;
                echo "  ✓ Created global size: {$sizeName} (Category: {$category})\n";
            }
        } catch (Exception $e) {
            $stats['errors'][] = "Failed to create global size {$sizeName}: " . $e->getMessage();
        }
    }

    // Step 3: Create "One Size" for items that only have colors
    echo "\nStep 3: Creating 'One Size' default...\n";
    try {
        $insertOneSizeSql = "INSERT IGNORE INTO global_sizes (size_name, category, display_order, is_active) VALUES ('One Size', 'Universal', 0, 1)";
        $pdo->exec($insertOneSizeSql);
        echo "  ✓ Created 'One Size' default\n";
    } catch (Exception $e) {
        $stats['errors'][] = "Failed to create 'One Size': " . $e->getMessage();
    }

    // Step 4: Process each item and create assignments
    echo "\nStep 4: Creating item assignments...\n";
    
    // Get all items
    $itemsQuery = "SELECT sku, name FROM items";
    $itemsStmt = $pdo->query($itemsQuery);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Processing " . count($items) . " items...\n";
    
    foreach ($items as $item) {
        $stats['items_processed']++;
        echo "  Processing item: {$item['sku']} - {$item['name']}\n";
        
        // Get item's sizes
        $itemSizesQuery = "SELECT DISTINCT size_name FROM item_sizes WHERE item_sku = ?";
        $itemSizesStmt = $pdo->prepare($itemSizesQuery);
        $itemSizesStmt->execute([$item['sku']]);
        $itemSizes = $itemSizesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get item's colors
        $itemColorsQuery = "SELECT DISTINCT color_name FROM item_colors WHERE item_sku = ?";
        $itemColorsStmt = $pdo->prepare($itemColorsQuery);
        $itemColorsStmt->execute([$item['sku']]);
        $itemColors = $itemColorsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // If no sizes but has colors, use "One Size"
        if (empty($itemSizes) && !empty($itemColors)) {
            $itemSizes = ['One Size'];
            echo "    → Using 'One Size' for color-only item\n";
        }
        
        // Create size assignments
        foreach ($itemSizes as $sizeName) {
            try {
                // Get global size ID
                $getSizeIdSql = "SELECT id FROM global_sizes WHERE size_name = ?";
                $getSizeIdStmt = $pdo->prepare($getSizeIdSql);
                $getSizeIdStmt->execute([$sizeName]);
                $sizeId = $getSizeIdStmt->fetchColumn();
                
                if ($sizeId) {
                    // Insert size assignment
                    $insertSizeAssignSql = "INSERT IGNORE INTO item_size_assignments (item_sku, global_size_id, is_active) VALUES (?, ?, 1)";
                    $insertSizeAssignStmt = $pdo->prepare($insertSizeAssignSql);
                    $insertSizeAssignStmt->execute([$item['sku'], $sizeId]);
                    
                    if ($insertSizeAssignStmt->rowCount() > 0) {
                        $stats['item_size_assignments']++;
                        echo "    ✓ Assigned size: {$sizeName}\n";
                    }
                    
                    // Create color assignments for this size
                    foreach ($itemColors as $colorName) {
                        try {
                            // Get global color ID
                            $getColorIdSql = "SELECT id FROM global_colors WHERE color_name = ?";
                            $getColorIdStmt = $pdo->prepare($getColorIdSql);
                            $getColorIdStmt->execute([$colorName]);
                            $colorId = $getColorIdStmt->fetchColumn();
                            
                            if ($colorId) {
                                // Get current stock and price from existing data
                                $getStockSql = "SELECT stock_level, 0 as price_adjustment FROM item_colors WHERE item_sku = ? AND color_name = ? LIMIT 1";
                                $getStockStmt = $pdo->prepare($getStockSql);
                                $getStockStmt->execute([$item['sku'], $colorName]);
                                $stockData = $getStockStmt->fetch(PDO::FETCH_ASSOC);
                                
                                $stockQuantity = $stockData['stock_level'] ?? 0;
                                $priceAdjustment = $stockData['price_adjustment'] ?? 0.00;
                                
                                // Insert color assignment
                                $insertColorAssignSql = "INSERT IGNORE INTO item_color_assignments (item_sku, global_size_id, global_color_id, stock_level, price_adjustment, is_active) VALUES (?, ?, ?, ?, ?, 1)";
                                $insertColorAssignStmt = $pdo->prepare($insertColorAssignSql);
                                $insertColorAssignStmt->execute([$item['sku'], $sizeId, $colorId, $stockQuantity, $priceAdjustment]);
                                
                                if ($insertColorAssignStmt->rowCount() > 0) {
                                    $stats['item_color_assignments']++;
                                    echo "      ✓ Assigned color: {$colorName} (Stock: {$stockQuantity})\n";
                                }
                            }
                        } catch (Exception $e) {
                            $stats['errors'][] = "Failed to assign color {$colorName} to {$item['sku']}: " . $e->getMessage();
                        }
                    }
                }
            } catch (Exception $e) {
                $stats['errors'][] = "Failed to assign size {$sizeName} to {$item['sku']}: " . $e->getMessage();
            }
        }
    }

    // Display final statistics
    echo "\n=== Migration Complete ===\n";
    echo "Global colors created: {$stats['global_colors_created']}\n";
    echo "Global sizes created: {$stats['global_sizes_created']}\n";
    echo "Items processed: {$stats['items_processed']}\n";
    echo "Size assignments created: {$stats['item_size_assignments']}\n";
    echo "Color assignments created: {$stats['item_color_assignments']}\n";
    
    if (!empty($stats['errors'])) {
        echo "\nErrors encountered:\n";
        foreach ($stats['errors'] as $error) {
            echo "  ❌ {$error}\n";
        }
    } else {
        echo "\n✅ Migration completed successfully with no errors!\n";
    }
    
    echo "\nNext steps:\n";
    echo "1. Test the new Global Colors & Sizes interface in Admin Settings\n";
    echo "2. Verify item assignments are working correctly\n";
    echo "3. Consider backing up old item_colors and item_sizes tables\n";
    echo "4. Update any custom code that references the old tables\n";

} catch (Exception $e) {
    echo "❌ Fatal error during migration: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nMigration script completed.\n";
?> 