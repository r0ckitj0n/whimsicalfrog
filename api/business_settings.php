<?php
// Business Settings API
// Handles CRUD operations for business settings

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    switch ($method) {
        case 'GET':
            handleGet($pdo, $action);
            break;
        case 'POST':
            handlePost($pdo);
            break;
        case 'PUT':
            handlePut($pdo);
            break;
        case 'DELETE':
            handleDelete($pdo);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function handleGet($pdo, $action) {
    switch ($action) {
        case 'get_all':
            getAllSettings($pdo);
            break;
        case 'get_by_category':
            getSettingsByCategory($pdo);
            break;
        case 'get_setting':
            getSingleSetting($pdo);
            break;
        case 'get_categories':
            getCategories($pdo);
            break;
        default:
            getAllSettings($pdo);
    }
}

function getAllSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM business_settings ORDER BY category, display_order");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by category
    $grouped = [];
    foreach ($settings as $setting) {
        $grouped[$setting['category']][] = $setting;
    }
    
    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'grouped' => $grouped
    ]);
}

function getSettingsByCategory($pdo) {
    $category = $_GET['category'] ?? '';
    if (empty($category)) {
        echo json_encode(['success' => false, 'error' => 'Category is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE category = ? ORDER BY display_order");
    $stmt->execute([$category]);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'category' => $category,
        'settings' => $settings
    ]);
}

function getSingleSetting($pdo) {
    $key = $_GET['key'] ?? '';
    if (empty($key)) {
        echo json_encode(['success' => false, 'error' => 'Setting key is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$setting) {
        echo json_encode(['success' => false, 'error' => 'Setting not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'setting' => $setting
    ]);
}

function getCategories($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT category FROM business_settings ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
}

function handlePost($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        return;
    }
    
    $required = ['setting_key', 'setting_value', 'setting_type', 'category', 'display_name'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Validate setting_type
    $validTypes = ['text', 'color', 'email', 'url', 'number', 'json', 'boolean'];
    if (!in_array($input['setting_type'], $validTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid setting type']);
        return;
    }
    
    // Validate setting_value based on type
    $validationResult = validateSettingValue($input['setting_value'], $input['setting_type']);
    if (!$validationResult['valid']) {
        echo json_encode(['success' => false, 'error' => $validationResult['error']]);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO business_settings 
            (setting_key, setting_value, setting_type, category, display_name, description, is_required, display_order) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $input['setting_key'],
            $input['setting_value'],
            $input['setting_type'],
            $input['category'],
            $input['display_name'],
            $input['description'] ?? '',
            $input['is_required'] ?? false,
            $input['display_order'] ?? 0
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Setting created successfully',
            'setting_id' => $pdo->lastInsertId()
        ]);
        
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate key
            echo json_encode(['success' => false, 'error' => 'Setting key already exists']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        }
    }
}

function handlePut($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['setting_key'])) {
        echo json_encode(['success' => false, 'error' => 'Setting key is required']);
        return;
    }
    
    $settingKey = $input['setting_key'];
    
    // Get current setting to validate type
    $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE setting_key = ?");
    $stmt->execute([$settingKey]);
    $currentSetting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentSetting) {
        echo json_encode(['success' => false, 'error' => 'Setting not found']);
        return;
    }
    
    // Validate new value if provided
    if (isset($input['setting_value'])) {
        $validationResult = validateSettingValue($input['setting_value'], $currentSetting['setting_type']);
        if (!$validationResult['valid']) {
            echo json_encode(['success' => false, 'error' => $validationResult['error']]);
            return;
        }
    }
    
    // Build update query dynamically
    $updateFields = [];
    $params = [];
    
    $allowedFields = ['setting_value', 'display_name', 'description', 'is_required', 'display_order'];
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($updateFields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }
    
    $params[] = $settingKey; // For WHERE clause
    
    try {
        $stmt = $pdo->prepare("UPDATE business_settings SET " . implode(', ', $updateFields) . " WHERE setting_key = ?");
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Setting updated successfully'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDelete($pdo) {
    $settingKey = $_GET['key'] ?? '';
    if (empty($settingKey)) {
        echo json_encode(['success' => false, 'error' => 'Setting key is required']);
        return;
    }
    
    // Check if setting is required
    $stmt = $pdo->prepare("SELECT is_required FROM business_settings WHERE setting_key = ?");
    $stmt->execute([$settingKey]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$setting) {
        echo json_encode(['success' => false, 'error' => 'Setting not found']);
        return;
    }
    
    if ($setting['is_required']) {
        echo json_encode(['success' => false, 'error' => 'Cannot delete required setting']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM business_settings WHERE setting_key = ?");
        $stmt->execute([$settingKey]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Setting deleted successfully'
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function validateSettingValue($value, $type) {
    switch ($type) {
        case 'email':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return ['valid' => false, 'error' => 'Invalid email format'];
            }
            break;
            
        case 'url':
            // Allow domain without protocol
            if (!empty($value) && !preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $value)) {
                return ['valid' => false, 'error' => 'Invalid domain format'];
            }
            break;
            
        case 'color':
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                return ['valid' => false, 'error' => 'Invalid color format (use #RRGGBB)'];
            }
            break;
            
        case 'number':
            if (!is_numeric($value)) {
                return ['valid' => false, 'error' => 'Value must be a number'];
            }
            break;
            
        case 'json':
            $decoded = json_decode($value);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['valid' => false, 'error' => 'Invalid JSON format'];
            }
            break;
            
        case 'boolean':
            if (!in_array(strtolower($value), ['true', 'false', '1', '0'])) {
                return ['valid' => false, 'error' => 'Boolean value must be true/false or 1/0'];
            }
            break;
    }
    
    return ['valid' => true];
}

// Helper function to get a single setting value (for use in other files)
function getBusinessSetting($key, $default = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value, setting_type FROM business_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return $default;
        }
        
        $value = $result['setting_value'];
        
        // Convert based on type
        switch ($result['setting_type']) {
            case 'boolean':
                return in_array(strtolower($value), ['true', '1']);
            case 'number':
                return is_numeric($value) ? (float)$value : $default;
            case 'json':
                $decoded = json_decode($value, true);
                return $decoded !== null ? $decoded : $default;
            default:
                return $value;
        }
        
    } catch (Exception $e) {
        return $default;
    }
}
?> 