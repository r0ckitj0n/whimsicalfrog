<?php
// Initialize Item Sizes Database Tables
require_once __DIR__ . '/config.php';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    echo "Connected to database successfully.\n";

    // Create item_sizes table
    $createItemSizesTable = "
    CREATE TABLE IF NOT EXISTS item_sizes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_sku VARCHAR(64) NOT NULL,
        color_id INT DEFAULT NULL,
        size_name VARCHAR(50) NOT NULL,
        size_code VARCHAR(10) NOT NULL,
        stock_level INT DEFAULT 0,
        price_adjustment DECIMAL(10,2) DEFAULT 0.00,
        is_active TINYINT(1) DEFAULT 1,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (item_sku) REFERENCES items(sku) ON DELETE CASCADE,
        FOREIGN KEY (color_id) REFERENCES item_colors(id) ON DELETE CASCADE,
        UNIQUE KEY unique_item_color_size (item_sku, color_id, size_name),
        INDEX idx_item_sku (item_sku),
        INDEX idx_color_id (color_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($createItemSizesTable);
    echo "Created item_sizes table.\n";

    // First, let's get the color IDs for our sample data
    $colorQuery = $pdo->query("
        SELECT id, item_sku, color_name 
        FROM item_colors 
        WHERE item_sku IN ('WF-TS-001', 'WF-TS-002') 
        ORDER BY item_sku, color_name
    ");
    $colors = $colorQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a lookup array for color IDs
    $colorLookup = [];
    foreach ($colors as $color) {
        $colorLookup[$color['item_sku']][$color['color_name']] = $color['id'];
    }

    // Insert sample size data - Mixed scenarios
    $sampleSizes = [];
    
    // Scenario 1: WF-TS-001 and WF-TS-002 - Sizes within colors (multi-color with sizes)
    if (isset($colorLookup['WF-TS-001'])) {
        foreach ($colorLookup['WF-TS-001'] as $colorName => $colorId) {
            $baseStock = $colorName === 'Black' ? 5 : ($colorName === 'White' ? 7 : 4);
            $sampleSizes[] = [$colorId, 'WF-TS-001', 'XS', 'XS', $baseStock, 0.00, 1];
            $sampleSizes[] = [$colorId, 'WF-TS-001', 'Small', 'S', $baseStock + 2, 0.00, 2];
            $sampleSizes[] = [$colorId, 'WF-TS-001', 'Medium', 'M', $baseStock + 3, 0.00, 3];
            $sampleSizes[] = [$colorId, 'WF-TS-001', 'Large', 'L', $baseStock + 2, 0.00, 4];
            $sampleSizes[] = [$colorId, 'WF-TS-001', 'XL', 'XL', $baseStock + 1, 1.00, 5];
            $sampleSizes[] = [$colorId, 'WF-TS-001', 'XXL', 'XXL', $baseStock, 2.00, 6];
        }
    }
    
    if (isset($colorLookup['WF-TS-002'])) {
        foreach ($colorLookup['WF-TS-002'] as $colorName => $colorId) {
            $baseStock = $colorName === 'Red' ? 3 : 4;
            $sampleSizes[] = [$colorId, 'WF-TS-002', 'Small', 'S', $baseStock, 0.00, 1];
            $sampleSizes[] = [$colorId, 'WF-TS-002', 'Medium', 'M', $baseStock + 2, 0.00, 2];
            $sampleSizes[] = [$colorId, 'WF-TS-002', 'Large', 'L', $baseStock + 1, 0.00, 3];
            $sampleSizes[] = [$colorId, 'WF-TS-002', 'XL', 'XL', $baseStock, 1.00, 4];
            $sampleSizes[] = [$colorId, 'WF-TS-002', 'XXL', 'XXL', $baseStock - 1, 2.00, 5];
        }
    }
    
    // Scenario 2: WF-TS-003, WF-TS-004, WF-TS-005 - General sizes (single color with multiple sizes)
    $generalSizeItems = [
        'WF-TS-003' => ['stock_base' => 8, 'sizes' => ['S', 'M', 'L', 'XL']],
        'WF-TS-004' => ['stock_base' => 6, 'sizes' => ['XS', 'S', 'M', 'L', 'XL', 'XXL']],
        'WF-TS-005' => ['stock_base' => 10, 'sizes' => ['S', 'M', 'L', 'XL', 'XXL']]
    ];
    
    foreach ($generalSizeItems as $itemSku => $config) {
        $order = 1;
        foreach ($config['sizes'] as $sizeCode) {
            $sizeName = $sizeCode === 'XS' ? 'Extra Small' : 
                       ($sizeCode === 'S' ? 'Small' : 
                       ($sizeCode === 'M' ? 'Medium' : 
                       ($sizeCode === 'L' ? 'Large' : 
                       ($sizeCode === 'XL' ? 'Extra Large' : 
                       ($sizeCode === 'XXL' ? 'Double XL' : $sizeCode)))));
            
            $stock = $config['stock_base'] + rand(-2, 3);
            $priceAdjustment = in_array($sizeCode, ['XL', 'XXL']) ? ($sizeCode === 'XXL' ? 2.00 : 1.00) : 0.00;
            
            // NULL for color_id means it's a general size for the item
            $sampleSizes[] = [null, $itemSku, $sizeName, $sizeCode, $stock, $priceAdjustment, $order];
            $order++;
        }
    }

    // Insert all size data
    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO item_sizes (color_id, item_sku, size_name, size_code, stock_level, price_adjustment, display_order) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($sampleSizes as $size) {
        $insertStmt->execute($size);
    }

    echo "Inserted " . count($sampleSizes) . " size records.\n";
    
    // Update color stock levels to match size totals for color-specific sizes
    echo "Updating color stock levels to match size totals...\n";
    $updateColorStock = $pdo->prepare("
        UPDATE item_colors ic 
        SET stock_level = (
            SELECT COALESCE(SUM(stock_level), 0) 
            FROM item_sizes 
            WHERE color_id = ic.id AND is_active = 1
        )
        WHERE EXISTS (
            SELECT 1 FROM item_sizes WHERE color_id = ic.id
        )
    ");
    $updateColorStock->execute();
    
    // Update main item stock levels to match total size quantities
    echo "Updating main item stock levels to match size totals...\n";
    $updateItemStock = $pdo->prepare("
        UPDATE items i 
        SET stockLevel = (
            SELECT COALESCE(SUM(stock_level), i.stockLevel) 
            FROM item_sizes 
            WHERE item_sku = i.sku AND is_active = 1
        )
        WHERE EXISTS (
            SELECT 1 FROM item_sizes WHERE item_sku = i.sku
        )
    ");
    $updateItemStock->execute();

    echo "Item sizes database initialization completed successfully!\n";
    echo "\nSize Configuration Summary:\n";
    echo "- Items with color-specific sizes: WF-TS-001, WF-TS-002\n";
    echo "- Items with general sizes only: WF-TS-003, WF-TS-004, WF-TS-005\n";
    echo "- Size options: XS, S, M, L, XL, XXL with price adjustments for larger sizes\n";
    echo "- Stock levels automatically synced with color and item totals\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 