<?php
/**
 * Migration Script: Simplify to SKU-based system
 * - Make SKU the primary key in items table
 * - Remove itemId from order_items table
 * - Use SKU throughout as the single identifier
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $results = [];
    
    $results[] = "=== SKU-Based System Migration ===";
    $results[] = "Simplifying to use SKU as primary identifier...";
    
    // Step 1: Backup current data
    $results[] = "\n=== Step 1: Creating backups ===";
    
    // Backup items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS items_backup_" . date('Y_m_d_H_i_s') . " AS SELECT * FROM items");
    $results[] = "✓ Backed up items table";
    
    // Backup order_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items_backup_" . date('Y_m_d_H_i_s') . " AS SELECT * FROM order_items");
    $results[] = "✓ Backed up order_items table";
    
    // Step 2: Check current items table structure
    $results[] = "\n=== Step 2: Analyzing current structure ===";
    $stmt = $pdo->query("DESCRIBE items");
    $itemsColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasId = false;
    $hasSku = false;
    
    foreach ($itemsColumns as $column) {
        if ($column['Field'] === 'id') {
            $hasId = true;
        }
        if ($column['Field'] === 'sku') {
            $hasSku = true;
        }
    }
    
    $results[] = "Items table - has id: " . ($hasId ? "Yes" : "No") . ", has sku: " . ($hasSku ? "Yes" : "No");
    
    // Step 3: Update items table to use SKU as primary key
    $results[] = "\n=== Step 3: Converting items table to SKU primary key ===";
    
    if ($hasId && $hasSku) {
        // Remove the old id primary key and make SKU the primary key
        $results[] = "✓ Removing old id primary key...";
        $pdo->exec("ALTER TABLE items DROP PRIMARY KEY");
        
        if ($hasId) {
            $results[] = "✓ Dropping id column...";
            $pdo->exec("ALTER TABLE items DROP COLUMN id");
        }
        
        $results[] = "✓ Making SKU the primary key...";
        $pdo->exec("ALTER TABLE items ADD PRIMARY KEY (sku)");
        
    } elseif ($hasSku && !$hasId) {
        $results[] = "✓ Items table already uses SKU (no id column found)";
        
        // Make sure SKU is primary key if it isn't already
        $stmt = $pdo->query("SHOW KEYS FROM items WHERE Key_name = 'PRIMARY'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE items ADD PRIMARY KEY (sku)");
            $results[] = "✓ Made SKU the primary key";
        } else {
            $results[] = "✓ SKU is already the primary key";
        }
    }
    
    // Step 4: Update order_items table
    $results[] = "\n=== Step 4: Simplifying order_items table ===";
    
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
    
    $results[] = "Order_items table - has itemId: " . ($hasItemId ? "Yes" : "No") . ", has sku: " . ($hasOrderItemsSku ? "Yes" : "No");
    
    if ($hasItemId && $hasOrderItemsSku) {
        $results[] = "✓ Removing redundant itemId column from order_items...";
        $pdo->exec("ALTER TABLE order_items DROP COLUMN itemId");
        $results[] = "✓ order_items now uses only SKU for item references";
    } elseif ($hasItemId && !$hasOrderItemsSku) {
        $results[] = "⚠️  order_items has itemId but no sku - this needs manual review";
    } else {
        $results[] = "✓ order_items already simplified (using SKU only)";
    }
    
    // Step 5: Verify the migration
    $results[] = "\n=== Step 5: Verification ===";
    
    // Check items table
    $stmt = $pdo->query("DESCRIBE items");
    $finalItemsColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results[] = "Final items table structure:";
    foreach ($finalItemsColumns as $column) {
        $isPrimary = $column['Key'] === 'PRI' ? ' (PRIMARY KEY)' : '';
        $results[] = "- " . $column['Field'] . " (" . $column['Type'] . ")" . $isPrimary;
    }
    
    // Check order_items table
    $stmt = $pdo->query("DESCRIBE order_items");
    $finalOrderItemsColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results[] = "\nFinal order_items table structure:";
    foreach ($finalOrderItemsColumns as $column) {
        $results[] = "- " . $column['Field'] . " (" . $column['Type'] . ")";
    }
    
    // Step 6: Data integrity check
    $results[] = "\n=== Step 6: Data integrity check ===";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM items");
    $itemCount = $stmt->fetchColumn();
    $results[] = "✓ Total items: " . $itemCount;
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM order_items");
    $orderItemCount = $stmt->fetchColumn();
    $results[] = "✓ Total order items: " . $orderItemCount;
    
    // Check for orphaned order items (SKUs that don't exist in items table)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM order_items oi 
        LEFT JOIN items i ON oi.sku = i.sku 
        WHERE i.sku IS NULL
    ");
    $orphanedCount = $stmt->fetchColumn();
    
    if ($orphanedCount > 0) {
        $results[] = "⚠️  Found {$orphanedCount} orphaned order items (SKUs not in items table)";
    } else {
        $results[] = "✓ All order items have valid SKU references";
    }
    
    $results[] = "\n=== Migration Complete ===";
    $results[] = "✓ System now uses SKU as the single primary identifier";
    $results[] = "✓ Eliminated redundant itemId columns";
    $results[] = "✓ Simplified data model for better maintainability";
    
    echo json_encode([
        'success' => true,
        'message' => 'SKU-based migration completed successfully',
        'details' => $results,
        'stats' => [
            'items' => $itemCount,
            'order_items' => $orderItemCount,
            'orphaned_order_items' => $orphanedCount
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
        'error' => 'Migration error: ' . $e->getMessage(),
        'details' => $results ?? []
    ]);
}
?> 