<?php
// Customer Addresses API
require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/helpers/AddressValidationHelper.php';

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

// Input normalization: ensure snake_case keys
function wf_normalize_address_input(array $data): array
{
    return $data;
}

// Output normalization: ensures snake_case
function wf_address_normalized(array $row): array
{
    return [
        'id' => $row['id'],
        'user_id' => $row['owner_id'] ?? '',
        'address_name' => $row['address_name'] ?? '',
        'address_line_1' => $row['address_line_1'] ?? '',
        'address_line_2' => $row['address_line_2'] ?? '',
        'city' => $row['city'] ?? '',
        'state' => $row['state'] ?? '',
        'zip_code' => $row['zip_code'] ?? '',
        'is_default' => (int) ($row['is_default'] ?? 0),
        'created_at' => $row['created_at'] ?? null,
        'updated_at' => $row['updated_at'] ?? null
    ];
}

try {
    $pdo = Database::getInstance();

    // Decode JSON body if present and merge with $_POST
    $jsonData = json_decode(file_get_contents('php://input'), true) ?: [];
    if (!empty($jsonData)) {
        $_POST = array_merge($_POST, $jsonData);
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $allowedActions = ['get_addresses', 'add_address', 'update_address', 'delete_address', 'set_default'];
    if (!in_array($action, $allowedActions, true)) {
        throw new Exception('Invalid action');
    }
    $getOnlyActions = ['get_addresses'];
    if (in_array($action, $getOnlyActions, true) && $method !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'GET method required']);
        exit;
    }
    if (!in_array($action, $getOnlyActions, true) && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'POST method required']);
        exit;
    }

    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Authentication required'
        ]);
        exit;
    }

    switch ($action) {
        case 'get_addresses':
            if (empty($user_id)) {
                throw new Exception('User ID is required');
            }
            if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $user_id)) {
                throw new InvalidArgumentException('Invalid user ID');
            }
            if (!AddressValidationHelper::canMutateCustomerOwner((string) $user_id)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                exit;
            }

            $addresses = Database::queryAll("SELECT * FROM addresses WHERE owner_type = ? AND owner_id = ? ORDER BY is_default DESC, address_name ASC", ['customer', $user_id]);
            $addresses = array_map('wf_address_normalized', is_array($addresses) ? $addresses : []);

            echo json_encode([
                'success' => true,
                'addresses' => $addresses
            ]);
            break;

        case 'add_address':
            $data = wf_normalize_address_input($_POST);
            $targetUserId = trim((string) ($data['user_id'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $targetUserId)) {
                throw new InvalidArgumentException('Invalid user ID');
            }
            if (!AddressValidationHelper::canMutateCustomerOwner($targetUserId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                exit;
            }
            AddressValidationHelper::assertOwnerExists('customer', $targetUserId);
            $normalized = AddressValidationHelper::normalize($data);
            AddressValidationHelper::assertRequired($normalized);

            // If this is set as default, unset other defaults
            if ((int) $normalized['is_default'] === 1) {
                Database::execute("UPDATE addresses SET is_default = 0 WHERE owner_type = ? AND owner_id = ?", ['customer', $targetUserId]);
            }

            Database::execute("INSERT INTO addresses (owner_type, owner_id, address_name, address_line_1, address_line_2, city, state, zip_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                'customer',
                $targetUserId,
                $normalized['address_name'],
                $normalized['address_line_1'],
                $normalized['address_line_2'],
                $normalized['city'],
                $normalized['state'],
                $normalized['zip_code'],
                (int) $normalized['is_default']
            ]);

            $addressId = Database::lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Address added successfully',
                'address_id' => $addressId
            ]);
            break;

        case 'update_address':
            $data = wf_normalize_address_input($_POST);

            if (empty($data['id'])) {
                throw new Exception('Address ID is required');
            }
            if (!ctype_digit((string) $data['id'])) {
                throw new InvalidArgumentException('Invalid address ID');
            }
            $existingAddress = Database::queryOne("SELECT owner_id FROM addresses WHERE id = ? AND owner_type = ?", [$data['id'], 'customer']);
            if (!$existingAddress) {
                throw new Exception('Address not found');
            }
            $targetUserId = (string) ($existingAddress['owner_id'] ?? '');
            if (!AddressValidationHelper::canMutateCustomerOwner($targetUserId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                exit;
            }
            AddressValidationHelper::assertOwnerExists('customer', $targetUserId);
            $normalized = AddressValidationHelper::normalize($data);
            AddressValidationHelper::assertRequired($normalized);

            // If this is set as default, unset other defaults for this user
            if ((int) $normalized['is_default'] === 1) {
                Database::execute("UPDATE addresses SET is_default = 0 WHERE owner_type = ? AND owner_id = ?", ['customer', $targetUserId]);
            }

            Database::execute("UPDATE addresses SET address_name = ?, address_line_1 = ?, address_line_2 = ?, city = ?, state = ?, zip_code = ?, is_default = ? WHERE id = ? AND owner_type = ?", [
                $normalized['address_name'],
                $normalized['address_line_1'],
                $normalized['address_line_2'],
                $normalized['city'],
                $normalized['state'],
                $normalized['zip_code'],
                (int) $normalized['is_default'],
                $data['id'],
                'customer'
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
            if (!ctype_digit((string) $addressId)) {
                throw new InvalidArgumentException('Invalid address ID');
            }

            $address = Database::queryOne("SELECT owner_id FROM addresses WHERE id = ? AND owner_type = ?", [$addressId, 'customer']);
            if (!$address) {
                throw new Exception('Address not found');
            }
            $targetUserId = (string) ($address['owner_id'] ?? '');
            if (!AddressValidationHelper::canMutateCustomerOwner($targetUserId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                exit;
            }
            Database::execute("DELETE FROM addresses WHERE id = ? AND owner_type = ?", [$addressId, 'customer']);

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
            if (!ctype_digit((string) $addressId)) {
                throw new InvalidArgumentException('Invalid address ID');
            }

            // Get user ID for this address
            $address = Database::queryOne("SELECT owner_id FROM addresses WHERE id = ? AND owner_type = ?", [$addressId, 'customer']);

            if (!$address) {
                throw new Exception('Address not found');
            }
            $targetUserId = (string) ($address['owner_id'] ?? '');
            if (!AddressValidationHelper::canMutateCustomerOwner($targetUserId)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Forbidden']);
                exit;
            }

            // Unset all defaults for this user
            Database::execute("UPDATE addresses SET is_default = 0 WHERE owner_type = ? AND owner_id = ?", ['customer', $targetUserId]);

            // Set this as default
            Database::execute("UPDATE addresses SET is_default = 1 WHERE id = ? AND owner_type = ?", [$addressId, 'customer']);

            echo json_encode([
                'success' => true,
                'message' => 'Default address updated'
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
