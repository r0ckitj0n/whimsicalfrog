<?php

// Start output buffering to catch any unexpected output
ob_start();

// Override error display settings for this script
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Require admin authentication for inventory management (absolute path)
require_once dirname(__DIR__) . '/includes/auth_helper.php';
AuthHelper::requireAdmin();

// Set up error logging to file instead of displaying
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/inventory_errors.log');

// Include database configuration (absolute path)
require_once dirname(__DIR__) . '/api/config.php';

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
        $item_sku = trim($_POST['sku']);
        $field = trim($_POST['field']);
        $value = $_POST['value'];

        // Validate field name
        $allowedFields = ['name', 'sku', 'stock_quantity', 'reorder_point', 'cost_price', 'retail_price', 'description', 'image_url'];
        if (!in_array($field, $allowedFields)) {
            returnError('Invalid field name');
        }

        // Basic validation
        if (empty($item_sku)) {
            returnError('Item SKU cannot be empty');
        }

        // Prepare and execute update - use items table with sku as primary key
        $query = "UPDATE items SET $field = ? WHERE sku = ?";
        $affected = Database::execute($query, [$value, $item_sku]);
        if ($affected !== false) {
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
            $requiredFields = ['name', 'stock_quantity', 'reorder_point', 'cost_price', 'retail_price'];
            if ($action === 'update') {
                $requiredFields[] = 'item_sku';
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
                $rowSku = Database::queryOne("SELECT sku FROM items WHERE sku LIKE ? ORDER BY sku DESC LIMIT 1", ['WF-' . $code . '-%']);
                $num = 1;
                if ($rowSku && preg_match('/WF-'.$code.'-(\d{3})$/', $rowSku['sku'], $m)) {
                    $num = intval($m[1]) + 1;
                }
                $sku = 'WF-'.$code.'-'.str_pad($num, 3, '0', STR_PAD_LEFT);
            }
            $stock_quantity = intval($_POST['stock_quantity']);
            $reorder_point = intval($_POST['reorder_point']);
            $cost_price = floatval($_POST['cost_price']);
            $retail_price = floatval($_POST['retail_price']);
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $image_url = isset($_POST['existingImageUrl']) ? trim($_POST['existingImageUrl']) : '';

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
                        $image_url = $destRel;
                    } else {
                        error_log('move_uploaded_file failed from '.$tmpPath.' to '.$destAbs);
                    }
                }
            }

            if ($action === 'add') {
                // Insert query - items table now uses sku as primary key
                $sql = "INSERT INTO items (sku, name, category, stock_quantity, reorder_point, cost_price, retail_price, description, image_url) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $affected = Database::execute($sql, [
                    $sku, $name, $category, $stock_quantity, $reorder_point,
                    $cost_price, $retail_price, $description, $image_url
                ]);
                $success = ($affected !== false);
                $id = $sku; // For consistency with return data
            } else {
                // Update existing item - use item_sku (original SKU) for WHERE clause
                $originalSku = trim($_POST['item_sku']);

                // Update query - use sku as primary key
                $sql = "UPDATE items SET 
                        name = ?, category = ?, sku = ?, stock_quantity = ?, 
                        reorder_point = ?, cost_price = ?, retail_price = ?, description = ?, image_url = ? 
                        WHERE sku = ?";
                $affected = Database::execute($sql, [
                    $name, $category, $sku, $stock_quantity, $reorder_point,
                    $cost_price, $retail_price, $description, $image_url, $originalSku
                ]);
                $success = ($affected !== false);
                $item_sku = $sku; // Use the new SKU for response
            }

            // Check if operation was successful
            if ($success) {
                // No need to sync with items table anymore - items table is the single source of truth

                // TEMP LOG
                $logId = ($action === 'add') ? $sku : $item_sku;
                error_log("[inventory_update] action=$action item_sku=$logId image_url=$image_url\n", 3, __DIR__ . '/inventory_errors.log');

                $message = "Item " . ($action === 'add' ? "added" : "updated") . " successfully";
                $returnId = ($action === 'add') ? $sku : $item_sku;
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

        if (isset($data['sku']) && isset($data['cost_price'])) {
            $item_sku = trim($data['sku']);
            $cost_price = floatval($data['cost_price']);

            $query = "UPDATE items SET cost_price = ? WHERE sku = ?";
            $affected = Database::execute($query, [$cost_price, $item_sku]);

            if ($affected !== false) {
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

        $action = $params['action'] ?? '';
        $item_sku = isset($params['sku']) ? trim($params['sku']) : '';

        if ($item_sku === '') {
            returnError('Missing SKU for delete request');
        }

        require_once dirname(__DIR__) . '/includes/inventory_helper.php';
        InventoryHelper::ensureArchiveColumns();

        switch ($action) {
            case 'delete':
            case 'archive':
                $actor = InventoryHelper::defaultActor();
                if (InventoryHelper::softDeleteItem($item_sku, $actor)) {
                    returnSuccess('Item archived successfully');
                }
                returnError('Failed to archive item');

            case 'restore':
                if (InventoryHelper::restoreItem($item_sku)) {
                    returnSuccess('Item restored successfully');
                }
                returnError('Failed to restore item');

            case 'nuke':
            case 'delete_forever':
                $orderRefs = Database::queryOne(
                    'SELECT COUNT(*) AS cnt FROM order_items WHERE sku = ?',
                    [$item_sku]
                );
                if ((int) ($orderRefs['cnt'] ?? 0) > 0) {
                    returnError(
                        'Cannot permanently delete this item because it appears in order history. Keep it archived to preserve records.',
                        409
                    );
                }

                // Only allow hard delete once item is archived to avoid accidental data loss.
                if (InventoryHelper::hardDeleteItem($item_sku, true)) {
                    returnSuccess('Item permanently deleted');
                }
                returnError('Failed to permanently delete item');

            default:
                returnError('Invalid delete request');
        }
    } else {
        returnError('Invalid request method or missing parameters');
    }
} catch (PDOException $e) {
    error_log("Database error in process_inventory_update.php: " . $e->getMessage());
    if ((string) $e->getCode() === '23000') {
        returnError('Cannot permanently delete this item because related records still reference it.', 409);
    }
    returnError('Database error occurred. Please check server logs.', 500);
} catch (Exception $e) {
    error_log("General error in process_inventory_update.php: " . $e->getMessage());
    returnError('An unexpected error occurred. Please check server logs.', 500);
}

// Clear any remaining output buffer before script ends
if (ob_get_length()) {
    ob_end_clean();
}
