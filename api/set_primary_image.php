<?php
/**
 * Sets a specific image as the primary image for an item
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}
requireAdmin(true);

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
    if (!isset($input['imageId']) || !isset($input['sku'])) {
        Response::error('Missing required parameters', null, 400);
    }

    $imageId = $input['imageId'];
    $sku = trim((string)$input['sku']);
    if (!is_numeric($imageId) || (int)$imageId <= 0) {
        Response::error('Invalid imageId', null, 422);
    }
    if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku)) {
        Response::error('Invalid SKU format', null, 422);
    }
    $imageId = (int)$imageId;

    Database::beginTransaction();

    // First, unset all primary images for this item
    Database::execute("UPDATE item_images SET is_primary = FALSE WHERE sku = ?", [$sku]);

    // Then set the selected image as primary
    $affected = Database::execute("UPDATE item_images SET is_primary = TRUE WHERE id = ? AND sku = ?", [$imageId, $sku]);

    if ($affected === 0) {
        Database::rollBack();
        Response::notFound('Image not found or does not belong to this item');
    }

    // Get the updated image path for response
    $row = Database::queryOne("SELECT image_path FROM item_images WHERE id = ? AND sku = ?", [$imageId, $sku]);
    $imagePath = $row ? $row['image_path'] : null;

    Database::commit();

    Response::success([
        'message' => 'Primary image updated successfully',
        'imagePath' => $imagePath
    ]);

} catch (PDOException $e) {
    Database::rollBack();
    Response::serverError('Database error: ' . $e->getMessage());
} catch (Exception $e) {
    Response::serverError('Error: ' . $e->getMessage());
}
