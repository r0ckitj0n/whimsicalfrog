<?php
/**
 * Set Primary Image API
 * 
 * Sets a specific image as the primary image for a product
 */

require_once __DIR__ . '/../api/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sku = $input['sku'] ?? '';
    $imageId = $input['imageId'] ?? '';
    
    if (empty($sku) || empty($imageId)) {
        echo json_encode(['success' => false, 'error' => 'SKU and Image ID are required']);
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // First, unset all primary images for this product
    $stmt = $pdo->prepare("UPDATE product_images SET is_primary = FALSE WHERE sku = ?");
    $stmt->execute([$sku]);
    
    // Set the specified image as primary
    $stmt = $pdo->prepare("UPDATE product_images SET is_primary = TRUE WHERE id = ? AND sku = ?");
    $stmt->execute([$imageId, $sku]);
    
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Image not found or does not belong to this product']);
        exit;
    }
    
    // Get the new primary image path
    $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ? AND sku = ?");
    $stmt->execute([$imageId, $sku]);
    $primaryImagePath = $stmt->fetchColumn();
    
    if ($primaryImagePath) {
        // Update inventory table
        $stmt = $pdo->prepare("UPDATE inventory SET imageUrl = ? WHERE sku = ?");
        $stmt->execute([$primaryImagePath, $sku]);
        
        // Update products table
        $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE sku = ?");
        $stmt->execute([$primaryImagePath, $sku]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Primary image updated successfully',
        'primaryImagePath' => $primaryImagePath
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Database error in set_primary_image: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Error in set_primary_image: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to set primary image']);
}
?> 