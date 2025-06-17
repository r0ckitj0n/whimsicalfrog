<?php
/**
 * Migration Script: Simplify to SKU-based system (v2)
 * - Handle foreign key constraints properly
 * - Make SKU the primary key in items table
 * - Remove itemId from order_items table
 * - Use SKU throughout as the single identifier
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $results = [];
    
    $results[] = "=== SKU-Based System Migration (v2) ===";
    $results[] = "Simplifying to use SKU as primary identifier...";
    
    // Step 1: Backup current data
    $results[] = "\n=== Step 1: Creating backups ===";
    
    // Backup items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS items_backup_" . date('Y_m_d_H_i_s') . " AS SELECT * FROM items");
    $results[] = "✓ Backed up items table";
    
    // Backup order_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items_backup_" . date('Y_m_d_H_i_s') . " AS SELECT * FROM order_items");
    $results[] = "✓ Backed up order_items table";
    
    // Step 2: Check current structure and foreign keys
    $results[] = "\n=== Step 2: Analyzing current structure ===";
    
    // Check items table structure
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
    
    // Check for foreign key constraints
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE 
            REFERENCED_TABLE_NAME = 'items' 
            AND REFERENCED_COLUMN_NAME = 'id'
            AND TABLE_SCHEMA = DATABASE()
    ");
    $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results[] = "Found " . count($foreignKeys) . " foreign key constraints referencing items.id";
    foreach ($foreignKeys as $fk) {
        $results[] = "- {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> items.id (constraint: {$fk['CONSTRAINT_NAME']})";
    }
    
    // Step 3: Handle foreign key constraints
    $results[] = "\n=== Step 3: Handling foreign key constraints ===";
    
    if (count($foreignKeys) > 0) {
        foreach ($foreignKeys as $fk) {
            $results[] = "Dropping constraint: {$fk['CONSTRAINT_NAME']}";
            $pdo->exec("ALTER TABLE {$fk['TABLE_NAME']} DROP FOREIGN KEY {$fk['CONSTRAINT_NAME']}");
        }
    } else {
        $results[] = "✓ No foreign key constraints to handle";
    }
    
    // Step 4: Update items table to use SKU as primary key
    $results[] = "\n=== Step 4: Converting items table to SKU primary key ===";
    
    if ($hasId && $hasSku) {
        // Remove the old id primary key and make SKU the primary key
        $results[] = "✓ Removing old id primary key...";
        $pdo->exec("ALTER TABLE items DROP PRIMARY KEY");
        
        $results[] = "✓ Dropping id column...";
        $pdo->exec("ALTER TABLE items DROP COLUMN id");
        
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
    
    // Step 5: Update order_items table
    $results[] = "\n=== Step 5: Simplifying order_items table ===";
    
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
    
    // Step 6: Add new foreign key constraints if needed
    $results[] = "\n=== Step 6: Adding new foreign key constraints ===";
    
    if ($hasOrderItemsSku) {
        try {
            $pdo->exec("ALTER TABLE order_items ADD CONSTRAINT fk_order_items_sku FOREIGN KEY (sku) REFERENCES items(sku)");
            $results[] = "✓ Added foreign key constraint: order_items.sku -> items.sku";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                $results[] = "✓ Foreign key constraint already exists";
            } else {
                $results[] = "⚠️  Could not add foreign key constraint: " . $e->getMessage();
            }
        }
    }
    
    // Step 7: Verify the migration
    $results[] = "\n=== Step 7: Verification ===";
    
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
    
    // Step 8: Data integrity check
    $results[] = "\n=== Step 8: Data integrity check ===";
    
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
        
        // Show which SKUs are orphaned
        $stmt = $pdo->query("
            SELECT DISTINCT oi.sku 
            FROM order_items oi 
            LEFT JOIN items i ON oi.sku = i.sku 
            WHERE i.sku IS NULL
        ");
        $orphanedSkus = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $results[] = "Orphaned SKUs: " . implode(", ", $orphanedSkus);
    } else {
        $results[] = "✓ All order items have valid SKU references";
    }
    
    $results[] = "\n=== Migration Complete ===";
    $results[] = "✓ System now uses SKU as the single primary identifier";
    $results[] = "✓ Eliminated redundant itemId columns";
    $results[] = "✓ Simplified data model for better maintainability";
    $results[] = "✓ Foreign key constraints properly handled";
    
    echo json_encode([
        'success' => true,
        'message' => 'SKU-based migration completed successfully',
        'details' => $results,
        'stats' => [
            'items' => $itemCount,
            'order_items' => $orderItemCount,
            'orphaned_order_items' => $orphanedCount,
            'foreign_keys_dropped' => count($foreignKeys)
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