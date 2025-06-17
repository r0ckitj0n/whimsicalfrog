<?php
/**
 * Migration Script: Update order_items table schema
 * Changes productId column to itemId to match the new item-based terminology
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $results = [];
    
    $results[] = "=== Order Items Schema Migration ===";
    $results[] = "Starting migration to rename productId to itemId...";
    
    // Step 1: Check current schema
    $stmt = $pdo->query("DESCRIBE order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results[] = "\nCurrent order_items table structure:";
    
    $hasProductId = false;
    $hasItemId = false;
    
    foreach ($columns as $column) {
        $results[] = "- " . $column['Field'] . " (" . $column['Type'] . ")";
        if ($column['Field'] === 'productId') {
            $hasProductId = true;
        }
        if ($column['Field'] === 'itemId') {
            $hasItemId = true;
        }
    }
    
    // Step 2: Perform migration if needed
    if ($hasProductId && !$hasItemId) {
        $results[] = "\n✓ Found productId column, proceeding with migration...";
        
        // Rename the column
        $pdo->exec("ALTER TABLE order_items CHANGE COLUMN productId itemId VARCHAR(50) NOT NULL");
        $results[] = "✓ Renamed productId column to itemId";
        
    } elseif ($hasItemId && !$hasProductId) {
        $results[] = "\n✓ Schema already migrated - itemId column exists";
        
    } elseif ($hasProductId && $hasItemId) {
        $results[] = "\n⚠️  Both productId and itemId columns exist - manual review needed";
        
    } else {
        $results[] = "\n❌ Neither productId nor itemId column found - table structure issue";
    }
    
    // Step 3: Verify migration
    $stmt = $pdo->query("DESCRIBE order_items");
    $newColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results[] = "\nUpdated order_items table structure:";
    
    foreach ($newColumns as $column) {
        $results[] = "- " . $column['Field'] . " (" . $column['Type'] . ")";
    }
    
    // Step 4: Check data integrity
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM order_items");
    $count = $stmt->fetchColumn();
    $results[] = "\nData integrity check:";
    $results[] = "✓ Total order items: " . $count;
    
    if ($count > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM order_items WHERE itemId IS NOT NULL AND itemId != ''");
        $validItems = $stmt->fetchColumn();
        $results[] = "✓ Valid itemId references: " . $validItems;
    }
    
    $results[] = "\n=== Migration Complete ===";
    
    // Return results
    echo json_encode([
        'success' => true,
        'message' => 'Migration completed successfully',
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
        'error' => 'Migration error: ' . $e->getMessage(),
        'details' => $results ?? []
    ]);
}
?> 