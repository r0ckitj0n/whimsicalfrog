<?php
// Get detailed item information API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    if (!isset($_GET['sku'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'SKU parameter required']);
        exit;
    }

    $sku = $_GET['sku'];

    // Get item details with all fields
    $stmt = $pdo->prepare("
        SELECT 
            sku, name, description, category, retailPrice, stockLevel, reorderPoint,
            materials, dimensions, weight, care_instructions, technical_details,
            features, color_options, size_options, production_time, 
            customization_options, usage_tips, warranty_info
        FROM items 
        WHERE sku = ?
    ");
    $stmt->execute([$sku]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }

    // Get item images
    $stmt = $pdo->prepare("
        SELECT image_path, alt_text, is_primary, sort_order
        FROM item_images 
        WHERE sku = ? 
        ORDER BY is_primary DESC, sort_order ASC
    ");
    $stmt->execute([$sku]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the response
    $response = [
        'success' => true,
        'item' => $item,
        'images' => $images
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 