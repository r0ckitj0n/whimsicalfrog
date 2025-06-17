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
    
    $sku = $_POST['sku'] ?? '';
    $isPrimary = isset($_POST['isPrimary']) && $_POST['isPrimary'] === 'true';
    $altText = $_POST['altText'] ?? '';
    $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';
    
    if (empty($sku)) {
        echo json_encode(['success' => false, 'error' => 'SKU is required']);
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
        $stmt = $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE sku = ?");
        $stmt->execute([$sku]);
    }
    
    // Get existing image paths to determine what letter suffixes are already used
    $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE sku = ?");
    $stmt->execute([$sku]);
    $existingPaths = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Extract used letter suffixes
    $usedSuffixes = [];
    foreach ($existingPaths as $path) {
        if (preg_match('/\/' . preg_quote($sku) . '([A-Z])\./', $path, $matches)) {
            $usedSuffixes[] = $matches[1];
        }
    }
    
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
        
        // Validate file size (max 10MB)
        if ($fileSize > 10 * 1024 * 1024) {
            $errors[] = "File too large: $originalName (max 10MB allowed)";
            continue;
        }
        
        // Find next available letter suffix
        $suffix = null;
        for ($letterIndex = 0; $letterIndex < 26; $letterIndex++) {
            $testSuffix = chr(65 + $letterIndex); // 65 is ASCII for 'A'
            if (!in_array($testSuffix, $usedSuffixes)) {
                $suffix = $testSuffix;
                $usedSuffixes[] = $suffix; // Mark this suffix as used for subsequent files in this batch
                break;
            }
        }
        
        if ($suffix === null) {
            $errors[] = "Too many images for product $sku (max 26)";
            continue;
        }
        
        $filename = $sku . $suffix . '.' . $ext;
        
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
            $isThisPrimary = ($isPrimary && $i === 0) ? 1 : 0; // Only first image can be primary if multiple uploaded
            
            // Get sort order (use letter index for consistent ordering)
            $sortOrder = ord($suffix) - 65; // Convert A=0, B=1, C=2, etc.
            
            // Insert into database
            $stmt = $pdo->prepare("
                INSERT INTO product_images (sku, image_path, is_primary, alt_text, sort_order) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $sku,
                $relPath,
                $isThisPrimary,
                $altText ?: $originalName,
                $sortOrder
            ]);
            
            $uploadedImages[] = [
                'filename' => $filename,
                'path' => $relPath,
                'isPrimary' => $isThisPrimary == 1,
                'sortOrder' => $sortOrder
            ];
            
            // Update items table with primary image
            if ($isThisPrimary) {
                $stmt = $pdo->prepare("UPDATE items SET imageUrl = ? WHERE sku = ?");
                $stmt->execute([$relPath, $sku]);
            }
            
        } else {
            $errors[] = "Failed to save file: $originalName";
        }
    }
    
    // If no primary image exists for this product, make the first uploaded image primary
    if (!empty($uploadedImages)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_images WHERE sku = ? AND is_primary = 1");
        $stmt->execute([$sku]);
        $hasPrimary = $stmt->fetchColumn() > 0;
        
        if (!$hasPrimary && !empty($uploadedImages)) {
            $firstImage = $uploadedImages[0];
            $stmt = $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE sku = ? AND image_path = ?");
            $stmt->execute([$sku, $firstImage['path']]);
            
            // Update items table
            $stmt = $pdo->prepare("UPDATE items SET imageUrl = ? WHERE sku = ?");
            $stmt->execute([$firstImage['path'], $sku]);
            
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
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error in multi-image upload: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()]);
}
?> 