<?php
/**
 * Delete Product Image API
 * 
 * Deletes a specific product image and the associated file
 */

require_once '../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $imageId = $input['imageId'] ?? '';
    
    if (empty($imageId)) {
        echo json_encode(['success' => false, 'error' => 'Image ID is required']);
        exit;
    }
    
    // Get image details before deletion
    $stmt = $pdo->prepare("SELECT product_id, image_path, is_primary FROM product_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $imageData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$imageData) {
        echo json_encode(['success' => false, 'error' => 'Image not found']);
        exit;
    }
    
    $productId = $imageData['product_id'];
    $imagePath = $imageData['image_path'];
    $wasPrimary = $imageData['is_primary'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
    $stmt->execute([$imageId]);
    
    // Delete physical file
    $fullPath = __DIR__ . '/../' . $imagePath;
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
    
    // If this was the primary image, set another image as primary
    if ($wasPrimary) {
        $stmt = $pdo->prepare("SELECT id, image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC LIMIT 1");
        $stmt->execute([$productId]);
        $newPrimary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($newPrimary) {
            // Set new primary image
            $stmt = $pdo->prepare("UPDATE product_images SET is_primary = TRUE WHERE id = ?");
            $stmt->execute([$newPrimary['id']]);
            
            // Update inventory and products tables
            $stmt = $pdo->prepare("UPDATE inventory SET imageUrl = ? WHERE productId = ?");
            $stmt->execute([$newPrimary['image_path'], $productId]);
            
            $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE id = ?");
            $stmt->execute([$newPrimary['image_path'], $productId]);
        } else {
            // No images left, set to placeholder
            $placeholderPath = 'images/products/placeholder.png';
            
            $stmt = $pdo->prepare("UPDATE inventory SET imageUrl = ? WHERE productId = ?");
            $stmt->execute([$placeholderPath, $productId]);
            
            $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE id = ?");
            $stmt->execute([$placeholderPath, $productId]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Image deleted successfully'
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Database error in delete_product_image: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Error in delete_product_image: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to delete image']);
}
?> 