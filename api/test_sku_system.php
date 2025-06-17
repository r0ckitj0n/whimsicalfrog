<?php
/**
 * Test Script: Verify SKU-based system
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $results = [];
    
    $results[] = "=== SKU-Based System Test ===";
    
    // Test 1: Items table structure
    $results[] = "\n=== Test 1: Items table structure ===";
    $stmt = $pdo->query("DESCRIBE items");
    $itemsColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasId = false;
    $hasSku = false;
    $skuIsPrimary = false;
    
    foreach ($itemsColumns as $column) {
        if ($column['Field'] === 'id') {
            $hasId = true;
        }
        if ($column['Field'] === 'sku') {
            $hasSku = true;
            $skuIsPrimary = ($column['Key'] === 'PRI');
        }
    }
    
    $results[] = "Items table - has id: " . ($hasId ? "❌ FAIL" : "✅ PASS");
    $results[] = "Items table - has sku: " . ($hasSku ? "✅ PASS" : "❌ FAIL");
    $results[] = "Items table - sku is primary: " . ($skuIsPrimary ? "✅ PASS" : "❌ FAIL");
    
    // Test 2: Order_items table structure
    $results[] = "\n=== Test 2: Order_items table structure ===";
    $stmt = $pdo->query("DESCRIBE order_items");
    $orderItemsColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasItemId = false;
    $hasOrderItemsSku = false;
    
    foreach ($orderItemsColumns as $column) {
        if ($column['Field'] === 'itemId') {
            $hasItemId = true;
        }
        if ($column['Field'] === 'sku') {
            $hasOrderItemsSku = true;
        }
    }
    
    $results[] = "Order_items table - has itemId: " . ($hasItemId ? "❌ FAIL" : "✅ PASS");
    $results[] = "Order_items table - has sku: " . ($hasOrderItemsSku ? "✅ PASS" : "❌ FAIL");
    
    // Test 3: Data integrity
    $results[] = "\n=== Test 3: Data integrity ===";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM items");
    $itemCount = $stmt->fetchColumn();
    $results[] = "Total items: " . $itemCount;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM order_items");
    $orderItemCount = $stmt->fetchColumn();
    $results[] = "Total order items: " . $orderItemCount;
    
    // Check for valid SKU references
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM order_items oi 
        INNER JOIN items i ON oi.sku = i.sku
    ");
    $validReferenceCount = $stmt->fetchColumn();
    $results[] = "Order items with valid SKU references: " . $validReferenceCount;
    
    // Check for orphaned order items
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM order_items oi 
        LEFT JOIN items i ON oi.sku = i.sku 
        WHERE i.sku IS NULL
    ");
    $orphanedCount = $stmt->fetchColumn();
    
    if ($orphanedCount > 0) {
        $results[] = "Orphaned order items: " . $orphanedCount . " ❌ FAIL";
        
        // Show orphaned SKUs
        $stmt = $pdo->query("
            SELECT DISTINCT oi.sku 
            FROM order_items oi 
            LEFT JOIN items i ON oi.sku = i.sku 
            WHERE i.sku IS NULL
        ");
        $orphanedSkus = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $results[] = "Orphaned SKUs: " . implode(", ", $orphanedSkus);
    } else {
        $results[] = "Orphaned order items: 0 ✅ PASS";
    }
    
    // Test 4: Sample queries
    $results[] = "\n=== Test 4: Sample queries ===";
    
    // Test order with items query
    $stmt = $pdo->query("
        SELECT 
            o.id as orderId,
            o.customerName,
            oi.sku,
            oi.quantity,
            oi.price,
            i.name as itemName
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.orderId
        LEFT JOIN items i ON oi.sku = i.sku
        LIMIT 3
    ");
    $sampleOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($sampleOrders) > 0) {
        $results[] = "✅ Order-items-items JOIN query works";
        $results[] = "Sample: Order " . $sampleOrders[0]['orderId'] . " has item " . ($sampleOrders[0]['itemName'] ?? 'Unknown');
    } else {
        $results[] = "❌ Order-items-items JOIN query failed";
    }
    
    // Test 5: Overall system status
    $results[] = "\n=== Test 5: Overall system status ===";
    
    $overallPass = !$hasId && $hasSku && $skuIsPrimary && !$hasItemId && $hasOrderItemsSku && ($orphanedCount == 0);
    
    if ($overallPass) {
        $results[] = "🎉 SKU-based system migration: ✅ COMPLETE SUCCESS";
        $results[] = "✅ System simplified to use SKU as single identifier";
        $results[] = "✅ All data integrity checks passed";
        $results[] = "✅ Ready for production use";
    } else {
        $results[] = "⚠️ SKU-based system migration: ❌ NEEDS ATTENTION";
        $results[] = "Some issues found - see details above";
    }
    
    echo json_encode([
        'success' => true,
        'overall_pass' => $overallPass,
        'details' => $results,
        'stats' => [
            'items' => $itemCount,
            'order_items' => $orderItemCount,
            'valid_references' => $validReferenceCount,
            'orphaned_items' => $orphanedCount
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Test error: ' . $e->getMessage()
    ]);
}
?> 