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
    echo json_encode(['success' => false,'message' => 'DB connect failed: '.$e->getMessage()]);
    exit;
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 50;

$params = [];
$where = '';
if ($q !== '') {
    $where = ' AND (i.sku LIKE ? OR i.name LIKE ?)';
    $like = '%'.$q.'%';
    $params[] = $like;
    $params[] = $like;
}

// Consider represented if there exists an active mapping matching SKU or item_id
$sql = "SELECT i.id AS item_id, i.sku, i.name, i.category
        FROM items i
        WHERE NOT EXISTS (
          SELECT 1 FROM area_mappings am
          WHERE am.is_active = 1 AND (
            (i.sku IS NOT NULL AND i.sku <> '' AND am.item_sku = i.sku)
            OR (am.item_id IS NOT NULL AND am.item_id = i.id)
          )
        )" . $where . "
        ORDER BY i.sku ASC
        LIMIT ".$limit;

try {
    $rows = Database::queryAll($sql, $params);
    echo json_encode(['success' => true,'items' => $rows,'count' => count($rows)]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false,'message' => 'Query failed: '.$e->getMessage()]);
}
