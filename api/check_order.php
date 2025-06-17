<?php
header('Content-Type: application/json');
require_once 'config.php';

$orderId = $_GET['order_id'] ?? '62F16P85';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check order
    $orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    // Check order items
    $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE orderId = ?");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'order_id' => $orderId,
        'order' => $order,
        'order_items' => $orderItems,
        'item_count' => count($orderItems)
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?> 