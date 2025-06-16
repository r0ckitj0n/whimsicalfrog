<?php
/**
 * Rename Product Images Script
 * 
 * This script renames existing product images from the old Product ID format (P001A.png)
 * to the new SKU format (WF-TS-001A.png) based on the inventory data.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $results = [];
    $results[] = "Starting image renaming process...";
    
    // Get all inventory items with their old productId and new SKU
    $stmt = $pdo->query("SELECT id, sku, imageUrl FROM inventory WHERE sku IS NOT NULL AND sku != ''");
    $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results[] = "Found " . count($inventoryItems) . " inventory items to process";
    
    $renamedCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    
    foreach ($inventoryItems as $item) {
        $sku = $item['sku'];
        $currentImageUrl = $item['imageUrl'];
        
        $results[] = "\n=== Processing item: {$item['id']} (SKU: {$sku}) ===";
        
        // Extract potential Product ID from SKU (if it follows WF-XX-### pattern)
        if (preg_match('/^WF-[A-Z]{2}-(\d{3})$/', $sku, $matches)) {
            $productIdNumber = $matches[1];
            $oldProductId = 'P' . $productIdNumber;
            
            $results[] = "Mapped SKU {$sku} to old Product ID {$oldProductId}";
            
            // Look for images with the old Product ID pattern
            $imageDir = __DIR__ . '/../images/products/';
            $imagePatterns = [
                $oldProductId . 'A.webp',
                $oldProductId . 'A.png', 
                $oldProductId . 'A.jpg',
                $oldProductId . 'A.jpeg',
                $oldProductId . '.webp',
                $oldProductId . '.png',
                $oldProductId . '.jpg',
                $oldProductId . '.jpeg'
            ];
            
            $foundImages = [];
            foreach ($imagePatterns as $pattern) {
                $oldPath = $imageDir . $pattern;
                if (file_exists($oldPath)) {
                    $foundImages[] = $pattern;
                }
            }
            
            if (empty($foundImages)) {
                $results[] = "No images found for Product ID {$oldProductId}";
                $skippedCount++;
                continue;
            }
            
            $results[] = "Found images: " . implode(', ', $foundImages);
            
            // Rename each found image
            foreach ($foundImages as $oldImageName) {
                $oldPath = $imageDir . $oldImageName;
                
                // Generate new name based on SKU
                $extension = pathinfo($oldImageName, PATHINFO_EXTENSION);
                $suffix = '';
                
                // Extract suffix (A, B, C, etc.) if present
                if (preg_match('/^' . preg_quote($oldProductId) . '([A-Z]?)\./', $oldImageName, $suffixMatches)) {
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