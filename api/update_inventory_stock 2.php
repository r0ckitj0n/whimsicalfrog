<?php

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin(true);

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::methodNotAllowed('Method not allowed');
    }
    Database::getInstance();
    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        Response::error('Invalid JSON', null, 400);
    }

    $skuInput = $data['sku'] ?? $data['inventoryId'] ?? null;
    if (!isset($skuInput) || !array_key_exists('stock_quantity', $data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request: sku/inventoryId and stock_quantity required']);
        exit;
    }

    $sku = trim((string)$skuInput);
    if (preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku) !== 1) {
        Response::error('Invalid SKU format', null, 422);
    }
    $stock = $data['stock_quantity'];
    if (!is_numeric($stock) || $stock < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'stock_quantity must be a non-negative number']);
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
    $affected = Database::execute('UPDATE items SET stock_quantity = ? WHERE sku = ?', [$stock, $sku]);
    if ($affected > 0) {
        Response::updated(['sku' => $sku, 'stock_quantity' => $stock]);
    } else {
        Response::noChanges(['sku' => $sku, 'stock_quantity' => $stock]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'details' => $e->getMessage()]);
}
