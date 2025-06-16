<?php
/**
 * Rename Product Images Script
 * 
 * This script renames existing product images from the old category-based format (TS001A.png)
 * to the new SKU format (WF-TS-001A.png) based on the inventory data.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $results = [];
    $results[] = "Starting image renaming process...";
    
    // Get all inventory items with their SKUs
    $stmt = $pdo->query("SELECT id, sku, imageUrl FROM inventory WHERE sku IS NOT NULL AND sku != ''");
    $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results[] = "Found " . count($inventoryItems) . " inventory items to process";
    
    // Get all existing images
    $imageDir = __DIR__ . '/../images/products/';
    $imageExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    $allFiles = scandir($imageDir);
    $existingImages = [];
    
    foreach ($allFiles as $file) {
        if ($file === '.' || $file === '..') continue;
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($extension, $imageExtensions)) {
            $existingImages[] = $file;
        }
    }
    
    $results[] = "Found " . count($existingImages) . " existing images: " . implode(', ', $existingImages);
    
    $renamedCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    
    // Create mapping of old format to new SKU format
    $categoryMapping = [
        'TS' => 'WF-TS-', // T-Shirts
        'TU' => 'WF-TU-', // Tumblers
        'AW' => 'WF-AR-', // Artwork (AR in SKU)
        'AR' => 'WF-AR-', // Artwork
        'SU' => 'WF-SU-', // Sublimation
        'WW' => 'WF-WW-', // Window Wraps
        'GN' => 'WF-GEN-', // General
        'MG' => 'WF-GEN-'  // Might be general/misc
    ];
    
    foreach ($inventoryItems as $item) {
        $sku = $item['sku'];
        $currentImageUrl = $item['imageUrl'];
        
        $results[] = "\n=== Processing item: {$item['id']} (SKU: {$sku}) ===";
        
        // Extract category and number from SKU (WF-XX-###)
        if (preg_match('/^WF-([A-Z]{2})-(\d{3})$/', $sku, $matches)) {
            $skuCategory = $matches[1];
            $skuNumber = $matches[2];
            
            $results[] = "SKU breakdown: Category={$skuCategory}, Number={$skuNumber}";
            
            // Find old format images that might match this SKU
            $possibleOldFormats = [];
            
            // Look for direct category match (TS001A.png for WF-TS-001)
            $directPattern = $skuCategory . $skuNumber;
            
            // Also check reverse mapping for special cases (AW -> AR)
            $reverseMapping = [
                'AR' => ['AW', 'AR'], // Artwork could be AW or AR
                'GEN' => ['GN', 'MG'], // General could be GN or MG
                'TS' => ['TS'],
                'TU' => ['TU'],
                'SU' => ['SU'],
                'WW' => ['WW']
            ];
            
            $oldCategories = $reverseMapping[$skuCategory] ?? [$skuCategory];
            
            foreach ($oldCategories as $oldCat) {
                $basePattern = $oldCat . $skuNumber;
                
                // Look for images matching this pattern
                foreach ($existingImages as $imageName) {
                    if (preg_match('/^' . preg_quote($basePattern) . '([A-Z]?)\.(png|jpg|jpeg|webp|gif)$/i', $imageName, $imageMatches)) {
                        $possibleOldFormats[] = $imageName;
                    }
                }
            }
            
            if (empty($possibleOldFormats)) {
                $results[] = "No matching images found for SKU {$sku}";
                $skippedCount++;
                continue;
            }
            
            $results[] = "Found matching images: " . implode(', ', $possibleOldFormats);
            
            // Rename each found image
            foreach ($possibleOldFormats as $oldImageName) {
                $oldPath = $imageDir . $oldImageName;
                
                // Generate new name based on SKU
                $extension = pathinfo($oldImageName, PATHINFO_EXTENSION);
                $suffix = '';
                
                // Extract suffix (A, B, C, etc.) if present
                if (preg_match('/([A-Z])\./', $oldImageName, $suffixMatches)) {
                    $suffix = $suffixMatches[1];
                }
                
                $newImageName = $sku . $suffix . '.' . $extension;
                $newPath = $imageDir . $newImageName;
                
                // Check if new name already exists
                if (file_exists($newPath)) {
                    $results[] = "WARNING: Target file already exists: {$newImageName}";
                    $errorCount++;
                    continue;
                }
                
                // Rename the file
                if (rename($oldPath, $newPath)) {
                    $results[] = "✅ Renamed: {$oldImageName} → {$newImageName}";
                    $renamedCount++;
                    
                    // Update database references
                    $oldRelativePath = 'images/products/' . $oldImageName;
                    $newRelativePath = 'images/products/' . $newImageName;
                    
                    // Update inventory table
                    if ($currentImageUrl === $oldRelativePath) {
                        $updateStmt = $pdo->prepare("UPDATE inventory SET imageUrl = ? WHERE id = ?");
                        $updateStmt->execute([$newRelativePath, $item['id']]);
                        $results[] = "Updated inventory imageUrl reference";
                    }
                    
                    // Update product_images table if it exists
                    try {
                        $updateImagesStmt = $pdo->prepare("UPDATE product_images SET image_path = ? WHERE image_path = ?");
                        $updateImagesStmt->execute([$newRelativePath, $oldRelativePath]);
                        if ($updateImagesStmt->rowCount() > 0) {
                            $results[] = "Updated product_images table reference";
                        }
                    } catch (PDOException $e) {
                        $results[] = "Note: product_images table may not exist yet";
                    }
                    
                    // Update products table
                    try {
                        $updateProductsStmt = $pdo->prepare("UPDATE products SET image = ? WHERE image = ?");
                        $updateProductsStmt->execute([$newRelativePath, $oldRelativePath]);
                        if ($updateProductsStmt->rowCount() > 0) {
                            $results[] = "Updated products table reference";
                        }
                    } catch (PDOException $e) {
                        $results[] = "Note: Could not update products table: " . $e->getMessage();
                    }
                    
                } else {
                    $results[] = "❌ Failed to rename: {$oldImageName}";
                    $errorCount++;
                }
            }
            
        } else {
            $results[] = "SKU {$sku} doesn't follow WF-XX-### pattern, skipping";
            $skippedCount++;
        }
    }
    
    $results[] = "\n=== SUMMARY ===";
    $results[] = "Images renamed: {$renamedCount}";
    $results[] = "Errors: {$errorCount}";
    $results[] = "Skipped: {$skippedCount}";
    $results[] = "Image renaming process completed!";
    
    echo json_encode([
        'success' => true,
        'message' => 'Image renaming completed',
        'details' => $results,
        'stats' => [
            'renamed' => $renamedCount,
            'errors' => $errorCount,
            'skipped' => $skippedCount
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Image renaming failed: ' . $e->getMessage(),
        'details' => $results ?? []
    ], JSON_PRETTY_PRINT);
}
?> 