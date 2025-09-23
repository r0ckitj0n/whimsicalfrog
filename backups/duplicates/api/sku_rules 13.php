<?php

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $rules = Database::queryAll("SELECT * FROM sku_rules ORDER BY category_name");
        echo json_encode(['success' => true, 'rules' => $rules]);
        break;

    case 'POST':
        $categoryName = $input['category_name'] ?? null;
        $skuPrefix = $input['sku_prefix'] ?? null;
        if (!$categoryName || !$skuPrefix) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Category name and SKU prefix are required.']);
            exit;
        }
        Database::execute("INSERT INTO sku_rules (category_name, sku_prefix) VALUES (?, ?)", [$categoryName, $skuPrefix]);
        echo json_encode(['success' => true, 'id' => Database::lastInsertId()]);
        break;

    case 'PUT':
        $id = $input['id'] ?? null;
        $categoryName = $input['category_name'] ?? null;
        $skuPrefix = $input['sku_prefix'] ?? null;
        if (!$id || !$categoryName || !$skuPrefix) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID, category name, and SKU prefix are required.']);
            exit;
        }
        Database::execute("UPDATE sku_rules SET category_name = ?, sku_prefix = ? WHERE id = ?", [$categoryName, $skuPrefix, $id]);
        echo json_encode(['success' => true]);
        break;

    case 'DELETE':
        $id = $input['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID is required.']);
            exit;
        }
        Database::execute("DELETE FROM sku_rules WHERE id = ?", [$id]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
        break;
}
