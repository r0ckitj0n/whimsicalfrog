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
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['imageId']) || !isset($input['sku'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
        exit;
    }

    $imageId = $input['imageId'];
    $sku = $input['sku'];

    Database::beginTransaction();

    // First, unset all primary images for this item
    Database::execute("UPDATE item_images SET is_primary = FALSE WHERE sku = ?", [$sku]);

    // Then set the selected image as primary
    $affected = Database::execute("UPDATE item_images SET is_primary = TRUE WHERE id = ? AND sku = ?", [$imageId, $sku]);

    if ($affected === 0) {
        Database::rollBack();
        echo json_encode(['success' => false, 'error' => 'Image not found or does not belong to this item']);
        exit;
    }

    // Get the updated image path for response
    $row = Database::queryOne("SELECT image_path FROM item_images WHERE id = ? AND sku = ?", [$imageId, $sku]);
    $imagePath = $row ? $row['image_path'] : null;

    Database::commit();

    echo json_encode([
        'success' => true,
        'message' => 'Primary image updated successfully',
        'imagePath' => $imagePath
    ]);

} catch (PDOException $e) {
    Database::rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?> 