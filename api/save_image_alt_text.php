<?php
require_once __DIR__ . '/config.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method. Only POST is allowed.']);
    exit;
}

// Get and validate input data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input.']);
    exit;
}

$sku = trim($input['sku'] ?? '');
$imageAnalysisData = $input['imageAnalysis'] ?? [];

if (empty($sku)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'SKU is required.']);
    exit;
}

if (empty($imageAnalysisData) || !is_array($imageAnalysisData)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Image analysis data is required.']);
    exit;
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
        $stmt = $pdo->prepare("
            UPDATE item_images 
            SET alt_text = ?, ai_description = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE sku = ? AND image_path = ?
        ");

        $result = $stmt->execute([$altText, $aiDescription, $sku, $imagePath]);

        if ($result && $stmt->rowCount() > 0) {
            $updatedImages++;
        } else {
            $errors[] = "Failed to update image: " . $imagePath;
        }
    }

    if ($updatedImages > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Updated alt text for {$updatedImages} image(s)",
            'updated_count' => $updatedImages,
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No images were updated',
            'errors' => $errors
        ]);
    }

} catch (PDOException $e) {
    error_log("Error saving image alt text: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred.']);
}
?> 