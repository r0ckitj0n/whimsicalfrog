<?php
/**
 * Delete Item Image API
 *
 * Deletes a specific item image and the associated file
 */

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Invalid request method');
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        Response::error('Invalid JSON', null, 400);
    }
    $imageId = $input['imageId'] ?? '';

    if (empty($imageId)) {
        Response::error('Image ID is required', null, 400);
    }

    // Get image details before deletion
    $imageData = Database::queryOne("SELECT sku, image_path, is_primary FROM item_images WHERE id = ?", [$imageId]);

    if (!$imageData) {
        Response::notFound('Image not found');
    }

    $sku = $imageData['sku'];
    $imagePath = $imageData['image_path'];
    $wasPrimary = $imageData['is_primary'];

    // Start transaction
    Database::beginTransaction();

    // Delete from database
    Database::execute("DELETE FROM item_images WHERE id = ?", [$imageId]);

    // Delete physical file
    $fullPath = __DIR__ . '/../' . $imagePath;
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }

    // If this was the primary image, automatically promote the next image to primary
    if ($wasPrimary) {
        // Find the next available image for this SKU, ordered by sort_order
        $newPrimary = Database::queryOne("SELECT id, image_path FROM item_images WHERE sku = ? ORDER BY sort_order ASC, id ASC LIMIT 1", [$sku]);

        if ($newPrimary) {
            // Promote the next image to primary
            Database::execute("UPDATE item_images SET is_primary = TRUE WHERE id = ?", [$newPrimary['id']]);

            $promotedMessage = " The next image has been automatically promoted to primary.";
        } else {
            // No images left for this item
            $promotedMessage = " No other images available for this item.";
        }
    } else {
        $promotedMessage = "";
    }

    Database::commit();

    Response::success([
        'message' => 'Image deleted successfully.' . $promotedMessage,
        'was_primary' => $wasPrimary,
        'promoted_new_primary' => $wasPrimary && isset($newPrimary) && $newPrimary
    ]);

} catch (PDOException $e) {
    Database::rollBack();
    error_log("Database error in delete_item_image: " . $e->getMessage());
    Response::serverError('Database error occurred');
} catch (Exception $e) {
    Database::rollBack();
    error_log("Error in delete_item_image: " . $e->getMessage());
    Response::serverError('Failed to delete image');
}
