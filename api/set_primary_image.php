<?php
/**
 * Sets a specific image as the primary image for an item
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['imageId']) || !isset($input['sku'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit;
    }
    
    $imageId = $input['imageId'];
    $sku = $input['sku'];
    
    $pdo->beginTransaction();
    
    // First, unset all primary images for this item
    $stmt = $pdo->prepare("UPDATE item_images SET is_primary = FALSE WHERE sku = ?");
    $stmt->execute([$sku]);
    
    // Then set the selected image as primary
    $stmt = $pdo->prepare("UPDATE item_images SET is_primary = TRUE WHERE id = ? AND sku = ?");
    $stmt->execute([$imageId, $sku]);
    
    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Image not found or does not belong to this item']);
        exit;
    }
    
    // Get the updated image path for response
    $stmt = $pdo->prepare("SELECT image_path FROM item_images WHERE id = ? AND sku = ?");
    $stmt->execute([$imageId, $sku]);
    $imagePath = $stmt->fetchColumn();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Primary image updated successfully',
        'imagePath' => $imagePath
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?> 