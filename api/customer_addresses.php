<?php
// Customer Addresses API
require_once __DIR__ . '/config.php';

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

// Input normalization: accept camelCase and map to existing snake_case
function wf_normalize_address_input(array $data): array {
    if (isset($data['userId']) && !isset($data['user_id'])) $data['user_id'] = $data['userId'];
    if (isset($data['addressName']) && !isset($data['address_name'])) $data['address_name'] = $data['addressName'];
    if (isset($data['addressLine1']) && !isset($data['address_line1'])) $data['address_line1'] = $data['addressLine1'];
    if (isset($data['addressLine2']) && !isset($data['address_line2'])) $data['address_line2'] = $data['addressLine2'];
    if (isset($data['zipCode']) && !isset($data['zip_code'])) $data['zip_code'] = $data['zipCode'];
    if (isset($data['isDefault']) && !isset($data['is_default'])) $data['is_default'] = $data['isDefault'];
    return $data;
}

// Output normalization: add camelCase alongside legacy snake_case keys
function wf_address_with_camel(array $row): array {
    $out = $row;
    if (array_key_exists('user_id', $row)) $out['userId'] = $row['user_id'];
    if (array_key_exists('address_name', $row)) $out['addressName'] = $row['address_name'];
    if (array_key_exists('address_line1', $row)) $out['addressLine1'] = $row['address_line1'];
    if (array_key_exists('address_line2', $row)) $out['addressLine2'] = $row['address_line2'];
    if (array_key_exists('city', $row)) $out['city'] = $row['city'];
    if (array_key_exists('state', $row)) $out['state'] = $row['state'];
    if (array_key_exists('zip_code', $row)) $out['zipCode'] = $row['zip_code'];
    if (array_key_exists('is_default', $row)) $out['isDefault'] = (int)$row['is_default'];
    return $out;
}

try {
    $pdo = Database::getInstance();

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $userId = $_GET['user_id'] ?? $_POST['user_id'] ?? $_GET['userId'] ?? $_POST['userId'] ?? '';

    switch ($action) {
        case 'get_addresses':
            if (empty($userId)) {
                throw new Exception('User ID is required');
            }

            $addresses = Database::queryAll("SELECT * FROM customer_addresses WHERE user_id = ? ORDER BY is_default DESC, address_name ASC", [$userId]);
            $addresses = array_map('wf_address_with_camel', is_array($addresses) ? $addresses : []);

            echo json_encode([
                'success' => true,
                'addresses' => $addresses
            ]);
            break;

        case 'add_address':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $data = wf_normalize_address_input($data);

            $required = ['user_id', 'address_name', 'address_line1', 'city', 'state', 'zip_code'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("$field is required");
                }
            }

            // If this is set as default, unset other defaults
            if (!empty($data['is_default'])) {
                Database::execute("UPDATE customer_addresses SET is_default = 0 WHERE user_id = ?", [$data['user_id']]);
            }

            Database::execute("INSERT INTO customer_addresses (user_id, address_name, address_line1, address_line2, city, state, zip_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [
                $data['user_id'],
                $data['address_name'],
                $data['address_line1'],
                $data['address_line2'] ?? '',
                $data['city'],
                $data['state'],
                $data['zip_code'],
                !empty($data['is_default']) ? 1 : 0
            ]);

            $addressId = Database::lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Address added successfully',
                'address_id' => $addressId
            ]);
            break;

        case 'update_address':
            $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $data = wf_normalize_address_input($data);

            if (empty($data['id'])) {
                throw new Exception('Address ID is required');
            }

            // If this is set as default, unset other defaults for this user
            if (!empty($data['is_default'])) {
                Database::execute("UPDATE customer_addresses SET is_default = 0 WHERE user_id = (SELECT user_id FROM customer_addresses WHERE id = ?)", [$data['id']]);
            }

            Database::execute("UPDATE customer_addresses SET address_name = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, zip_code = ?, is_default = ? WHERE id = ?", [
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
            $addressId = $_GET['id'] ?? $_POST['id'] ?? $_GET['addressId'] ?? $_POST['addressId'] ?? '';

            if (empty($addressId)) {
                throw new Exception('Address ID is required');
            }

            Database::execute("DELETE FROM customer_addresses WHERE id = ?", [$addressId]);

            echo json_encode([
                'success' => true,
                'message' => 'Address deleted successfully'
            ]);
            break;

        case 'set_default':
            $addressId = $_GET['id'] ?? $_POST['id'] ?? $_GET['addressId'] ?? $_POST['addressId'] ?? '';

            if (empty($addressId)) {
                throw new Exception('Address ID is required');
            }

            // Get user ID for this address
            $address = Database::queryOne("SELECT user_id FROM customer_addresses WHERE id = ?", [$addressId]);

            if (!$address) {
                throw new Exception('Address not found');
            }

            // Unset all defaults for this user
            Database::execute("UPDATE customer_addresses SET is_default = 0 WHERE user_id = ?", [$address['user_id']]);

            // Set this as default
            Database::execute("UPDATE customer_addresses SET is_default = 1 WHERE id = ?", [$addressId]);

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