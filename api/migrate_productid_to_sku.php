<?php
/**
 * Migration Script: Consolidate Product ID and SKU
 * 
 * This script removes the productId field and uses SKU as the primary identifier
 * throughout the system. It updates all related tables and data.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $results = [];
    $results[] = "Starting Product ID to SKU migration...";
    
    // Step 1: Ensure all inventory items have SKUs
    $results[] = "\n=== Step 1: Ensuring all inventory items have SKUs ===";
    
    $stmt = $pdo->query("SELECT id, productId, sku, name FROM inventory WHERE sku IS NULL OR sku = ''");
    $itemsWithoutSku = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($itemsWithoutSku as $item) {
        // Generate SKU from productId or create new one
        $newSku = $item['productId'] ?: ('WF-GEN-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT));
        
        $updateStmt = $pdo->prepare("UPDATE inventory SET sku = ? WHERE id = ?");
        $updateStmt->execute([$newSku, $item['id']]);
        
        $results[] = "Generated SKU '{$newSku}' for item '{$item['name']}' (ID: {$item['id']})";
    }
    
    // Step 2: Update product_images table to use SKU instead of product_id
    $results[] = "\n=== Step 2: Updating product_images table ===";
    
    // First, add sku column to product_images if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE product_images ADD COLUMN sku VARCHAR(50) AFTER product_id");
        $results[] = "Added 'sku' column to product_images table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            $results[] = "SKU column already exists in product_images table";
        } else {
            throw $e;
        }
    }
    
    // Update product_images with SKU values from inventory
    $stmt = $pdo->query("
        UPDATE product_images pi 
        JOIN inventory i ON pi.product_id = i.productId 
        SET pi.sku = i.sku 
        WHERE pi.sku IS NULL OR pi.sku = ''
    ");
    $results[] = "Updated " . $stmt->rowCount() . " product image records with SKU values";
    
    // Step 3: Update products table to use SKU as primary key
    $results[] = "\n=== Step 3: Updating products table ===";
    
    // Add sku column to products table if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE products ADD COLUMN sku VARCHAR(50) AFTER id");
        $results[] = "Added 'sku' column to products table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            $results[] = "SKU column already exists in products table";
        } else {
            throw $e;
        }
    }
    
    // Update products with SKU values from inventory
    $stmt = $pdo->query("
        UPDATE products p 
        JOIN inventory i ON p.id = i.productId 
        SET p.sku = i.sku 
        WHERE p.sku IS NULL OR p.sku = ''
    ");
    $results[] = "Updated " . $stmt->rowCount() . " product records with SKU values";
    
    // Step 4: Update order_items table to use SKU
    $results[] = "\n=== Step 4: Updating order_items table ===";
    
    // Check if order_items table exists and has productId column
    $stmt = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'productId'");
    if ($stmt->rowCount() > 0) {
        // Add sku column to order_items if it doesn't exist
        try {
            $pdo->exec("ALTER TABLE order_items ADD COLUMN sku VARCHAR(50) AFTER productId");
            $results[] = "Added 'sku' column to order_items table";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                $results[] = "SKU column already exists in order_items table";
            } else {
                throw $e;
            }
        }
        
        // Update order_items with SKU values
        $stmt = $pdo->query("
            UPDATE order_items oi 
            JOIN inventory i ON oi.productId = i.productId 
            SET oi.sku = i.sku 
            WHERE oi.sku IS NULL OR oi.sku = ''
        ");
        $results[] = "Updated " . $stmt->rowCount() . " order item records with SKU values";
    } else {
        $results[] = "order_items table doesn't have productId column - skipping";
    }
    
    // Step 5: Create backup of current data before dropping columns
    $results[] = "\n=== Step 5: Creating backup tables ===";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS inventory_backup_" . date('Y_m_d_H_i_s') . " AS SELECT * FROM inventory");
    $pdo->exec("CREATE TABLE IF NOT EXISTS products_backup_" . date('Y_m_d_H_i_s') . " AS SELECT * FROM products");
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images_backup_" . date('Y_m_d_H_i_s') . " AS SELECT * FROM product_images");
    
    $results[] = "Created backup tables with timestamp";
    
    // Step 6: Drop productId columns (commented out for safety - uncomment when ready)
    $results[] = "\n=== Step 6: Dropping productId columns (COMMENTED OUT FOR SAFETY) ===";
    $results[] = "To complete the migration, uncomment the following lines in the script:";
    $results[] = "// ALTER TABLE inventory DROP COLUMN productId;";
    $results[] = "// ALTER TABLE product_images DROP COLUMN product_id;";
    $results[] = "// ALTER TABLE order_items DROP COLUMN productId; (if exists)";
    
    /*
    // UNCOMMENT THESE LINES WHEN READY TO COMPLETE THE MIGRATION:
    
    // Drop productId from inventory
    $pdo->exec("ALTER TABLE inventory DROP COLUMN productId");
    $results[] = "Dropped productId column from inventory table";
    
    // Drop product_id from product_images
    $pdo->exec("ALTER TABLE product_images DROP COLUMN product_id");
    $results[] = "Dropped product_id column from product_images table";
    
    // Drop productId from order_items if it exists
    $stmt = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'productId'");
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE order_items DROP COLUMN productId");
        $results[] = "Dropped productId column from order_items table";
    }
    */
    
    // Step 7: Add indexes for performance
    $results[] = "\n=== Step 7: Adding indexes ===";
    
    try {
        $pdo->exec("CREATE INDEX idx_inventory_sku ON inventory(sku)");
        $results[] = "Added index on inventory.sku";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            $results[] = "Index on inventory.sku already exists";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_product_images_sku ON product_images(sku)");
        $results[] = "Added index on product_images.sku";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            $results[] = "Index on product_images.sku already exists";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_products_sku ON products(sku)");
        $results[] = "Added index on products.sku";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            $results[] = "Index on products.sku already exists";
        } else {
            throw $e;
        }
    }
    
    $results[] = "\n=== Migration Summary ===";
    $results[] = "✅ All inventory items now have SKUs";
    $results[] = "✅ product_images table updated with SKU column";
    $results[] = "✅ products table updated with SKU column";
    $results[] = "✅ order_items table updated with SKU column (if applicable)";
    $results[] = "✅ Backup tables created";
    $results[] = "✅ Indexes added for performance";
    $results[] = "";
    $results[] = "⚠️  IMPORTANT: productId columns are still present for safety.";
    $results[] = "⚠️  After testing, uncomment the DROP COLUMN statements in this script.";
    $results[] = "⚠️  Then update all application code to use SKU instead of productId.";
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration completed successfully',
        'details' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Migration failed: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?> 