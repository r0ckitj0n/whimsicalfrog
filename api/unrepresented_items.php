<?php

// List items that are NOT represented by any active area mapping
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';
try {
    Database::getInstance();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB connect failed: ' . $e->getMessage()]);
    exit;
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = isset($_GET['limit']) ? max(1, min(500, (int) $_GET['limit'])) : 50;

// Check if items table has 'id' column
$hasId = false;
try {
    $check = Database::queryOne("SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'items' AND COLUMN_NAME = 'id'");
    $hasId = ($check && $check['c'] > 0);
} catch (Exception $e) {
}

$selectId = $hasId ? 'i.id' : 'NULL AS id';

$params = [];
$where = '';
if ($q !== '') {
    $where = ' AND (i.sku LIKE ? OR i.name LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}

// Consider represented if there exists any mapping matching this SKU
// Use a robust join condition that handles potential collation issues
$sql = "SELECT $selectId, i.sku, i.name, i.category, COALESCE(img.image_path, i.image_url) AS image_url
        FROM items i
        LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1
        WHERE NOT EXISTS (
          SELECT 1 FROM area_mappings am
          WHERE am.is_active = 1 
          AND am.mapping_type = 'item'
          AND am.item_sku IS NOT NULL 
          AND am.item_sku <> ''
          AND am.item_sku = i.sku
        )" . $where . "
        ORDER BY i.sku ASC
        LIMIT " . $limit;

try {
    $rows = Database::queryAll($sql, $params);
    foreach ($rows as &$row) {
        if ($row['image_url']) {
            $p = ltrim($row['image_url'], '/');
            $row['image_url'] = (strpos($p, 'images/items/') === 0) ? '/' . $p : '/images/items/' . $p;
        }
    }
    echo json_encode(['success' => true, 'items' => $rows, 'count' => count($rows)]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $e->getMessage()]);
}
