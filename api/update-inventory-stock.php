<?php
header('Content-Type: application/json');
$pdo = new PDO('mysql:host=localhost;dbname=whimsicalfrog', 'root', 'Palz2516');
$data = json_decode(file_get_contents('php://input'), true);
if (isset($data['inventoryId'], $data['stockLevel'])) {
    $stmt = $pdo->prepare('UPDATE inventory SET stockLevel = ? WHERE id = ?');
    $stmt->execute([$data['stockLevel'], $data['inventoryId']]);
    echo json_encode(['success' => true]);
    exit;
}
echo json_encode(['error' => 'Invalid request']); 