<?php
// Include the configuration file
require_once 'api/config.php';

// Set appropriate headers
header('Content-Type: application/json');

try {
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if orders table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    $tableExists = $stmt->rowCount() > 0;
    
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
        $stmt = $pdo->prepare('
            SELECT o.*, oi.*, p.name as product_name, p.image as product_image 
            FROM orders o
<<<<<<< HEAD
            LEFT JOIN order_items oi ON o.id = oi.orderId
            LEFT JOIN products p ON oi.productId = p.id
            WHERE o.id = ?
=======
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE o.order_id = ?
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
        ');
        $stmt->execute([$orderId]);
        
        // Group order items by order
        $orderData = null;
        $items = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($orderData === null) {
                // First row contains the order data
                $orderData = [
<<<<<<< HEAD
                    'order_id' => $row['id'],
                    'user_id' => $row['userId'],
                    'order_date' => $row['date'],
                    'status' => $row['status'],
                    'total_amount' => $row['total'],
                    'shipping_method' => $row['shippingMethod'] ?? null,
                    'payment_method' => $row['paymentMethod'] ?? null,
                    'payment_status' => $row['paymentStatus'] ?? null,
=======
                    'order_id' => $row['order_id'],
                    'user_id' => $row['user_id'],
                    'order_date' => $row['order_date'],
                    'status' => $row['status'],
                    'total_amount' => $row['total_amount'],
                    'shipping_address' => $row['shipping_address'] ?? null,
                    'billing_address' => $row['billing_address'] ?? null,
                    'payment_method' => $row['payment_method'] ?? null,
                    'payment_status' => $row['payment_status'] ?? null,
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
                    'items' => []
                ];
            }
            
            // Add item to order
<<<<<<< HEAD
            if (isset($row['productId'])) {
                $orderData['items'][] = [
                    'product_id' => $row['productId'],
=======
            if (isset($row['product_id'])) {
                $orderData['items'][] = [
                    'product_id' => $row['product_id'],
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
                    'product_name' => $row['product_name'],
                    'quantity' => $row['quantity'],
                    'price' => $row['price'],
                    'product_image' => $row['product_image']
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
    } else if ($userId) {
        // Fetch orders for specific user
<<<<<<< HEAD
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE userId = ? ORDER BY date DESC');
=======
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC');
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($orders);
    } else {
        // Fetch all orders with pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $countStmt = $pdo->query('SELECT COUNT(*) FROM orders');
        $totalOrders = $countStmt->fetchColumn();
        
        // Get orders for current page
<<<<<<< HEAD
        $stmt = $pdo->prepare('SELECT * FROM orders ORDER BY date DESC LIMIT ? OFFSET ?');
=======
        $stmt = $pdo->prepare('SELECT * FROM orders ORDER BY order_date DESC LIMIT ? OFFSET ?');
>>>>>>> e7613922959eba56cecb64c66c6f4812bff0f7d7
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
?>
