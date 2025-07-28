<?php

// Start output buffering to catch any unexpected output
ob_start();

// Override error display settings for this script
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Require admin authentication for inventory management
require_once 'includes/auth_helper.php';
AuthHelper::requireAdmin();

// Set up error logging to file instead of displaying
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/inventory_errors.log');

// Include database configuration
require_once __DIR__ . '/api/config.php';

// Function to return JSON success response
function returnSuccess($message, $data = null)
{
    // Clear any previous output
    if (ob_get_length()) {
        ob_clean();
    }

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
function returnError($message, $statusCode = 400)
{
    // Clear any previous output
    if (ob_get_length()) {
        ob_clean();
    }

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
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Handle inline editing (specific field update)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sku']) && isset($_POST['field']) && isset($_POST['value'])) {
        $itemSku = trim($_POST['sku']);
        $field = trim($_POST['field']);
        $value = $_POST['value'];

        // Validate field name
        $allowedFields = ['name', 'sku', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice', 'description', 'imageUrl'];
        if (!in_array($field, $allowedFields)) {
            returnError('Invalid field name');
        }

        // Basic validation
        if (empty($itemSku)) {
            returnError('Item SKU cannot be empty');
        }

        // Prepare and execute update - use items table with sku as primary key
        $query = "UPDATE items SET $field = ? WHERE sku = ?";
        $stmt = $pdo->prepare($query);
        if ($stmt->execute([$value, $itemSku])) {
            returnSuccess(ucfirst($field) . ' updated successfully');
        } else {
            returnError('Failed to update ' . $field);
        }
    }
    // Handle form submission (add or update)
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        // Check if this is an add or update action
        if ($action === 'add' || $action === 'update') {
            // Required fields (SKU can be auto-generated)
            $requiredFields = ['name', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice'];
            if ($action === 'update') {
                $requiredFields[] = 'itemSku';
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
                function cat_code($cat)
                {
                    $map = ['T-Shirts' => 'TS','Tumblers' => 'TU','Artwork' => 'AR','Sublimation' => 'SU','WindowWraps' => 'WW'];
                    return $map[$cat] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $cat), 0, 2));
                }
                $code = cat_code($category);
                $stmtSku = $pdo->prepare("SELECT sku FROM items WHERE sku LIKE :pat ORDER BY sku DESC LIMIT 1");
                $stmtSku->execute([':pat' => 'WF-'.$code.'-%']);
                $rowSku = $stmtSku->fetch(PDO::FETCH_ASSOC);
                $num = 1;
                if ($rowSku && preg_match('/WF-'.$code.'-(\d{3})$/', $rowSku['sku'], $m)) {
                    $num = intval($m[1]) + 1;
                }
                $sku = 'WF-'.$code.'-'.str_pad($num, 3, '0', STR_PAD_LEFT);
            }
            $stockLevel = intval($_POST['stockLevel']);
            $reorderPoint = intval($_POST['reorderPoint']);
            $costPrice = floatval($_POST['costPrice']);
            $retailPrice = floatval($_POST['retailPrice']);
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $imageUrl = isset($_POST['existingImageUrl']) ? trim($_POST['existingImageUrl']) : '';

            // Handle new image upload
            if (isset($_FILES['imageUpload']) && $_FILES['imageUpload']['error'] === UPLOAD_ERR_OK) {
                $tmpPath = $_FILES['imageUpload']['tmp_name'];
                $origName = $_FILES['imageUpload']['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $allowedExt = ['png','jpg','jpeg','webp'];
                if (in_array($ext, $allowedExt)) {
                    $unique = substr(md5(uniqid()), 0, 6);
                    $destRel = 'images/items/' . $sku . '-' . $unique . '.' . $ext;
                    $rootDir = __DIR__;
                    $destAbs = $rootDir . '/' . $destRel;
                    $dir = dirname($destAbs);
                    if (!is_dir($dir)) {
                        if (!mkdir($dir, 0777, true)) {
                            error_log('Failed to create image directory: '.$dir);
                        }
                        chmod($dir, 0777);
                    }
                    if (move_uploaded_file($tmpPath, $destAbs)) {
                        chmod($destAbs, 0644);
                        $imageUrl = $destRel;
                    } else {
                        error_log('move_uploaded_file failed from '.$tmpPath.' to '.$destAbs);
                    }
                }
            }

            if ($action === 'add') {
                // Insert query - items table now uses sku as primary key
                $sql = "INSERT INTO items (sku, name, category, stockLevel, reorderPoint, costPrice, retailPrice, description, imageUrl) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([
                    $sku, $name, $category, $stockLevel, $reorderPoint,
                    $costPrice, $retailPrice, $description, $imageUrl
                ]);
                $itemId = $sku; // For consistency with return data
            } else {
                // Update existing item - use itemSku (original SKU) for WHERE clause
                $originalSku = trim($_POST['itemSku']);

                // Update query - use sku as primary key
                $sql = "UPDATE items SET 
                        name = ?, category = ?, sku = ?, stockLevel = ?, 
                        reorderPoint = ?, costPrice = ?, retailPrice = ?, description = ?, imageUrl = ? 
                        WHERE sku = ?";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([
                    $name, $category, $sku, $stockLevel, $reorderPoint,
                    $costPrice, $retailPrice, $description, $imageUrl, $originalSku
                ]);
                $itemSku = $sku; // Use the new SKU for response
            }

            // Check if operation was successful
            if ($success) {
                // No need to sync with products table anymore - items table is the single source of truth

                // TEMP LOG
                $logId = ($action === 'add') ? $sku : $itemSku;
                error_log("[inventory_update] action=$action itemSku=$logId imageUrl=$imageUrl\n", 3, __DIR__ . '/inventory_errors.log');

                $message = "Item " . ($action === 'add' ? "added" : "updated") . " successfully";
                $returnId = ($action === 'add') ? $sku : $itemSku;
                returnSuccess($message, ['sku' => $returnId, 'action' => $action]);
            } else {
                returnError('Failed to ' . $action . ' item');
            }
        } else {
            returnError('Invalid action');
        }
    }
    // Handle JSON input for cost price updates
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST' &&
             isset($_SERVER['CONTENT_TYPE']) &&
             strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            returnError('Invalid JSON format');
        }

        if (isset($data['sku']) && isset($data['costPrice'])) {
            $itemSku = trim($data['sku']);
            $costPrice = floatval($data['costPrice']);

            $query = "UPDATE items SET costPrice = ? WHERE sku = ?";
            $stmt = $pdo->prepare($query);

            if ($stmt->execute([$costPrice, $itemSku])) {
                returnSuccess('Cost price updated successfully');
            } else {
                returnError('Failed to update cost price');
            }
        } else {
            returnError('Missing required parameters');
        }
    }
    // Handle DELETE requests
    elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        parse_str($_SERVER['QUERY_STRING'], $params);

        if (isset($params['action']) && $params['action'] === 'delete' && isset($params['sku'])) {
            $itemSku = trim($params['sku']);

            $stmt = $pdo->prepare("DELETE FROM items WHERE sku = ?");
            if ($stmt->execute([$itemSku])) {
                returnSuccess('Item deleted successfully');
            } else {
                returnError('Failed to delete item');
            }
        } else {
            returnError('Invalid delete request');
        }
    } else {
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
if (ob_get_length()) {
    ob_end_clean();
}
