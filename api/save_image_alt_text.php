<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed('Invalid request method. Only POST is allowed.');
}

// Get and validate input data
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    Response::error('Invalid JSON input.', null, 400);
}

$sku = trim($input['sku'] ?? '');
$imageAnalysisData = $input['imageAnalysis'] ?? [];

if (empty($sku)) {
    Response::error('SKU is required.', null, 400);
}

if (empty($imageAnalysisData) || !is_array($imageAnalysisData)) {
    Response::error('Image analysis data is required.', null, 400);
}

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    $updatedImages = 0;
    $errors = [];

    foreach ($imageAnalysisData as $imageData) {
        $imagePath = $imageData['image_path'] ?? '';
        $altText = $imageData['alt_text'] ?? '';
        $aiDescription = $imageData['description'] ?? '';

        if (empty($imagePath)) {
            $errors[] = "Missing image path in analysis data";
            continue;
        }

        // Update the item_images table with alt text and AI description
        $affected = Database::execute("\n            UPDATE item_images \n            SET alt_text = ?, ai_description = ?, updated_at = CURRENT_TIMESTAMP \n            WHERE sku = ? AND image_path = ?\n        ", [$altText, $aiDescription, $sku, $imagePath]);

        if ($affected > 0) {
            $updatedImages++;
        } else {
            $errors[] = "Failed to update image: " . $imagePath;
        }
    }

    if ($updatedImages > 0) {
        Response::updated(['message' => "Updated alt text for {$updatedImages} image(s)", 'updated_count' => $updatedImages, 'errors' => $errors]);
    } else {
        Response::noChanges(['message' => 'No images were updated', 'errors' => $errors]);
    }

} catch (PDOException $e) {
    error_log("Error saving image alt text: " . $e->getMessage());
    Response::serverError('Database error occurred.');
}
?> 