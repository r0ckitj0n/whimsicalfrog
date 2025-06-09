<?php
// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers for AJAX requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include database configuration
require_once __DIR__ . '/api/config.php';

// Improved AJAX detection - check multiple indicators
function isAjaxRequest() {
    // Method 1: Check for X-Requested-With header (most common)
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    
    // Method 2: Check for Accept header containing 'json'
    if (isset($_SERVER['HTTP_ACCEPT']) && 
        strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        return true;
    }
    
    // Method 3: Check if request was made to a specific API endpoint
    if (strpos($_SERVER['REQUEST_URI'], 'process_inventory_update.php') !== false && 
        $_SERVER['REQUEST_METHOD'] === 'POST') {
        // This is likely an API call
        return true;
    }
    
    return false;
}

// Set AJAX flag
$isAjax = isAjaxRequest();

// Debug log function - only logs in development environment
function debugLog($message, $data = null) {
    global $isLocalhost;
    if ($isLocalhost) {
        error_log("AJAX DEBUG: " . $message . ($data ? " - " . json_encode($data) : ""));
    }
}

// Check for JSON input
$jsonInput = false;
$inputData = [];
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : '';

if (strpos($contentType, 'application/json') !== false) {
    $jsonInput = true;
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        debugLog("JSON parse error", json_last_error_msg());
        returnJsonError('Invalid JSON format: ' . json_last_error_msg(), 400);
    }
}

