<?php

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
$pdo = Database::getInstance();
$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['inventoryId'], $data['stockLevel'])) {
    $stmt = $pdo->prepare('UPDATE items SET stockLevel = ? WHERE sku = ?');
    $stmt->execute([$data['stockLevel'], $data['inventoryId']]);
    echo json_encode(['success' => true]);
    exit;
}
echo json_encode(['error' => 'Invalid request']);
