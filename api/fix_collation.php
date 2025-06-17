<?php
/**
 * Fix Script: Collation mismatch between items.sku and order_items.sku
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $results = [];
    
    $results[] = "=== Collation Fix ===";
    $results[] = "Fixing collation mismatch between items.sku and order_items.sku...";
    
    // Step 1: Check current collations
    $results[] = "\n=== Step 1: Checking current collations ===";
    
    // Check items.sku collation
    $stmt = $pdo->query("
        SELECT COLUMN_NAME, COLLATION_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'items' 
        AND COLUMN_NAME = 'sku'
    ");
    $itemsSkuInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check order_items.sku collation
    $stmt = $pdo->query("
        SELECT COLUMN_NAME, COLLATION_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'order_items' 
        AND COLUMN_NAME = 'sku'
    ");
    $orderItemsSkuInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $results[] = "items.sku: " . $itemsSkuInfo['DATA_TYPE'] . "(" . $itemsSkuInfo['CHARACTER_MAXIMUM_LENGTH'] . ") COLLATE " . $itemsSkuInfo['COLLATION_NAME'];
    $results[] = "order_items.sku: " . $orderItemsSkuInfo['DATA_TYPE'] . "(" . $orderItemsSkuInfo['CHARACTER_MAXIMUM_LENGTH'] . ") COLLATE " . $orderItemsSkuInfo['COLLATION_NAME'];
    
    // Step 2: Standardize to utf8mb4_unicode_ci
    $results[] = "\n=== Step 2: Standardizing collations ===";
    
    $targetCollation = 'utf8mb4_unicode_ci';
    
    if ($itemsSkuInfo['COLLATION_NAME'] !== $targetCollation) {
        $results[] = "Updating items.sku collation...";
        $pdo->exec("ALTER TABLE items MODIFY COLUMN sku VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $results[] = "✓ items.sku updated to utf8mb4_unicode_ci";
    } else {
        $results[] = "✓ items.sku already uses utf8mb4_unicode_ci";
    }
    
    if ($orderItemsSkuInfo['COLLATION_NAME'] !== $targetCollation) {
        $results[] = "Updating order_items.sku collation...";
        $pdo->exec("ALTER TABLE order_items MODIFY COLUMN sku VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $results[] = "✓ order_items.sku updated to utf8mb4_unicode_ci";
    } else {
        $results[] = "✓ order_items.sku already uses utf8mb4_unicode_ci";
    }
    
    // Step 3: Test the join
    $results[] = "\n=== Step 3: Testing JOIN operation ===";
    
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) as count 
            FROM order_items oi 
            INNER JOIN items i ON oi.sku = i.sku
        ");
        $joinCount = $stmt->fetchColumn();
        $results[] = "✅ JOIN test successful - " . $joinCount . " valid references";
    } catch (PDOException $e) {
        $results[] = "❌ JOIN test failed: " . $e->getMessage();
    }
    
    // Step 4: Add foreign key constraint if possible
    $results[] = "\n=== Step 4: Adding foreign key constraint ===";
    
    try {
        $pdo->exec("ALTER TABLE order_items ADD CONSTRAINT fk_order_items_sku FOREIGN KEY (sku) REFERENCES items(sku)");
        $results[] = "✅ Foreign key constraint added successfully";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            $results[] = "✓ Foreign key constraint already exists";
        } else {
            $results[] = "⚠️ Could not add foreign key constraint: " . $e->getMessage();
        }
    }
    
    $results[] = "\n=== Collation Fix Complete ===";
    $results[] = "✅ Both SKU columns now use utf8mb4_unicode_ci";
    $results[] = "✅ JOIN operations should work properly";
    
    echo json_encode([
        'success' => true,
        'message' => 'Collation fix completed successfully',
        'details' => $results
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'details' => $results ?? []
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Fix error: ' . $e->getMessage(),
        'details' => $results ?? []
    ]);
}
?> 