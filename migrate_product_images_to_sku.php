<?php
/**
 * Migration script to add SKU column to product_images table
 * and populate it with correct SKU values based on image paths
 */

require_once 'api/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "Migrating product_images table to use SKUs...\n\n";
    
    // Step 1: Add SKU column if it doesn't exist
    echo "Step 1: Adding SKU column...\n";
    try {
        $pdo->exec("ALTER TABLE product_images ADD COLUMN sku VARCHAR(64) AFTER id");
        echo "✅ SKU column added successfully\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️  SKU column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Step 2: Create mapping from old product IDs to new SKUs
    echo "\nStep 2: Creating mapping...\n";
    $mapping = [
        'TS001' => 'WF-TS-001',
        'TS002' => 'WF-TS-002', 
        'TU001' => 'WF-TU-001',
        'TU002' => 'WF-TU-002',
        'AW001' => 'WF-AR-001',  // This maps to WF-AR-001 (Artwork)
        'GN001' => 'WF-WW-001',  // This is actually the window wrap
        'MG001' => 'WF-SU-002',  // This is the mug/sublimation item
        'TEST001' => 'WF-TEST-001' // Test product
    ];
    
    // Step 3: Update SKU values based on image paths
    echo "\nStep 3: Updating SKU values...\n";
    
    $stmt = $pdo->prepare("UPDATE product_images SET sku = ? WHERE image_path LIKE ?");
    $updated = 0;
    
    foreach ($mapping as $oldId => $newSku) {
        $pattern = "images/products/{$oldId}%";
        $result = $stmt->execute([$newSku, $pattern]);
        
        if ($result) {
            $rowCount = $stmt->rowCount();
            if ($rowCount > 0) {
                echo "✅ Updated $rowCount images: $oldId -> $newSku\n";
                $updated += $rowCount;
            } else {
                echo "ℹ️  No images found for: $oldId\n";
            }
        }
    }
    
    // Step 4: Check for any unmapped images
    echo "\nStep 4: Checking for unmapped images...\n";
    $stmt = $pdo->query("SELECT id, image_path FROM product_images WHERE sku IS NULL OR sku = ''");
    $unmapped = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($unmapped)) {
        echo "⚠️  Found " . count($unmapped) . " unmapped images:\n";
        foreach ($unmapped as $image) {
            echo "   - ID " . $image['id'] . ": " . $image['image_path'] . "\n";
        }
    } else {
        echo "✅ All images have been mapped to SKUs\n";
    }
    
    // Step 5: Add index on SKU column for better performance
    echo "\nStep 5: Adding index on SKU column...\n";
    try {
        $pdo->exec("ALTER TABLE product_images ADD INDEX idx_sku (sku)");
        echo "✅ Index added successfully\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "ℹ️  Index already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Step 6: Show final statistics
    echo "\nStep 6: Final statistics...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM product_images");
    $total = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM product_images WHERE sku IS NOT NULL AND sku != ''");
    $mapped = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT sku) FROM product_images WHERE sku IS NOT NULL AND sku != ''");
    $uniqueSkus = $stmt->fetchColumn();
    
    echo "Total images: $total\n";
    echo "Mapped images: $mapped\n";
    echo "Unique SKUs: $uniqueSkus\n";
    echo "Updated in this run: $updated\n";
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Migration failed!\n";
}
?> 