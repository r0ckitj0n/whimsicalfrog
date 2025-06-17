<?php
/**
 * Migration Script: Consolidate to Items-Only System
 * 
 * This script eliminates the products table and consolidates everything
 * into a single "items" table with consistent terminology.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $results = [];
    $results[] = "Starting migration to Items-Only system...";
    
    // Step 1: Create backup of current tables
    $results[] = "\n=== Step 1: Creating backup tables ===";
    $timestamp = date('Y_m_d_H_i_s');
    
    $pdo->exec("CREATE TABLE inventory_backup_$timestamp AS SELECT * FROM inventory");
    $pdo->exec("CREATE TABLE products_backup_$timestamp AS SELECT * FROM products");
    $results[] = "Created backup tables: inventory_backup_$timestamp, products_backup_$timestamp";
    
    // Step 2: Add category column to inventory table (from products table)
    $results[] = "\n=== Step 2: Adding category column to inventory ===";
    
    try {
        $pdo->exec("ALTER TABLE inventory ADD COLUMN category VARCHAR(100) AFTER name");
        $results[] = "Added 'category' column to inventory table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            $results[] = "Category column already exists in inventory table";
        } else {
            throw $e;
        }
    }
    
    // Step 3: Populate category data from products table
    $results[] = "\n=== Step 3: Populating category data ===";
    
    $stmt = $pdo->query("
        UPDATE inventory i 
        JOIN products p ON p.id = i.productId 
        SET i.category = p.productType 
        WHERE i.category IS NULL OR i.category = ''
    ");
    $results[] = "Updated " . $stmt->rowCount() . " inventory records with category data";
    
    // Step 4: Rename inventory table to items
    $results[] = "\n=== Step 4: Renaming inventory table to items ===";
    
    try {
        $pdo->exec("RENAME TABLE inventory TO items");
        $results[] = "Renamed 'inventory' table to 'items'";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Table 'items' already exists") !== false) {
            $results[] = "Items table already exists - skipping rename";
        } else {
            throw $e;
        }
    }
    
    // Step 5: Remove productId column from items table
    $results[] = "\n=== Step 5: Removing productId column ===";
    
    try {
        $pdo->exec("ALTER TABLE items DROP COLUMN productId");
        $results[] = "Removed 'productId' column from items table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "check that column/key exists") !== false) {
            $results[] = "ProductId column already removed";
        } else {
            throw $e;
        }
    }
    
    // Step 6: Update product_images table to remove product_id references
    $results[] = "\n=== Step 6: Cleaning up product_images table ===";
    
    try {
        $pdo->exec("ALTER TABLE product_images DROP COLUMN product_id");
        $results[] = "Removed 'product_id' column from product_images table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "check that column/key exists") !== false) {
            $results[] = "Product_id column already removed from product_images";
        } else {
            throw $e;
        }
    }
    
    // Step 7: Update other tables that reference products
    $results[] = "\n=== Step 7: Updating related tables ===";
    
    // Check if order_items table exists and has productId
    $stmt = $pdo->query("SHOW TABLES LIKE 'order_items'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SHOW COLUMNS FROM order_items LIKE 'productId'");
        if ($stmt->rowCount() > 0) {
            // Update order_items to use SKU if not already done
            try {
                $pdo->exec("ALTER TABLE order_items ADD COLUMN sku VARCHAR(50) AFTER productId");
                $results[] = "Added 'sku' column to order_items table";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                    throw $e;
                }
            }
            
            // Populate SKU data in order_items
            $stmt = $pdo->query("
                UPDATE order_items oi 
                JOIN items i ON oi.productId = i.id 
                SET oi.sku = i.sku 
                WHERE oi.sku IS NULL OR oi.sku = ''
            ");
            $results[] = "Updated " . $stmt->rowCount() . " order_items with SKU data";
            
            // Remove productId from order_items
            try {
                $pdo->exec("ALTER TABLE order_items DROP COLUMN productId");
                $results[] = "Removed 'productId' column from order_items table";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), "check that column/key exists") === false) {
                    throw $e;
                }
            }
        }
    }
    
    // Step 8: Drop the products table
    $results[] = "\n=== Step 8: Dropping products table ===";
    
    try {
        $pdo->exec("DROP TABLE products");
        $results[] = "Dropped 'products' table - no longer needed";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Unknown table") !== false) {
            $results[] = "Products table already dropped";
        } else {
            throw $e;
        }
    }
    
    // Step 9: Add indexes for performance
    $results[] = "\n=== Step 9: Adding indexes ===";
    
    try {
        $pdo->exec("CREATE INDEX idx_items_sku ON items(sku)");
        $results[] = "Added index on items.sku";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("CREATE INDEX idx_items_category ON items(category)");
        $results[] = "Added index on items.category";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            throw $e;
        }
    }
    
    // Step 10: Verify the migration
    $results[] = "\n=== Step 10: Verification ===";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM items");
    $itemCount = $stmt->fetchColumn();
    $results[] = "Items table contains $itemCount records";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM items WHERE category IS NOT NULL AND category != ''");
    $categorizedCount = $stmt->fetchColumn();
    $results[] = "$categorizedCount items have categories assigned";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM items WHERE sku IS NOT NULL AND sku != ''");
    $skuCount = $stmt->fetchColumn();
    $results[] = "$skuCount items have SKUs assigned";
    
    $results[] = "\n=== Migration Summary ===";
    $results[] = "✅ Backup tables created";
    $results[] = "✅ Category data consolidated into items table";
    $results[] = "✅ Inventory table renamed to 'items'";
    $results[] = "✅ ProductId references removed";
    $results[] = "✅ Products table eliminated";
    $results[] = "✅ Related tables updated to use SKU";
    $results[] = "✅ Performance indexes added";
    $results[] = "";
    $results[] = "🎉 Migration to Items-Only system completed successfully!";
    $results[] = "📝 Next step: Update application code to use 'items' terminology";
    
    echo json_encode([
        'success' => true,
        'message' => 'Migration to Items-Only system completed successfully',
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