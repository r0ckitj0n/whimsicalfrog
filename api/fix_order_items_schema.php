<?php
/**
 * Fix Script: Add itemId column to order_items table
 * The table currently has SKU but our code expects itemId
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $results = [];
    
    $results[] = "=== Order Items Schema Fix ===";
    $results[] = "Adding missing itemId column...";
    
    // Step 1: Add itemId column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'itemId'");
    if ($stmt->rowCount() == 0) {
        $results[] = "✓ Adding itemId column...";
        $pdo->exec("ALTER TABLE order_items ADD COLUMN itemId VARCHAR(50) AFTER orderId");
        $results[] = "✓ itemId column added successfully";
    } else {
        $results[] = "✓ itemId column already exists";
    }
    
    // Step 2: Populate itemId from SKU by looking up items table
    $results[] = "\n=== Populating itemId from SKU references ===";
    
    // Get all order_items with SKU but no itemId
    $stmt = $pdo->query("SELECT id, sku FROM order_items WHERE (itemId IS NULL OR itemId = '') AND sku IS NOT NULL");
    $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results[] = "Found " . count($orderItems) . " order items to update";
    
    $updated = 0;
    $skipped = 0;
    
    foreach ($orderItems as $orderItem) {
        $sku = $orderItem['sku'];
        $orderItemId = $orderItem['id'];
        
        // Look up the item ID from the items table using SKU
        $stmt = $pdo->prepare("SELECT id FROM items WHERE sku = ?");
        $stmt->execute([$sku]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            // Update the order_items record with the itemId
            $updateStmt = $pdo->prepare("UPDATE order_items SET itemId = ? WHERE id = ?");
            $updateStmt->execute([$item['id'], $orderItemId]);
            $updated++;
            $results[] = "✓ Updated order item {$orderItemId}: SKU {$sku} → itemId {$item['id']}";
        } else {
            $skipped++;
            $results[] = "⚠️  No item found for SKU: {$sku} (order item: {$orderItemId})";
        }
    }
    
    $results[] = "\n=== Summary ===";
    $results[] = "✓ Updated: {$updated} order items";
    if ($skipped > 0) {
        $results[] = "⚠️  Skipped: {$skipped} order items (no matching items found)";
    }
    
    // Step 3: Verify the fix
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM order_items");
    $total = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as with_itemid FROM order_items WHERE itemId IS NOT NULL AND itemId != ''");
    $withItemId = $stmt->fetchColumn();
    
    $results[] = "\n=== Verification ===";
    $results[] = "Total order items: {$total}";
    $results[] = "Order items with itemId: {$withItemId}";
    $results[] = "Success rate: " . round(($withItemId / $total) * 100, 1) . "%";
    
    // Step 4: Show current table structure
    $stmt = $pdo->query("DESCRIBE order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results[] = "\nFinal table structure:";
    
    foreach ($columns as $column) {
        $results[] = "- " . $column['Field'] . " (" . $column['Type'] . ")";
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Schema fix completed successfully',
        'details' => $results,
        'stats' => [
            'total' => $total,
            'updated' => $updated,
            'skipped' => $skipped,
            'with_itemid' => $withItemId
        ]
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