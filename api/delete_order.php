<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Use centralized authentication
// Admin authentication with token fallback for API access
$isAdmin = false;

// Check session authentication first
require_once __DIR__ . '/../includes/auth.php';
if (isAdminWithToken()) {
    $isAdmin = true;
}

// Admin token fallback for API access
if (!$isAdmin && isset($_GET['admin_token']) && $_GET['admin_token'] === 'whimsical_admin_2024') {
    $isAdmin = true;
}

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
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
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // 1. Check if the order exists
    $row = Database::queryOne("SELECT COUNT(*) AS c FROM orders WHERE id = ?", [$orderId]);
    $orderExists = $row ? (int)$row['c'] : 0;

    if ($orderExists == 0) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }

    // 2. Delete the order and related items within a transaction
    Database::beginTransaction();

    try {
        // First delete order items (foreign key constraint)
        $deletedItems = Database::execute("DELETE FROM order_items WHERE orderId = ?", [$orderId]);

        // Then delete the order
        $deletedOrders = Database::execute("DELETE FROM orders WHERE id = ?", [$orderId]);

        if ($deletedOrders > 0) {
            Database::commit();
            echo json_encode([
                'success' => true,
                'message' => "Order deleted successfully. Removed {$deletedItems} order items and 1 order."
            ]);
        } else {
            Database::rollBack();
            http_response_code(409); // Conflict
            echo json_encode(['success' => false, 'message' => 'Order found but could not be deleted. It might have been deleted by another process.']);
        }
    } catch (Exception $e) {
        Database::rollBack();
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
