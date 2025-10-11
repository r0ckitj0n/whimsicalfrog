<?php
// Get detailed item information API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config.php';

// Prevent notices/warnings from corrupting JSON; buffer early output
ini_set('display_errors', 0);
ob_start();

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
    $item = Database::queryOne("
        SELECT 
            sku, name, description, category, retailPrice, stockLevel, reorderPoint,
            materials, dimensions, weight, care_instructions, technical_details,
            features, color_options, size_options, production_time, 
            customization_options, usage_tips, warranty_info
        FROM items 
        WHERE sku = ?
    ", [$sku]);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }

    // Compute aggregated total stock from item_sizes (active rows only) and expose as totalStock
    $stockRow = Database::queryOne(
        "SELECT COALESCE(SUM(stock_level), 0) AS totalStock FROM item_sizes WHERE item_sku = ? AND is_active = 1",
        [$sku]
    );
    $item['totalStock'] = isset($stockRow['totalStock']) ? (int)$stockRow['totalStock'] : 0;

    // Get item images
    $images = Database::queryAll("
        SELECT image_path, alt_text, is_primary, sort_order
        FROM item_images 
        WHERE sku = ? 
        ORDER BY is_primary DESC, sort_order ASC
    ", [$sku]);

    // Format the response
    $response = [
        'success' => true,
        'item' => $item,
        'images' => $images
    ];

    if (ob_get_length() !== false) {
        ob_end_clean();
    }
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