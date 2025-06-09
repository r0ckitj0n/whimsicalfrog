<?php
// Simple process_inventory_update.php - handles inventory form submissions

// Set headers for JSON responses
header('Content-Type: application/json');

// Include database configuration
require_once __DIR__ . '/api/config.php';

// Function to return JSON success response
function returnSuccess($message, $data = null) {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Function to return JSON error response
function returnError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

try {
    // Connect to database
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Handle inline editing (specific field update)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['itemId']) && isset($_POST['field']) && isset($_POST['value'])) {
        $itemId = trim($_POST['itemId']);
        $field = trim($_POST['field']);
        $value = $_POST['value'];
        
        // Validate field name
        $allowedFields = ['name', 'category', 'sku', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice', 'description', 'imageUrl'];
        if (!in_array($field, $allowedFields)) {
            returnError('Invalid field name');
        }
        
        // Basic validation
        if (empty($itemId)) {
            returnError('Item ID cannot be empty');
        }
        
        // Prepare and execute update
        $query = "UPDATE inventory SET $field = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        if ($stmt->execute([$value, $itemId])) {
            returnSuccess(ucfirst($field) . ' updated successfully');
        } else {
            returnError('Failed to update ' . $field);
        }
    }
    // Handle form submission (add or update)
    else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Check if this is an add or update action
        if ($action === 'add' || $action === 'update') {
            // Required fields
            $requiredFields = ['name', 'category', 'sku', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice'];
            if ($action === 'update') {
                $requiredFields[] = 'itemId';
            }
            
            // Check required fields
            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field]) || $_POST[$field] === '') {
                    returnError(ucfirst($field) . ' is required');
                }
            }
            
            // Sanitize and prepare data
            $name = trim($_POST['name']);
            $category = trim($_POST['category']);
            $sku = trim($_POST['sku']);
            $stockLevel = intval($_POST['stockLevel']);
            $reorderPoint = intval($_POST['reorderPoint']);
            $costPrice = floatval($_POST['costPrice']);
            $retailPrice = floatval($_POST['retailPrice']);
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $imageUrl = isset($_POST['imageUrl']) ? trim($_POST['imageUrl']) : '';
            $productId = isset($_POST['productId']) ? trim($_POST['productId']) : '';
            
            if ($action === 'add') {
                // Generate new Item ID
                $stmtId = $pdo->query("SELECT id FROM inventory ORDER BY CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC LIMIT 1");
                $lastIdRow = $stmtId->fetch(PDO::FETCH_ASSOC);
                $lastIdNum = $lastIdRow ? (int)substr($lastIdRow['id'], 1) : 0;
                $itemId = 'I' . str_pad($lastIdNum + 1, 3, '0', STR_PAD_LEFT);
                
                // Generate new Product ID if not provided
                if (empty($productId)) {
                    $stmtProdId = $pdo->query("SELECT productId FROM inventory ORDER BY CAST(SUBSTRING(productId, 2) AS UNSIGNED) DESC LIMIT 1");
                    $lastProdIdRow = $stmtProdId->fetch(PDO::FETCH_ASSOC);
                    $lastProdIdNum = $lastProdIdRow ? (int)substr($lastProdIdRow['productId'], 1) : 0;
                    $productId = 'P' . str_pad($lastProdIdNum + 1, 3, '0', STR_PAD_LEFT);
                }
                
                // Insert query
                $sql = "INSERT INTO inventory (id, productId, name, category, sku, stockLevel, reorderPoint, costPrice, retailPrice, description, imageUrl) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([
                    $itemId, $productId, $name, $category, $sku, $stockLevel, $reorderPoint, 
                    $costPrice, $retailPrice, $description, $imageUrl
                ]);
            } else {
                // Update existing item
                $itemId = trim($_POST['itemId']);
                
                // Update query
                $sql = "UPDATE inventory SET 
                        productId = ?, name = ?, category = ?, sku = ?, stockLevel = ?, 
                        reorderPoint = ?, costPrice = ?, retailPrice = ?, description = ?, imageUrl = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([
                    $productId, $name, $category, $sku, $stockLevel, $reorderPoint, 
                    $costPrice, $retailPrice, $description, $imageUrl, $itemId
                ]);
            }
            
            // Check if operation was successful
            if ($success) {
                $message = "Item " . ($action === 'add' ? "added" : "updated") . " successfully";
                returnSuccess($message, ['itemId' => $itemId, 'action' => $action]);
            } else {
                returnError('Failed to ' . $action . ' item');
            }
        } else {
            returnError('Invalid action');
        }
    } 
    // Handle JSON input for cost price updates
    else if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
             isset($_SERVER['CONTENT_TYPE']) && 
             strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            returnError('Invalid JSON format');
        }
        
        if (isset($data['id']) && isset($data['costPrice'])) {
            $itemId = trim($data['id']);
            $costPrice = floatval($data['costPrice']);
            
            $query = "UPDATE inventory SET costPrice = ? WHERE id = ?";
            $stmt = $pdo->prepare($query);
            
            if ($stmt->execute([$costPrice, $itemId])) {
                returnSuccess('Cost price updated successfully');
            } else {
                returnError('Failed to update cost price');
            }
        } else {
            returnError('Missing required parameters');
        }
    }
    // Handle DELETE requests
    else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        parse_str($_SERVER['QUERY_STRING'], $params);
        
        if (isset($params['action']) && $params['action'] === 'delete' && isset($params['itemId'])) {
            $itemId = trim($params['itemId']);
            
            $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
            if ($stmt->execute([$itemId])) {
                returnSuccess('Item deleted successfully');
            } else {
                returnError('Failed to delete item');
            }
        } else {
            returnError('Invalid delete request');
        }
    }
    else {
        returnError('Invalid request method or missing parameters');
    }
} catch (PDOException $e) {
    returnError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    returnError('An unexpected error occurred: ' . $e->getMessage(), 500);
}
?>