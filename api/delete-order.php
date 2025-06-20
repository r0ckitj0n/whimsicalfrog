<?php
header('Content-Type: application/json');
require_once 'config.php'; // For DB credentials

// Start session if not already started (config.php might do this, but ensure it is)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Security Check: Ensure user is logged in and is an Admin
$isLoggedIn = isset($_SESSION['user']);
$isAdmin = false;

if ($isLoggedIn) {
    $userData = $_SESSION['user'];
    // Handle both string and array formats
    if (is_string($userData)) {
        $userData = json_decode($userData, true);
    }
    if (is_array($userData)) {
        $isAdmin = isset($userData['role']) && $userData['role'] === 'Admin';
    }
}

if (!$isLoggedIn || !$isAdmin) {
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

    // 2. Delete the order and related items within a transaction
    $pdo->beginTransaction();
    
    try {
        // First delete order items (foreign key constraint)
        $stmtDeleteItems = $pdo->prepare("DELETE FROM order_items WHERE orderId = ?");
        $stmtDeleteItems->execute([$orderId]);
        $deletedItems = $stmtDeleteItems->rowCount();
        
        // Then delete the order
        $stmtDeleteOrder = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmtDeleteOrder->execute([$orderId]);
        $deletedOrders = $stmtDeleteOrder->rowCount();
        
        if ($deletedOrders > 0) {
            $pdo->commit();
            echo json_encode([
                'success' => true, 
                'message' => "Order deleted successfully. Removed {$deletedItems} order items and 1 order."
            ]);
        } else {
            $pdo->rollBack();
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'Order found but could not be deleted. It might have been deleted by another process.']);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Failed to delete order $orderId in transaction: " . $e->getMessage());
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Failed to delete order due to a database error: ' . $e->getMessage()]);
    }

} catch (PDOException $e) {
    // If using a transaction and an exception occurs before commit: if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Database error in delete-order.php for order ID $orderId: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
