<?php
/**
 * Multi-Image Upload Processor
 * 
 * Handles multiple image uploads per product with:
 * - Images named after product ID (P001A.jpg, P001B.jpg, P001C.jpg, etc.)
 * - Primary image designation
 * - Overwrite existing images option
 * - Support for multiple formats
 */

require_once __DIR__ . '/api/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $productId = $_POST['productId'] ?? '';
    $isPrimary = isset($_POST['isPrimary']) && $_POST['isPrimary'] === 'true';
    $altText = $_POST['altText'] ?? '';
    $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';
    
    if (empty($productId)) {
        echo json_encode(['success' => false, 'error' => 'Product ID is required']);
        exit;
    }
    
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        echo json_encode(['success' => false, 'error' => 'No images uploaded']);
        exit;
    }
    
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
    $uploadedImages = [];
    $errors = [];
    
    // Ensure products directory exists
    $productsDir = __DIR__ . '/images/products/';
    if (!is_dir($productsDir)) {
        mkdir($productsDir, 0755, true);
    }
    
    // If this is marked as primary, unset any existing primary images for this product
    if ($isPrimary) {
        $stmt = $pdo->prepare("UPDATE product_images SET is_primary = FALSE WHERE product_id = ?");
        $stmt->execute([$productId]);
    }
    
    // Get current image count for this product to determine naming
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM product_images WHERE product_id = ?");
    $stmt->execute([$productId]);
    $currentCount = $stmt->fetchColumn();
    
    // Process each uploaded file
    $fileCount = count($_FILES['images']['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error for file " . ($i + 1);
            continue;
        }
        
        $originalName = $_FILES['images']['name'][$i];
        $tmpPath = $_FILES['images']['tmp_name'][$i];
        $fileSize = $_FILES['images']['size'][$i];
        
        // Validate file extension
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            $errors[] = "Unsupported file type: $originalName";
            continue;
        }
        
        // Validate file size (max 5MB)
        if ($fileSize > 5 * 1024 * 1024) {
            $errors[] = "File too large: $originalName (max 5MB)";
            continue;
        }
        
        // Generate filename based on product ID with letter suffix
        // All images get a letter suffix (A, B, C, etc.)
        $letterIndex = $currentCount + $i;
        $suffix = chr(65 + $letterIndex); // 65 is ASCII for 'A'
        $filename = $productId . $suffix . '.' . $ext;
        
        $relPath = 'images/products/' . $filename;
        $absPath = $productsDir . $filename;
        
        // If overwriting, remove existing file
        if ($overwrite && file_exists($absPath)) {
            unlink($absPath);
        }
        
        // Move uploaded file
        if (move_uploaded_file($tmpPath, $absPath)) {
            chmod($absPath, 0644);
            
            // Determine if this should be primary
            $isThisPrimary = $isPrimary && $i === 0; // Only first image can be primary if multiple uploaded
            
            // Get sort order
            $sortOrder = $currentCount + $i;
            
            // Insert into database
            $stmt = $pdo->prepare("
                INSERT INTO product_images (product_id, image_path, is_primary, alt_text, sort_order) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                image_path = VALUES(image_path),
                is_primary = VALUES(is_primary),
                alt_text = VALUES(alt_text),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                $productId,
                $relPath,
                $isThisPrimary,
                $altText ?: $originalName,
                $sortOrder
            ]);
            
            $uploadedImages[] = [
                'filename' => $filename,
                'path' => $relPath,
                'isPrimary' => $isThisPrimary,
                'sortOrder' => $sortOrder
            ];
            
            // Update inventory table with primary image
            if ($isThisPrimary) {
                $stmt = $pdo->prepare("UPDATE inventory SET imageUrl = ? WHERE productId = ?");
                $stmt->execute([$relPath, $productId]);
                
                // Update products table with primary image
                $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE id = ?");
                $stmt->execute([$relPath, $productId]);
            }
            
        } else {
            $errors[] = "Failed to save file: $originalName";
        }
    }
    
    // If no primary image exists for this product, make the first uploaded image primary
    if (!empty($uploadedImages)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_primary = TRUE");
        $stmt->execute([$productId]);
        $hasPrimary = $stmt->fetchColumn() > 0;
        
        if (!$hasPrimary && !empty($uploadedImages)) {
            $firstImage = $uploadedImages[0];
            $stmt = $pdo->prepare("UPDATE product_images SET is_primary = TRUE WHERE product_id = ? AND image_path = ?");
            $stmt->execute([$productId, $firstImage['path']]);
            
            // Update inventory and products tables
            $stmt = $pdo->prepare("UPDATE inventory SET imageUrl = ? WHERE productId = ?");
            $stmt->execute([$firstImage['path'], $productId]);
            
            $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE id = ?");
            $stmt->execute([$firstImage['path'], $productId]);
            
            $uploadedImages[0]['isPrimary'] = true;
        }
    }
    
    $response = [
        'success' => true,
        'message' => count($uploadedImages) . ' image(s) uploaded successfully',
        'uploadedImages' => $uploadedImages
    ];
    
    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Database error in multi-image upload: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Error in multi-image upload: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()]);
}
?> 