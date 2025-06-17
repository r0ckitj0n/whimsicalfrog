<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Get recent orders
    $ordersStmt = $pdo->query("SELECT id, userId, total, date FROM orders ORDER BY date DESC LIMIT 5");
    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results = ['orders' => $orders, 'order_details' => []];
    
    // For each order, get the order items
    foreach ($orders as $order) {
        $itemsStmt = $pdo->prepare("
            SELECT 
                oi.id,
                oi.orderId,
                oi.sku,
                oi.quantity,
                oi.price,
                i.name as itemName,
                i.sku as itemSku
            FROM order_items oi
            LEFT JOIN items i ON oi.sku = i.sku
            WHERE oi.orderId = ?
        ");
        $itemsStmt->execute([$order['id']]);
        $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results['order_details'][$order['id']] = $orderItems;
    }
    
    // Also check if there are any orphaned order_items
    $orphanedStmt = $pdo->query("
        SELECT oi.*, 'ORPHANED' as status
        FROM order_items oi 
        LEFT JOIN items i ON oi.sku = i.sku 
        WHERE i.sku IS NULL
        LIMIT 10
    ");
    $orphaned = $orphanedStmt->fetchAll(PDO::FETCH_ASSOC);
    $results['orphaned_items'] = $orphaned;
    
    // Check total counts
    $orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $orderItemCount = $pdo->query("SELECT COUNT(*) FROM order_items")->fetchColumn();
    $itemCount = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
    
    $results['counts'] = [
        'orders' => $orderCount,
        'order_items' => $orderItemCount,
        'items' => $itemCount
    ];
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?> 