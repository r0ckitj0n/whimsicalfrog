<?php
// Get detailed item information API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';

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

    $sku = trim((string)$_GET['sku']);
    if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid SKU format']);
        exit;
    }

    // Get item details with necessary fields - include saved AI suggestions
    $item = Database::queryOne("
        SELECT 
            i.sku, i.name, i.description, COALESCE(c.name, i.category) AS category, 
            i.status, i.cost_price, i.retail_price, i.is_archived,
            i.image_url, i.stock_quantity, i.reorder_point, 
            i.weight_oz, i.package_length_in, i.package_width_in, i.package_height_in,
            i.locked_fields,
            i.locked_words,
            i.quality_tier,
            i.cost_quality_tier,
            i.price_quality_tier,
            cs.suggested_cost, cs.reasoning as cost_reasoning, cs.confidence as cost_confidence, cs.breakdown as cost_breakdown,
            ps.suggested_price, ps.reasoning as price_reasoning, ps.confidence as price_confidence, ps.factors as price_factors, ps.components as price_components
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN cost_suggestions cs ON i.sku = cs.sku
        LEFT JOIN price_suggestions ps ON i.sku = ps.sku
        WHERE i.sku = ?
    ", [$sku]);

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }

    // Master stock mode: use the item's stock_quantity regardless of variant rows.
    $item['total_stock'] = (int) ($item['stock_quantity'] ?? 0);

    // Decode AI JSON fields if they exist
    $aiJsonFields = ['cost_breakdown', 'price_factors', 'price_components', 'locked_fields', 'locked_words'];
    foreach ($aiJsonFields as $f) {
        if (!empty($item[$f])) {
            $item[$f] = json_decode($item[$f], true);
        }
    }

    // Get item images
    $rawImages = Database::queryAll("
        SELECT image_path, alt_text, is_primary, sort_order
        FROM item_images 
        WHERE sku = ? 
        ORDER BY is_primary DESC, sort_order ASC, id ASC
    ", [$sku]);

    // Deduplicate images for frontend display
    // We want to show the best version (WebP > PNG/JPG) but the query order
    // (Primary first, then low sort_order) typically puts the preferred WebP first.
    // So we just take the first occurrence of each filename stem.
    $images = [];
    $seenStems = [];

    foreach ($rawImages as $img) {
        $path = $img['image_path'];
        $filename = basename($path);
        // Get filename without extension (e.g. 'WF-Item-A' from 'WF-Item-A.webp')
        $stem = pathinfo($filename, PATHINFO_FILENAME);

        if (!in_array($stem, $seenStems)) {
            $seenStems[] = $stem;
            $images[] = $img;
        }
    }

    // Add random cart button text for the modal
    $item['button_text'] = function_exists('getRandomCartButtonText') ? getRandomCartButtonText() : 'Add to Cart';

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
    if (ob_get_length() !== false)
        ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (ob_get_length() !== false)
        ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>
