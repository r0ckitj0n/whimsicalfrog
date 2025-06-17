<?php
/**
 * Delete Item Image API
 * 
 * Deletes a specific item image and the associated file
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
    $imageId = $input['imageId'] ?? '';
    
    if (empty($imageId)) {
        echo json_encode(['success' => false, 'error' => 'Image ID is required']);
        exit;
    }
    
    // Get image details before deletion
    $stmt = $pdo->prepare("SELECT sku, image_path, is_primary FROM item_images WHERE id = ?");
    $stmt->execute([$imageId]);
    $imageData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$imageData) {
        echo json_encode(['success' => false, 'error' => 'Image not found']);
        exit;
    }
    
    $sku = $imageData['sku'];
    $imagePath = $imageData['image_path'];
    $wasPrimary = $imageData['is_primary'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM item_images WHERE id = ?");
    $stmt->execute([$imageId]);
    
    // Delete physical file
    $fullPath = __DIR__ . '/../' . $imagePath;
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
    
    // If this was the primary image, automatically promote the next image to primary
    if ($wasPrimary) {
        // Find the next available image for this SKU, ordered by sort_order
        $stmt = $pdo->prepare("SELECT id, image_path FROM item_images WHERE sku = ? ORDER BY sort_order ASC, id ASC LIMIT 1");
        $stmt->execute([$sku]);
        $newPrimary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($newPrimary) {
            // Promote the next image to primary
            $stmt = $pdo->prepare("UPDATE item_images SET is_primary = TRUE WHERE id = ?");
            $stmt->execute([$newPrimary['id']]);
            
            $promotedMessage = " The next image has been automatically promoted to primary.";
        } else {
            // No images left for this item
            $promotedMessage = " No other images available for this item.";
        }
    } else {
        $promotedMessage = "";
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Image deleted successfully.' . $promotedMessage,
        'was_primary' => $wasPrimary,
        'promoted_new_primary' => $wasPrimary && isset($newPrimary) && $newPrimary
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Database error in delete_item_image: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    error_log("Error in delete_item_image: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to delete image']);
}
?> 