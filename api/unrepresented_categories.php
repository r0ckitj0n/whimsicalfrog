<?php

// List categories that are NOT represented by any active area mapping
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

$params = [];
$where = '';
if ($q !== '') {
    $where = ' AND c.name LIKE ?';
    $params[] = '%' . $q . '%';
}

// Consider represented if there exists an active mapping with mapping_type='category' and matching category_id
$sql = "SELECT c.id, c.name, c.description
        FROM categories c
        WHERE NOT EXISTS (
          SELECT 1 FROM area_mappings am
          WHERE am.is_active = 1 
          AND am.mapping_type = 'category' 
          AND (
            am.category_id = c.id 
            OR (am.content_target = CAST(c.id AS CHAR) COLLATE utf8mb4_unicode_ci AND am.mapping_type = 'category')
          )
        )" . $where . "
        ORDER BY c.name ASC
        LIMIT " . $limit;

try {
    $rows = Database::queryAll($sql, $params);
    echo json_encode(['success' => true, 'categories' => $rows, 'count' => count($rows)]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $e->getMessage()]);
}
