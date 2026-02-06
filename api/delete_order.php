<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

// Centralized admin check
AuthHelper::requireAdmin();

// Check if the request method is DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    Response::methodNotAllowed('Invalid request method. Only DELETE is allowed.');
}

// Get order_id from query parameter
$order_id = $_GET['order_id'] ?? null;

if (empty($order_id)) {
    Response::error('Order ID is required.', null, 400);
}

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // 1. Check if the order exists
    $row = Database::queryOne("SELECT COUNT(*) AS c FROM orders WHERE id = ?", [$order_id]);
    $orderExists = $row ? (int)$row['c'] : 0;

    if ($orderExists == 0) {
        Response::notFound('Order not found.');
    }

    // 2. Delete the order and related items within a transaction
    Database::beginTransaction();

    try {
        // First delete order items (foreign key constraint)
        $deletedItems = Database::execute("DELETE FROM order_items WHERE order_id = ?", [$order_id]);

         // Best-effort: remove attribution row(s) if present
         try {
             Database::execute("DELETE FROM order_attributions WHERE BINARY order_id = BINARY ?", [$order_id]);
         } catch (Throwable $____e) {
         }

        // Then delete the order
        $deletedOrders = Database::execute("DELETE FROM orders WHERE id = ?", [$order_id]);

        if ($deletedOrders > 0) {
            Database::commit();

             // Invalidate marketing microcache best-effort
             try {
                 $cacheDir = sys_get_temp_dir() . '/wf_cache';
                 $files = @glob($cacheDir . '/marketing_overview_*.json') ?: [];
                 foreach ($files as $f) { @unlink($f); }
             } catch (Throwable $____e) {
             }

            Response::success(['message' => "Order deleted successfully. Removed {$deletedItems} order items and 1 order."]);
        } else {
            Database::rollBack();
            Response::error('Order found but could not be deleted. It might have been deleted by another process.', null, 409);
        }
    } catch (Exception $e) {
        Database::rollBack();
        error_log("Failed to delete order $order_id in transaction: " . $e->getMessage());
        Response::serverError('Failed to delete order due to a database error: ' . $e->getMessage());
    }

} catch (PDOException $e) {
    // If using a transaction and an exception occurs before commit: if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Database error in delete-order.php for order ID $order_id: " . $e->getMessage());
    Response::serverError('Database error: ' . $e->getMessage());
}
