<?php
require_once 'config.php';
require_once '../includes/functions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authentication check
SessionManager::startSession();
$isLoggedIn = isset($_SESSION['user']) && !empty($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === 'admin';

// Check for admin token as fallback
$adminToken = $_GET['admin_token'] ?? $_POST['admin_token'] ?? '';
$isValidToken = ($adminToken === 'whimsical_admin_2024');

if (!$isAdmin && !$isValidToken) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Parse action from GET, POST, or JSON body
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    // If no action found in GET/POST, try parsing from JSON body
    if (empty($action)) {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $action = $jsonInput['action'] ?? '';
    }
    
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    switch ($action) {
        case 'add':
            if ($method !== 'POST') {
                throw new Exception('POST method required for adding genders');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $itemSku = $input['item_sku'] ?? '';
            $gender = $input['gender'] ?? '';
            
            if (empty($itemSku) || empty($gender)) {
                throw new Exception('Item SKU and gender are required');
            }
            
            // Check if this gender already exists for this item
            $checkSql = "SELECT id FROM item_genders WHERE item_sku = ? AND gender = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$itemSku, $gender]);
            
            if ($checkStmt->rowCount() > 0) {
                throw new Exception('This gender already exists for this item');
            }
            
            // Insert new gender
            $sql = "INSERT INTO item_genders (item_sku, gender, is_active, created_at) VALUES (?, ?, 1, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$itemSku, $gender]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Gender added successfully',
                'gender_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'get_all':
            $itemSku = $_GET['item_sku'] ?? '';
            
            if (empty($itemSku)) {
                throw new Exception('Item SKU is required');
            }
            
            $sql = "SELECT * FROM item_genders WHERE item_sku = ? ORDER BY gender";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$itemSku]);
            
            $genders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'genders' => $genders
            ]);
            break;
            
        case 'delete':
            if ($method !== 'POST') {
                throw new Exception('POST method required for deleting genders');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $genderId = $input['gender_id'] ?? '';
            
            if (empty($genderId)) {
                throw new Exception('Gender ID is required');
            }
            
            $sql = "DELETE FROM item_genders WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$genderId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Gender deleted successfully'
                ]);
            } else {
                throw new Exception('Gender not found or could not be deleted');
            }
            break;
            
        case 'update':
            if ($method !== 'POST') {
                throw new Exception('POST method required for updating genders');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $genderId = $input['gender_id'] ?? '';
            $gender = $input['gender'] ?? '';
            $isActive = $input['is_active'] ?? 1;
            
            if (empty($genderId) || empty($gender)) {
                throw new Exception('Gender ID and gender name are required');
            }
            
            $sql = "UPDATE item_genders SET gender = ?, is_active = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$gender, $isActive, $genderId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Gender updated successfully'
                ]);
            } else {
                throw new Exception('Gender not found or no changes made');
            }
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 