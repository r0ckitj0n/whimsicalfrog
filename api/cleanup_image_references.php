<?php
/**
 * Cleanup Image References Script
 * 
 * This script cleans up orphaned database references to old image names
 * and updates them to match the renamed files.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $results = [];
    $results[] = "Starting image reference cleanup...";
    
    // Get all product_images entries
    $stmt = $pdo->query("SELECT * FROM product_images ORDER BY sku, sort_order");
    $imageEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results[] = "Found " . count($imageEntries) . " image entries in database";
    
    $updatedCount = 0;
    $deletedCount = 0;
    $errorCount = 0;
    
    $imageDir = __DIR__ . '/../images/products/';
    
    foreach ($imageEntries as $entry) {
        $id = $entry['id'];
        $sku = $entry['sku'];
        $currentPath = $entry['image_path'];
        
        $results[] = "\n=== Processing entry ID {$id}: {$currentPath} ===";
        
        // Check if current path exists
        $fullCurrentPath = __DIR__ . '/../' . $currentPath;
        if (file_exists($fullCurrentPath)) {
            $results[] = "✅ File exists: {$currentPath}";
            continue;
        }
        
        $results[] = "❌ File missing: {$currentPath}";
        
        // Try to find the renamed version
        $fileName = basename($currentPath);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        
        // Check if this is an old format name that should have been renamed
        if (preg_match('/^([A-Z]{2})(\d{3})([A-Z]?)\.(png|jpg|jpeg|webp|gif)$/i', $fileName, $matches)) {
            $oldCategory = $matches[1];
            $number = $matches[2];
            $suffix = $matches[3];
            $ext = $matches[4];
            
            $results[] = "Old format detected: Category={$oldCategory}, Number={$number}, Suffix={$suffix}";
            
            // Try to find the corresponding SKU-named file
            $possibleNewNames = [
                $sku . $suffix . '.' . $ext,
                $sku . $suffix . '.webp',
                $sku . $suffix . '.png',
                $sku . $suffix . '.jpg',
                $sku . $suffix . '.jpeg'
            ];
            
            $foundNewFile = null;
            foreach ($possibleNewNames as $newName) {
                $newPath = $imageDir . $newName;
                if (file_exists($newPath)) {
                    $foundNewFile = 'images/products/' . $newName;
                    break;
                }
            }
            
            if ($foundNewFile) {
                $results[] = "✅ Found renamed file: {$foundNewFile}";
                
                // Update database reference
                $updateStmt = $pdo->prepare("UPDATE product_images SET image_path = ?, updated_at = NOW() WHERE id = ?");
                if ($updateStmt->execute([$foundNewFile, $id])) {
                    $results[] = "✅ Updated database reference";
                    $updatedCount++;
                } else {
                    $results[] = "❌ Failed to update database reference";
                    $errorCount++;
                }
            } else {
                $results[] = "❌ No renamed file found, deleting orphaned reference";
                
                // Delete orphaned reference
                $deleteStmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
                if ($deleteStmt->execute([$id])) {
                    $results[] = "✅ Deleted orphaned reference";
                    $deletedCount++;
                } else {
                    $results[] = "❌ Failed to delete orphaned reference";
                    $errorCount++;
                }
            }
        } else {
            $results[] = "Not an old format name, deleting orphaned reference";
            
            // Delete orphaned reference
            $deleteStmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
            if ($deleteStmt->execute([$id])) {
                $results[] = "✅ Deleted orphaned reference";
                $deletedCount++;
            } else {
                $results[] = "❌ Failed to delete orphaned reference";
                $errorCount++;
            }
        }
    }
    
    $results[] = "\n=== SUMMARY ===";
    $results[] = "References updated: {$updatedCount}";
    $results[] = "Orphaned references deleted: {$deletedCount}";
    $results[] = "Errors: {$errorCount}";
    $results[] = "Image reference cleanup completed!";
    
    echo json_encode([
        'success' => true,
        'message' => 'Image reference cleanup completed',
        'details' => $results,
        'stats' => [
            'updated' => $updatedCount,
            'deleted' => $deletedCount,
            'errors' => $errorCount
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Image reference cleanup failed: ' . $e->getMessage(),
        'details' => $results ?? []
    ], JSON_PRETTY_PRINT);
}
?> 