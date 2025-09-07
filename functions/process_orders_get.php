<?php

// Include the configuration file
require_once 'api/config.php';

// Set appropriate headers
header('Content-Type: application/json');

try {
    // Create database connection using config
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Check if orders table exists
    $rows = Database::queryAll("SHOW TABLES LIKE 'orders'");
    $tableExists = count($rows) > 0;

    if (!$tableExists) {
        // Orders table doesn't exist yet
        http_response_code(404);
        echo json_encode([
            'error' => 'Orders table does not exist',
            'message' => 'The orders table has not been created yet. Please create the table first.'
        ]);
        exit;
    }

    // Check if user_id filter is provided
    $userId = isset($_GET['user_id']) ? $_GET['user_id'] : null;
    $orderId = isset($_GET['order_id']) ? $_GET['order_id'] : null;

    // Prepare SQL query based on filters
    if ($orderId) {
        // Fetch specific order with details
        $rows = Database::queryAll('
            SELECT o.*, oi.*, i.name as item_name, i.image as item_image 
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.orderId
            LEFT JOIN items i ON oi.itemId = i.id
            WHERE o.id = ?
        ', [$orderId]);

        // Group order items by order
        $orderData = null;
        $items = [];

        foreach ($rows as $row) {
            if ($orderData === null) {
                // First row contains the order data
                $orderData = [
                    'order_id' => $row['id'],
                    'user_id' => $row['userId'],
                    'order_date' => $row['date'],
                    'status' => $row['status'],
                    'total_amount' => $row['total'],
                    'shipping_method' => $row['shippingMethod'] ?? null,
                    'payment_method' => $row['paymentMethod'] ?? null,
                    'payment_status' => $row['paymentStatus'] ?? null,
                    'items' => []
                ];
            }

            // Add item to order
            if (isset($row['itemId'])) {
                $orderData['items'][] = [
                    'item_id' => $row['itemId'],
                    'item_name' => $row['item_name'],
                    'quantity' => $row['quantity'],
                    'price' => $row['price'],
                    'item_image' => $row['item_image']
                ];
            }
        }

        // Return single order or 404
        if ($orderData) {
            echo json_encode($orderData);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
        }
    } elseif ($userId) {
        // Fetch orders for specific user
        $orders = Database::queryAll('SELECT * FROM orders WHERE userId = ? ORDER BY date DESC', [$userId]);

        echo json_encode($orders);
    } else {
        // Fetch all orders with pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;

        // Get total count
        $countRow = Database::queryOne('SELECT COUNT(*) AS c FROM orders');
        $totalOrders = $countRow ? (int)$countRow['c'] : 0;

        // Get orders for current page
        $orders = Database::queryAll("
            SELECT 
                o.id,
                o.orderId,
                o.customerName,
                o.customerEmail,
                o.customerPhone,
                o.customerAddress,
                o.paymentMethod,
                o.paymentStatus,
                o.orderStatus,
                o.shippingMethod,
                o.orderDate,
                o.totalAmount,
                o.discountCodeUsed,
                o.discountAmountApplied,
                o.taxAmount,
                o.notes,
                oi.id as orderItemId,
                oi.sku,
                oi.quantity,
                oi.price,
                i.name as itemName
            FROM orders o
            LEFT JOIN order_items oi ON o.orderId = oi.orderId
            LEFT JOIN items i ON oi.sku = i.sku
            ORDER BY o.orderDate DESC
            LIMIT ?
            OFFSET ?
        ", [$limit, $offset]);

        // Return orders with pagination info
        echo json_encode([
            'orders' => $orders,
            'pagination' => [
                'total' => $totalOrders,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($totalOrders / $limit)
            ]
        ]);
    }

} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    // Handle general errors
    http_response_code(500);
    echo json_encode([
        'error' => 'An unexpected error occurred',
        'details' => $e->getMessage()
    ]);
    exit;
}
