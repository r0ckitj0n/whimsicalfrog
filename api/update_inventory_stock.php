<?php

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

try {
    Database::getInstance();
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['inventoryId']) || !array_key_exists('stockLevel', $data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request: inventoryId and stockLevel required']);
        exit;
    }

    $sku = $data['inventoryId'];
    $stock = $data['stockLevel'];
    if (!is_numeric($stock) || $stock < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'stockLevel must be a non-negative number']);
        exit;
    }
    $stock = (int)$stock;

    // Ensure item exists
    $exists = Database::queryOne('SELECT sku FROM items WHERE sku = ?', [$sku]);
    if (!$exists) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit;
    }

    // Apply update
    $affected = Database::execute('UPDATE items SET stockLevel = ? WHERE sku = ?', [$stock, $sku]);
    if ($affected > 0) {
        Response::updated(['sku' => $sku, 'stockLevel' => $stock]);
    } else {
        Response::noChanges(['sku' => $sku, 'stockLevel' => $stock]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}
