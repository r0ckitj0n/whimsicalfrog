<?php

// functions/process_order_update.php
// AJAX endpoint to update an order. Expects POST and returns JSON.

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    require_once dirname(__DIR__) . '/api/config.php';
    // Optional: admin auth guard if available
    try {
        require_once dirname(__DIR__) . '/includes/auth.php';
    } catch (\Throwable $e) {
    }

    Database::getInstance();

    $orderId = $_POST['orderId'] ?? $_POST['id'] ?? '';
    if (!$orderId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing orderId']);
        exit;
    }

    $status = $_POST['status'] ?? $_POST['order_status'] ?? null;
    $totalAmount = $_POST['total_amount'] ?? $_POST['total'] ?? null;

    $updates = [];
    $params = [];

    if ($status !== null && $status !== '') {
        $updates[] = 'status = ?';
        $params[] = $status;
    }
    if ($totalAmount !== null && $totalAmount !== '') {
        $updates[] = 'total = ?';
        $params[] = (float)$totalAmount;
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'id' => $orderId, 'message' => 'No changes to update']);
        exit;
    }

    $params[] = $orderId;
    $sql = 'UPDATE orders SET ' . implode(', ', $updates) . ' WHERE id = ?';
    $affected = Database::execute($sql, $params);

    echo json_encode([
        'success' => $affected >= 0,
        'id' => $orderId,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
