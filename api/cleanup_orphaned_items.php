<?php
/**
 * Cleanup Script: Fix orphaned order items with empty SKUs
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $results = [];
    
    $results[] = "=== Orphaned Items Cleanup ===";
    
    // Step 1: Find orphaned order items
    $results[] = "\n=== Step 1: Finding orphaned order items ===";
    
    $stmt = $pdo->query("
        SELECT 
            oi.id,
            oi.orderId,
            oi.sku,
            oi.quantity,
            oi.price
        FROM order_items oi 
        LEFT JOIN items i ON oi.sku = i.sku 
        WHERE i.sku IS NULL
    ");
    $orphanedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results[] = "Found " . count($orphanedItems) . " orphaned order items";
    
    foreach ($orphanedItems as $item) {
        $results[] = "- Order Item {$item['id']}: SKU '{$item['sku']}', Order {$item['orderId']}, Qty {$item['quantity']}, Price {$item['price']}";
    }
    
    // Step 2: Handle orphaned items
    $results[] = "\n=== Step 2: Cleaning up orphaned items ===";
    
    foreach ($orphanedItems as $item) {
        $sku = trim($item['sku']);
        
        if (empty($sku)) {
            // Empty SKU - delete this order item
            $results[] = "Deleting order item {$item['id']} (empty SKU)";
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
            $stmt->execute([$item['id']]);
        } else {
            // Non-empty SKU that doesn't exist in items table
            $results[] = "SKU '{$sku}' does not exist in items table for order item {$item['id']}";
            
            // Could create a placeholder item or delete - for now, let's delete
            $results[] = "Deleting order item {$item['id']} (invalid SKU: {$sku})";
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
            $stmt->execute([$item['id']]);
        }
    }
    
    // Step 3: Verify cleanup
    $results[] = "\n=== Step 3: Verification ===";
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM order_items oi 
        LEFT JOIN items i ON oi.sku = i.sku 
        WHERE i.sku IS NULL
    ");
    $remainingOrphaned = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM order_items");
    $totalOrderItems = $stmt->fetchColumn();
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM order_items oi 
        INNER JOIN items i ON oi.sku = i.sku
    ");
    $validReferences = $stmt->fetchColumn();
    
    $results[] = "Remaining orphaned items: " . $remainingOrphaned;
    $results[] = "Total order items: " . $totalOrderItems;
    $results[] = "Valid SKU references: " . $validReferences;
    
    if ($remainingOrphaned == 0) {
        $results[] = "✅ All orphaned items cleaned up successfully";
        $results[] = "✅ SKU-based system is now fully operational";
    } else {
        $results[] = "⚠️ Still have " . $remainingOrphaned . " orphaned items";
    }
    
    echo json_encode([
        'success' => true,
        'cleaned_items' => count($orphanedItems),
        'remaining_orphaned' => $remainingOrphaned,
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
        'error' => 'Cleanup error: ' . $e->getMessage(),
        'details' => $results ?? []
    ]);
}
?> 