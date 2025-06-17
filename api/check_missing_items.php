<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $stmt = $pdo->prepare("SELECT sku, name, stockLevel FROM inventory WHERE sku IN (?, ?)");
    $stmt->execute(['WF-SU-003', 'WF-TU-003']);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'items' => $items]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?> 