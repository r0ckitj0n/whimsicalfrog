<?php
// Order Management API - Admin features for order editing
require_once 'config.php';
require_once __DIR__ . '/../includes/response.php';

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    Database::getInstance();

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'add_item_to_order':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            $required = ['order_id', 'sku', 'quantity', 'price'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("$field is required");
                }
            }

            // Check if item already exists in order
            $existing = Database::queryOne("SELECT id, quantity FROM order_items WHERE orderId = ? AND sku = ?", [$data['order_id'], $data['sku']]);

            if ($existing) {
                // Update existing item quantity
                $newQuantity = $existing['quantity'] + $data['quantity'];
                Database::execute("UPDATE order_items SET quantity = ?, price = ? WHERE id = ?", [$newQuantity, $data['price'], $existing['id']]);
                $message = 'Item quantity updated in order';
            } else {
                // Add new item to order
                Database::execute("INSERT INTO order_items (orderId, sku, quantity, price) VALUES (?, ?, ?, ?)", [$data['order_id'], $data['sku'], $data['quantity'], $data['price']]);
                $message = 'Item added to order';
            }

            // Recalculate order total
            $row = Database::queryOne("SELECT SUM(quantity * price) as total FROM order_items WHERE orderId = ?", [$data['order_id']]);
            $newTotal = $row['total'] ?? 0;

            // Update order total
            Database::execute("UPDATE orders SET total = ? WHERE id = ?", [$newTotal, $data['order_id']]);

            Response::updated(['message' => $message, 'new_total' => $newTotal]);
            break;

        case 'remove_item_from_order':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            if (empty($data['order_item_id'])) {
                throw new Exception('Order item ID is required');
            }

            // Get order ID before deleting
            $orderRow = Database::queryOne("SELECT orderId FROM order_items WHERE id = ?", [$data['order_item_id']]);
            $orderId = $orderRow ? $orderRow['orderId'] : null;

            if (!$orderId) {
                throw new Exception('Order item not found');
            }

            // Delete the item
            Database::execute("DELETE FROM order_items WHERE id = ?", [$data['order_item_id']]);

            // Recalculate order total
            $row = Database::queryOne("SELECT SUM(quantity * price) as total FROM order_items WHERE orderId = ?", [$orderId]);
            $newTotal = $row['total'] ?? 0;

            // Update order total
            Database::execute("UPDATE orders SET total = ? WHERE id = ?", [$newTotal, $orderId]);

            Response::updated(['message' => 'Item removed from order', 'new_total' => $newTotal]);
            break;

        case 'update_item_quantity':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            $required = ['order_item_id', 'quantity'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("$field is required");
                }
            }

            if ($data['quantity'] <= 0) {
                throw new Exception('Quantity must be greater than 0');
            }

            // Get order ID
            $rowOrder = Database::queryOne("SELECT orderId FROM order_items WHERE id = ?", [$data['order_item_id']]);
            $orderId = $rowOrder ? $rowOrder['orderId'] : null;

            if (!$orderId) {
                throw new Exception('Order item not found');
            }

            // Update quantity
            Database::execute("UPDATE order_items SET quantity = ? WHERE id = ?", [$data['quantity'], $data['order_item_id']]);

            // Recalculate order total
            $rowTotal = Database::queryOne("SELECT SUM(quantity * price) as total FROM order_items WHERE orderId = ?", [$orderId]);
            $newTotal = $rowTotal && isset($rowTotal['total']) ? (float)$rowTotal['total'] : 0;

            // Update order total
            Database::execute("UPDATE orders SET total = ? WHERE id = ?", [$newTotal, $orderId]);

            Response::updated(['message' => 'Item quantity updated', 'new_total' => $newTotal]);
            break;

        case 'update_order_address':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            $required = ['order_id', 'address_data'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("$field is required");
                }
            }

            $address = $data['address_data'];

            // Update the user's address fields in the users table (since orders join with users for address)
            $row = Database::queryOne("SELECT userId FROM orders WHERE id = ?", [$data['order_id']]);
            $userId = $row ? $row['userId'] : null;

            if (!$userId) {
                throw new Exception('Order not found');
            }

            Database::execute("UPDATE users SET addressLine1 = ?, addressLine2 = ?, city = ?, state = ?, zipCode = ? WHERE id = ?", [
                $address['address_line1'] ?? $address['addressLine1'] ?? '',
                $address['address_line2'] ?? $address['addressLine2'] ?? '',
                $address['city'] ?? '',
                $address['state'] ?? '',
                $address['zip_code'] ?? $address['zipCode'] ?? '',
                $userId
            ]);

            Response::updated(['message' => 'Order address updated']);
            break;

        case 'get_available_items':
            $search = $_GET['search'] ?? '';

            $sql = "SELECT i.sku, i.name, i.retailPrice, COALESCE(img.image_path, i.imageUrl) as imageUrl FROM items i LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1";
            $params = [];

            if (!empty($search)) {
                $sql .= " WHERE i.sku LIKE ? OR i.name LIKE ?";
                $params = ["%$search%", "%$search%"];
            }

            $sql .= " ORDER BY i.name LIMIT 50";

            $items = Database::queryAll($sql, $params);

            echo json_encode([
                'success' => true,
                'items' => $items
            ]);
            break;

        case 'impersonate_customer':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

            if (empty($data['customer_id'])) {
                throw new Exception('Customer ID is required');
            }

            // Get customer details
            $customer = Database::queryOne("SELECT * FROM users WHERE id = ? AND role = 'Customer'", [$data['customer_id']]);

            if (!$customer) {
                throw new Exception('Customer not found');
            }

            // Start session if not already started
            if (session_status() == PHP_SESSION_NONE) {

            }

            // Store original admin user data
            if (!isset($_SESSION['original_admin'])) {
                $_SESSION['original_admin'] = $_SESSION['user'] ?? null;
            }

            // Set session to customer
            $_SESSION['user'] = [
                'userId' => $customer['id'],
                'username' => $customer['username'],
                'email' => $customer['email'],
                'role' => $customer['role'],
                'firstName' => $customer['firstName'],
                'lastName' => $customer['lastName'],
                'impersonated' => true
            ];

            Response::updated(['message' => 'Now impersonating customer: ' . $customer['username'], 'customer' => $_SESSION['user'], 'redirect_url' => '/?impersonating=true']);
            break;

        case 'stop_impersonation':
            if (session_status() == PHP_SESSION_NONE) {

            }

            if (isset($_SESSION['original_admin'])) {
                $_SESSION['user'] = $_SESSION['original_admin'];
                unset($_SESSION['original_admin']);
            }

            Response::updated(['message' => 'Stopped impersonating customer', 'redirect_url' => '/?page=admin']);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 