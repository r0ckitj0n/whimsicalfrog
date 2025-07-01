<?php
// Customer Addresses API
require_once 'config.php';

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
    $pdo = Database::getInstance();
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $userId = $_GET['user_id'] ?? $_POST['user_id'] ?? '';
    
    switch ($action) {
        case 'get_addresses':
            if (empty($userId)) {
                throw new Exception('User ID is required');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE user_id = ? ORDER BY is_default DESC, address_name ASC");
            $stmt->execute([$userId]);
            $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'addresses' => $addresses
            ]);
            break;
            
        case 'add_address':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            $required = ['user_id', 'address_name', 'address_line1', 'city', 'state', 'zip_code'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("$field is required");
                }
            }
            
            // If this is set as default, unset other defaults
            if (!empty($data['is_default'])) {
                $stmt = $pdo->prepare("UPDATE customer_addresses SET is_default = 0 WHERE user_id = ?");
                $stmt->execute([$data['user_id']]);
            }
            
            $stmt = $pdo->prepare("INSERT INTO customer_addresses (user_id, address_name, address_line1, address_line2, city, state, zip_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['user_id'],
                $data['address_name'],
                $data['address_line1'],
                $data['address_line2'] ?? '',
                $data['city'],
                $data['state'],
                $data['zip_code'],
                !empty($data['is_default']) ? 1 : 0
            ]);
            
            $addressId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Address added successfully',
                'address_id' => $addressId
            ]);
            break;
            
        case 'update_address':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            if (empty($data['id'])) {
                throw new Exception('Address ID is required');
            }
            
            // If this is set as default, unset other defaults for this user
            if (!empty($data['is_default'])) {
                $stmt = $pdo->prepare("UPDATE customer_addresses SET is_default = 0 WHERE user_id = (SELECT user_id FROM customer_addresses WHERE id = ?)");
                $stmt->execute([$data['id']]);
            }
            
            $stmt = $pdo->prepare("UPDATE customer_addresses SET address_name = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, zip_code = ?, is_default = ? WHERE id = ?");
            $stmt->execute([
                $data['address_name'],
                $data['address_line1'],
                $data['address_line2'] ?? '',
                $data['city'],
                $data['state'],
                $data['zip_code'],
                !empty($data['is_default']) ? 1 : 0,
                $data['id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Address updated successfully'
            ]);
            break;
            
        case 'delete_address':
            $addressId = $_GET['id'] ?? $_POST['id'] ?? '';
            
            if (empty($addressId)) {
                throw new Exception('Address ID is required');
            }
            
            $stmt = $pdo->prepare("DELETE FROM customer_addresses WHERE id = ?");
            $stmt->execute([$addressId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Address deleted successfully'
            ]);
            break;
            
        case 'set_default':
            $addressId = $_GET['id'] ?? $_POST['id'] ?? '';
            
            if (empty($addressId)) {
                throw new Exception('Address ID is required');
            }
            
            // Get user ID for this address
            $stmt = $pdo->prepare("SELECT user_id FROM customer_addresses WHERE id = ?");
            $stmt->execute([$addressId]);
            $address = $stmt->fetch();
            
            if (!$address) {
                throw new Exception('Address not found');
            }
            
            // Unset all defaults for this user
            $stmt = $pdo->prepare("UPDATE customer_addresses SET is_default = 0 WHERE user_id = ?");
            $stmt->execute([$address['user_id']]);
            
            // Set this as default
            $stmt = $pdo->prepare("UPDATE customer_addresses SET is_default = 1 WHERE id = ?");
            $stmt->execute([$addressId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Default address updated'
            ]);
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