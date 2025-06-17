<?php
/**
 * Fix Foreign Key Constraints in Inventory Cost Tables
 * Updates the cost breakdown tables to reference SKU instead of old ID column
 */

// Include database configuration
require_once __DIR__ . '/api/config.php';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

echo "🔧 Fixing inventory cost table foreign key constraints...\n";

try {
    // Connect to local database
    echo "📱 Connecting to local database...\n";
    $localDsn = "mysql:host=localhost;dbname=whimsicalfrog;charset=utf8mb4";
    $localUser = "root";
    $localPass = "Palz2516";
    $pdo = new PDO($localDsn, $localUser, $localPass, $options);
    
    $costTables = ['inventory_materials', 'inventory_labor', 'inventory_energy', 'inventory_equipment'];
    
    foreach ($costTables as $table) {
        echo "🔄 Fixing table: $table\n";
        
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if (!$stmt->fetch()) {
            echo "  ⚠️  Table $table does not exist, skipping\n";
            continue;
        }
        
        // Drop the existing foreign key constraint
        echo "  🗑️  Dropping old foreign key constraint...\n";
        try {
            $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY `{$table}_ibfk_1`");
            echo "  ✅ Old foreign key constraint dropped\n";
        } catch (PDOException $e) {
            echo "  ⚠️  Could not drop foreign key (may not exist): " . $e->getMessage() . "\n";
        }
        
        // Rename inventoryId column to sku if it exists
        echo "  🔄 Updating column structure...\n";
        try {
            // Check if inventoryId column exists
            $stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('inventoryId', $columns)) {
                // Rename inventoryId to sku and change type to VARCHAR(20)
                $pdo->exec("ALTER TABLE `$table` CHANGE `inventoryId` `sku` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "  ✅ Column inventoryId renamed to sku\n";
            } else {
                echo "  ℹ️  Column inventoryId does not exist, checking for sku column\n";
                
                if (!in_array('sku', $columns)) {
                    // Add sku column if it doesn't exist
                    $pdo->exec("ALTER TABLE `$table` ADD `sku` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci AFTER `id`");
                    echo "  ✅ Added sku column\n";
                }
            }
        } catch (PDOException $e) {
            echo "  ❌ Error updating column structure: " . $e->getMessage() . "\n";
            continue;
        }
        
        // Add new foreign key constraint referencing items.sku
        echo "  🔗 Adding new foreign key constraint...\n";
        try {
            $pdo->exec("ALTER TABLE `$table` ADD CONSTRAINT `{$table}_sku_fk` FOREIGN KEY (`sku`) REFERENCES `items` (`sku`) ON DELETE CASCADE");
            echo "  ✅ New foreign key constraint added\n";
        } catch (PDOException $e) {
            echo "  ❌ Error adding foreign key constraint: " . $e->getMessage() . "\n";
            continue;
        }
        
        echo "  ✅ Table $table fixed successfully\n";
    }
    
    echo "\n🎉 All inventory cost tables have been updated!\n";
    echo "📊 Summary of changes:\n";
    echo "  • Dropped old foreign key constraints referencing items.id\n";
    echo "  • Renamed inventoryId columns to sku\n"; 
    echo "  • Added new foreign key constraints referencing items.sku\n";
    echo "  • Tables are now compatible with SKU-only system\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Foreign key fix completed! You can now run ./deploy_full.sh to sync these tables to live.\n";
?> 