try {
    // Create database connection
    $pdo = new PDO($dsn, $user, $pass, $options);
    debugLog("Database connection established");
    
    // Handle inline editing (AJAX with specific field)
    if ($isAjax && isset($_POST['itemId']) && isset($_POST['field']) && isset($_POST['value'])) {
        debugLog("Processing inline edit", $_POST);
        $itemId = trim($_POST['itemId']);
        $field = trim($_POST['field']);
        $value = $_POST['value'];
        
        // Validate item ID format
        if (empty($itemId)) {
            returnJsonError('Item ID cannot be empty', 400);
        }
        
        // Validate field name to prevent SQL injection
        $allowedFields = ['name', 'category', 'sku', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice', 'description', 'imageUrl'];
        if (!in_array($field, $allowedFields)) {
            returnJsonError('Invalid field name: ' . htmlspecialchars($field), 400);
        }
        
        // Validate input based on field type
        switch ($field) {
            case 'stockLevel':
            case 'reorderPoint':
                if (!is_numeric($value) || intval($value) < 0) {
                    returnJsonError('Value must be a non-negative integer', 400);
                }
                $value = intval($value);
                break;
                
            case 'costPrice':
            case 'retailPrice':
                if (!is_numeric($value) || floatval($value) < 0) {
                    returnJsonError('Price must be a non-negative number', 400);
                }
                $value = floatval($value);
                break;
                
            default:
                // For text fields, just sanitize
                $value = trim($value);
                if (empty($value) && ($field === 'name' || $field === 'category' || $field === 'sku')) {
                    returnJsonError('This field cannot be empty', 400);
                }
        }
        
        // Check if item exists
        $checkStmt = $pdo->prepare("SELECT id FROM inventory WHERE id = ?");
        $checkStmt->execute([$itemId]);
        if (!$checkStmt->fetch()) {
            returnJsonError('Item not found with ID: ' . htmlspecialchars($itemId), 404);
        }
        
        // Update the specific field
        $query = "UPDATE inventory SET $field = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        
        if ($stmt->execute([$value, $itemId])) {
            debugLog("Field updated successfully", ["field" => $field, "value" => $value]);
            returnJsonSuccess('Field updated successfully');
        } else {
            $errorInfo = $stmt->errorInfo();
            debugLog("Database update error", $errorInfo);
            returnJsonError('Failed to update field: ' . $errorInfo[2], 500);
        }
    }
    // Handle full item update via JSON (for cost price updates from suggested cost)
    else if ($jsonInput && isset($inputData['id'])) {
        debugLog("Processing JSON update", $inputData);
        $itemId = trim($inputData['id']);
        
        // Validate required fields
        if (!isset($inputData['costPrice']) || !is_numeric($inputData['costPrice']) || floatval($inputData['costPrice']) < 0) {
            returnJsonError('Cost price must be a non-negative number', 400);
        }
        
        // Check if item exists
        $checkStmt = $pdo->prepare("SELECT id FROM inventory WHERE id = ?");
        $checkStmt->execute([$itemId]);
        if (!$checkStmt->fetch()) {
            returnJsonError('Item not found with ID: ' . htmlspecialchars($itemId), 404);
        }
        
        // Update cost price
        $costPrice = floatval($inputData['costPrice']);
        $query = "UPDATE inventory SET costPrice = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        
        if ($stmt->execute([$costPrice, $itemId])) {
            debugLog("Cost price updated successfully", ["costPrice" => $costPrice]);
            returnJsonSuccess('Cost price updated successfully');
        } else {
            $errorInfo = $stmt->errorInfo();
            debugLog("Database update error", $errorInfo);
            returnJsonError('Failed to update cost price: ' . $errorInfo[2], 500);
        }
    }
    // Handle traditional form submission
    else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
        debugLog("Processing form submission", $_POST);
        // Validate required fields
        $requiredFields = ['itemId', 'productId', 'name', 'category', 'sku', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice'];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                if ($isAjax) {
                    returnJsonError("Missing required field: $field", 400);
                } else {
                    header("Location: ?page=admin&section=inventory&message=Missing required field: $field&type=error");
                    exit;
                }
            }
        }
        
        // Get form data
        $id = trim($_POST['itemId']);
        $productId = trim($_POST['productId']);
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $sku = trim($_POST['sku']);
        $stockLevel = intval($_POST['stockLevel']);
        $reorderPoint = intval($_POST['reorderPoint']);
        $costPrice = floatval($_POST['costPrice']);
        $retailPrice = floatval($_POST['retailPrice']);
        $description = isset($_POST['description']) ? trim($_POST['description']) : '';
        $imageUrl = isset($_POST['imageUrl']) ? trim($_POST['imageUrl']) : '';
        
        // Validate numeric fields
        if ($stockLevel < 0 || $reorderPoint < 0 || $costPrice < 0 || $retailPrice < 0) {
            if ($isAjax) {
                returnJsonError("Numeric values must be non-negative", 400);
            } else {
                header("Location: ?page=admin&section=inventory&message=Numeric values must be non-negative&type=error");
                exit;
            }
        }
        
        // Update the item
        $updateStmt = $pdo->prepare("UPDATE inventory SET 
                                    productId = ?, 
                                    name = ?, 
                                    category = ?, 
                                    sku = ?, 
                                    stockLevel = ?, 
                                    reorderPoint = ?, 
                                    costPrice = ?, 
                                    retailPrice = ?, 
                                    description = ?, 
                                    imageUrl = ? 
                                    WHERE id = ?");
        
        if ($updateStmt->execute([$productId, $name, $category, $sku, $stockLevel, $reorderPoint, $costPrice, $retailPrice, $description, $imageUrl, $id])) {
            if ($isAjax) {
                returnJsonSuccess('Item updated successfully');
            } else {
                header("Location: ?page=admin&section=inventory&message=Item updated successfully&type=success");
                exit;
            }
        } else {
            $errorInfo = $updateStmt->errorInfo();
            debugLog("Database update error", $errorInfo);
            if ($isAjax) {
                returnJsonError('Failed to update item: ' . $errorInfo[2], 500);
            } else {
                header("Location: ?page=admin&section=inventory&message=Failed to update item&type=error");
                exit;
            }
        }
    }
    // No valid action specified
    else {
        debugLog("Invalid request", ["method" => $_SERVER['REQUEST_METHOD'], "post" => $_POST, "get" => $_GET]);
        if ($isAjax) {
            returnJsonError('Invalid request. Missing required parameters.', 400);
        } else {
            header("Location: ?page=admin&section=inventory&message=Invalid request&type=error");
            exit;
        }
    }
    
} catch (PDOException $e) {
    // Handle database errors
    debugLog("PDO Exception", ["message" => $e->getMessage()]);
    if ($isAjax) {
        returnJsonError('Database error: ' . $e->getMessage(), 500);
    } else {
        header("Location: ?page=admin&section=inventory&message=Database error: " . urlencode($e->getMessage()) . "&type=error");
        exit;
    }
} catch (Exception $e) {
    // Handle general errors
    debugLog("General Exception", ["message" => $e->getMessage()]);
    if ($isAjax) {
        returnJsonError('Error: ' . $e->getMessage(), 500);
    } else {
        header("Location: ?page=admin&section=inventory&message=Error: " . urlencode($e->getMessage()) . "&type=error");
        exit;
    }
}

// Helper function to return JSON success response with appropriate status code
function returnJsonSuccess($message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    $response = [
        'success' => true,
        'message' => $message
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Helper function to return JSON error response with appropriate status code
function returnJsonError($message, $statusCode = 400) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}
?>
