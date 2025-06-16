<?php
/**
 * Final Image Fix Script
 * 
 * This script will:
 * 1. Rename physical image files from old format to SKU format
 * 2. Add the renamed images to the product_images database table
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $results = [];
    $results[] = "Starting final image fix...";
    
    // Get all inventory items with their SKUs
    $stmt = $pdo->query("SELECT id, sku FROM inventory WHERE sku IS NOT NULL AND sku != ''");
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
    $addedToDbCount = 0;
    $errorCount = 0;
    
    // Category mapping for reverse lookup
    $reverseMapping = [
        'AR' => ['AW', 'AR'], // Artwork could be AW or AR
        'GEN' => ['GN', 'MG'], // General could be GN or MG
        'TS' => ['TS'],
        'TU' => ['TU'],
        'SU' => ['SU'],
        'WW' => ['WW']
    ];
    
    foreach ($inventoryItems as $item) {
        $sku = $item['sku'];
        
        $results[] = "\n=== Processing SKU: {$sku} ===";
        
        // Extract category and number from SKU (WF-XX-###)
        if (preg_match('/^WF-([A-Z]{2,3})-(\d{3})$/', $sku, $matches)) {
            $skuCategory = $matches[1];
            $skuNumber = $matches[2];
            
            $results[] = "SKU breakdown: Category={$skuCategory}, Number={$skuNumber}";
            
            $oldCategories = $reverseMapping[$skuCategory] ?? [$skuCategory];
            
            foreach ($oldCategories as $oldCat) {
                $basePattern = $oldCat . $skuNumber;
                
                // Look for images matching this pattern
                foreach ($existingImages as $imageName) {
                    if (preg_match('/^' . preg_quote($basePattern) . '([A-Z]?)\.(png|jpg|jpeg|webp|gif)$/i', $imageName, $imageMatches)) {
                        $suffix = $imageMatches[1];
                        $extension = $imageMatches[2];
                        
                        $oldPath = $imageDir . $imageName;
                        $newImageName = $sku . $suffix . '.' . $extension;
                        $newPath = $imageDir . $newImageName;
                        
                        // Check if already renamed
                        if (file_exists($newPath)) {
                            $results[] = "✅ Already renamed: {$newImageName}";
                        } else {
                            // Rename the file
                            if (rename($oldPath, $newPath)) {
                                $results[] = "✅ Renamed: {$imageName} → {$newImageName}";
                                $renamedCount++;
                            } else {
                                $results[] = "❌ Failed to rename: {$imageName}";
                                $errorCount++;
                                continue;
                            }
                        }
                        
                        // Add to database if not already there
                        $relativePath = 'images/products/' . $newImageName;
                        $checkStmt = $pdo->prepare("SELECT id FROM product_images WHERE sku = ? AND image_path = ?");
                        $checkStmt->execute([$sku, $relativePath]);
                        
                        if (!$checkStmt->fetch()) {
                            // Determine if this should be primary (A suffix or first image)
                            $isPrimary = ($suffix === 'A' || $suffix === '');
                            
                            // If setting as primary, unset other primary images for this SKU
                            if ($isPrimary) {
                                $unsetPrimaryStmt = $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE sku = ?");
                                $unsetPrimaryStmt->execute([$sku]);
                            }
                            
                            // Get next sort order
                            $sortStmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 as next_sort FROM product_images WHERE sku = ?");
                            $sortStmt->execute([$sku]);
                            $nextSort = $sortStmt->fetchColumn();
                            
                            // Insert new image record
                            $insertStmt = $pdo->prepare("
                                INSERT INTO product_images (sku, image_path, is_primary, sort_order, alt_text, created_at, updated_at) 
                                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                            ");
                            
                            $altText = $imageName; // Use original filename as alt text
                            
                            if ($insertStmt->execute([$sku, $relativePath, $isPrimary ? 1 : 0, $nextSort, $altText])) {
                                $results[] = "✅ Added to database: {$relativePath}" . ($isPrimary ? " (PRIMARY)" : "");
                                $addedToDbCount++;
                            } else {
                                $results[] = "❌ Failed to add to database: {$relativePath}";
                                $errorCount++;
                            }
                        } else {
                            $results[] = "✅ Already in database: {$relativePath}";
                        }
                    }
                }
            }
        } else {
            $results[] = "SKU {$sku} doesn't follow expected pattern, skipping";
        }
    }
    
    $results[] = "\n=== SUMMARY ===";
    $results[] = "Files renamed: {$renamedCount}";
    $results[] = "Database entries added: {$addedToDbCount}";
    $results[] = "Errors: {$errorCount}";
    $results[] = "Final image fix completed!";
    
    echo json_encode([
        'success' => true,
        'message' => 'Final image fix completed',
        'details' => $results,
        'stats' => [
            'renamed' => $renamedCount,
            'added_to_db' => $addedToDbCount,
            'errors' => $errorCount
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Final image fix failed: ' . $e->getMessage(),
        'details' => $results ?? []
    ], JSON_PRETTY_PRINT);
}
?> 