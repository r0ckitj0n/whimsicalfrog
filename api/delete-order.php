<?php
header('Content-Type: application/json');
require_once 'config.php'; // For DB credentials

// Start session if not already started (config.php might do this, but ensure it is)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Ensure user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Admin privileges required.']);
    exit;
}

// Check if the request method is DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method. Only DELETE is allowed.']);
    exit;
}

// Get orderId from query parameter
$orderId = $_GET['orderId'] ?? null;

if (empty($orderId)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Order ID is required.']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // 1. Check if the order exists
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE id = ?");
    $stmtCheck->execute([$orderId]);
    $orderExists = $stmtCheck->fetchColumn();

    if ($orderExists == 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    // 2. Delete the order
    // If you have related tables (e.g., order_items) that should be deleted when an order is deleted,
    // you would add those DELETE statements here, ideally within a transaction.
    // For example:
    // $pdo->beginTransaction();
    // $stmtDeleteItems = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
    // $stmtDeleteItems->execute([$orderId]);
    
    $stmtDeleteOrder = $pdo->prepare("DELETE FROM orders WHERE id = ?");
    
    if ($stmtDeleteOrder->execute([$orderId])) {
        if ($stmtDeleteOrder->rowCount() > 0) {
            // If using a transaction: $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Order deleted successfully.']);
        } else {
            // If using a transaction: $pdo->rollBack();
            // This case means the order existed (from check above) but wasn't deleted (e.g., deleted by another process between check and delete).
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'Order found but could not be deleted. It might have been deleted by another process or an issue occurred.']);
        }
    } else {
        // If using a transaction: $pdo->rollBack();
        error_log("Failed to delete order $orderId: " . implode(", ", $stmtDeleteOrder->errorInfo()));
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to delete order due to a database error.']);
    }

} catch (PDOException $e) {
    // If using a transaction and an exception occurs before commit: if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Database error in delete-order.php for order ID $orderId: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
