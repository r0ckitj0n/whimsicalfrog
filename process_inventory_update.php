<?php
// Start output buffering to catch any unexpected output
ob_start();

// Override error display settings for this script
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Set up error logging to file instead of displaying
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/inventory_errors.log');

// Include database configuration
require_once __DIR__ . '/api/config.php';

// Function to return JSON success response
function returnSuccess($message, $data = null) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Set headers for JSON responses
    header('Content-Type: application/json');
    
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Function to return JSON error response
function returnError($message, $statusCode = 400) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Set headers for JSON responses
    header('Content-Type: application/json');
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
        $allowedFields = ['name', 'sku', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice', 'description', 'imageUrl'];
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
            // Category updates are now handled directly in the items table
            // No need to sync with products table anymore
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
            // Required fields (SKU can be auto-generated)
            $requiredFields = ['name', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice'];
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
            $category = isset($_POST['category']) ? trim($_POST['category']) : '';
            $sku = trim($_POST['sku']);
            if ($sku === '') {
                // auto-generate
                function cat_code($cat){
                    $map=['T-Shirts'=>'TS','Tumblers'=>'TU','Artwork'=>'AR','Sublimation'=>'SU','WindowWraps'=>'WW'];
                    return $map[$cat] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/','',$cat),0,2));
                }
                $code=cat_code($category);
                $stmtSku=$pdo->prepare("SELECT sku FROM inventory WHERE sku LIKE :pat ORDER BY sku DESC LIMIT 1");
                $stmtSku->execute([':pat'=>'WF-'.$code.'-%']);
                $rowSku=$stmtSku->fetch(PDO::FETCH_ASSOC);
                $num=1;
                if($rowSku && preg_match('/WF-'.$code.'-(\d{3})$/',$rowSku['sku'],$m)){ $num=intval($m[1])+1; }
                $sku='WF-'.$code.'-'.str_pad($num,3,'0',STR_PAD_LEFT);
            }
            $stockLevel = intval($_POST['stockLevel']);
            $reorderPoint = intval($_POST['reorderPoint']);
            $costPrice = floatval($_POST['costPrice']);
            $retailPrice = floatval($_POST['retailPrice']);
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $imageUrl = isset($_POST['existingImageUrl']) ? trim($_POST['existingImageUrl']) : '';
            
            // Handle new image upload
            if(isset($_FILES['imageUpload']) && $_FILES['imageUpload']['error'] === UPLOAD_ERR_OK){
                $tmpPath = $_FILES['imageUpload']['tmp_name'];
                $origName = $_FILES['imageUpload']['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $allowedExt = ['png','jpg','jpeg','webp'];
                if(in_array($ext, $allowedExt)){
                    $unique = substr(md5(uniqid()),0,6);
                    $destRel = 'images/items/' . $sku . '-' . $unique . '.' . $ext;
                    $rootDir = __DIR__;
                    $destAbs = $rootDir . '/' . $destRel;
                    $dir = dirname($destAbs);
                    if(!is_dir($dir)){
                        if(!mkdir($dir, 0777, true)){
                            error_log('Failed to create image directory: '.$dir);
                        }
                        chmod($dir, 0777);
                    }
                    if(move_uploaded_file($tmpPath, $destAbs)){
                        chmod($destAbs, 0644);
                        $imageUrl = $destRel;
                    } else {
                        error_log('move_uploaded_file failed from '.$tmpPath.' to '.$destAbs);
                    }
                }
            }
            
            if ($action === 'add') {
                // Generate new Item ID
                $stmtId = $pdo->query("SELECT id FROM items ORDER BY CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC LIMIT 1");
                $lastIdRow = $stmtId->fetch(PDO::FETCH_ASSOC);
                $lastIdNum = $lastIdRow ? (int)substr($lastIdRow['id'], 1) : 0;
                $itemId = 'I' . str_pad($lastIdNum + 1, 3, '0', STR_PAD_LEFT);
                
                // Insert query
                $sql = "INSERT INTO items (id, name, category, sku, stockLevel, reorderPoint, costPrice, retailPrice, description, imageUrl) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([
                    $itemId, $name, $category, $sku, $stockLevel, $reorderPoint, 
                    $costPrice, $retailPrice, $description, $imageUrl
                ]);
            } else {
                // Update existing item
                $itemId = trim($_POST['itemId']);
                
                // Update query
                $sql = "UPDATE items SET 
                        name = ?, category = ?, sku = ?, stockLevel = ?, 
                        reorderPoint = ?, costPrice = ?, retailPrice = ?, description = ?, imageUrl = ? 
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([
                    $name, $category, $sku, $stockLevel, $reorderPoint, 
                    $costPrice, $retailPrice, $description, $imageUrl, $itemId
                ]);
            }
            
            // Check if operation was successful
            if ($success) {
                // No need to sync with products table anymore - items table is the single source of truth

                // TEMP LOG
                error_log("[inventory_update] action=$action itemId=$itemId imageUrl=$imageUrl\n", 3, __DIR__ . '/inventory_errors.log');

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
    error_log("Database error in process_inventory_update.php: " . $e->getMessage());
    returnError('Database error occurred. Please check server logs.', 500);
} catch (Exception $e) {
    error_log("General error in process_inventory_update.php: " . $e->getMessage());
    returnError('An unexpected error occurred. Please check server logs.', 500);
}

// Clear any remaining output buffer before script ends
if (ob_get_length()) ob_end_clean();
?>
