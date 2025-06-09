<?php
// process_inventory_update.php

// Start output buffering immediately to catch any stray output or errors
ob_start();

// Set error reporting for debugging (should be off or logged to file in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set CORS headers for AJAX requests
header('Access-Control-Allow-Origin: *'); // Adjust to specific domains in production
header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_flush(); // Send buffered content (should be none) and turn off buffering
    exit;
}

// Include database configuration
// Ensure config.php does not output anything (no whitespace outside <?php ... ?> tags, no direct echos)
require_once __DIR__ . '/api/config.php';

// Debug log function - only logs in development environment
function debugLog($message, $data = null) {
    global $isLocalhost; // Assumes $isLocalhost is defined in config.php
    if ($isLocalhost) {
        $logEntry = "AJAX DEBUG (" . date("Y-m-d H:i:s") . "): " . $message;
        if ($data !== null) {
            $logEntry .= " - Data: " . json_encode($data, JSON_PRETTY_PRINT);
        }
        error_log($logEntry);
    }
}

// Enhanced AJAX detection function
function isAjaxRequest() {
    global $isLocalhost; // For debugLog
    $ajax_detected = false;
    $detection_reason = "No AJAX indicators found.";
    $headers_checked = [
        'HTTP_X_REQUESTED_WITH' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null,
        'HTTP_ACCEPT' => $_SERVER['HTTP_ACCEPT'] ?? null,
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? null,
    ];

    if (isset($headers_checked['HTTP_X_REQUESTED_WITH']) && strtolower($headers_checked['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $ajax_detected = true;
        $detection_reason = "X-Requested-With header is 'xmlhttprequest'.";
    } elseif (isset($headers_checked['HTTP_ACCEPT']) && stripos($headers_checked['HTTP_ACCEPT'], 'application/json') !== false) {
        $ajax_detected = true;
        $detection_reason = "Accept header contains 'application/json'.";
    } elseif (isset($headers_checked['CONTENT_TYPE']) && stripos($headers_checked['CONTENT_TYPE'], 'application/json') !== false) {
        $ajax_detected = true;
        $detection_reason = "Content-Type header is 'application/json'.";
    }

    if ($isLocalhost) {
        debugLog("AJAX Detection Check:", [
            'Headers' => $headers_checked,
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? null,
            'Detected_As_AJAX' => $ajax_detected,
            'Reason' => $detection_reason
        ]);
    }
    return $ajax_detected;
}

$isAjax = isAjaxRequest();

debugLog("Request Received in process_inventory_update.php", [
    'IS_AJAX' => $isAjax,
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
    'GET_PARAMS' => $_GET,
    'POST_PARAMS' => $_POST, // Note: For FormData, $_POST is populated. For raw JSON, it's not.
    'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'Not set'
]);

// Helper function to return JSON success response
function returnJsonSuccess($message, $data = null, $statusCode = 200) {
    debugLog("Attempting to send JSON Success", ['message' => $message, 'data' => $data, 'statusCode' => $statusCode]);
    if (ob_get_level() > 0) ob_clean(); // Clean buffer before sending JSON
    http_response_code($statusCode);
    header('Content-Type: application/json');
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    ob_end_flush();
    exit;
}

// Helper function to return JSON error response
function returnJsonError($message, $statusCode = 400, $errors = null) {
    debugLog("Attempting to send JSON Error", ['message' => $message, 'statusCode' => $statusCode, 'errors' => $errors]);
    if (ob_get_level() > 0) ob_clean(); // Clean buffer before sending JSON
    http_response_code($statusCode);
    header('Content-Type: application/json');
    $response = ['success' => false, 'error' => $message];
    if ($errors !== null) {
        $response['errors'] = $errors;
    }
    echo json_encode($response);
    ob_end_flush();
    exit;
}

// Check for JSON input if Content-Type indicates it
$inputData = [];
$jsonInput = false;
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if (stripos($contentType, 'application/json') !== false) {
    $jsonInput = true;
    $rawInput = file_get_contents('php://input');
    debugLog("Raw JSON input received", $rawInput);
    $inputData = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        debugLog("JSON parse error: " . json_last_error_msg());
        returnJsonError('Invalid JSON format: ' . json_last_error_msg(), 400);
    }
    debugLog("Parsed JSON input", $inputData);
}


try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    debugLog("Database connection established successfully.");

    $action = null;
    if ($jsonInput && isset($inputData['action'])) {
        $action = $inputData['action'];
    } elseif (isset($_POST['action'])) {
        $action = $_POST['action'];
    } elseif (isset($_GET['action'])) { // Less common for updates, but possible
        $action = $_GET['action'];
    }
    
    // Consolidate itemId source
    $itemId = null;
    if ($jsonInput && isset($inputData['itemId'])) {
        $itemId = trim($inputData['itemId']);
    } elseif (isset($_POST['itemId'])) {
        $itemId = trim($_POST['itemId']);
    } elseif ($jsonInput && isset($inputData['id'])) { // For cost price updates from suggested cost
         $itemId = trim($inputData['id']);
    }


    // Inline editing (typically AJAX with specific field from FormData)
    if ($isAjax && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['itemId']) && isset($_POST['field']) && isset($_POST['value'])) {
        debugLog("Processing INLINE EDIT request", $_POST);
        $itemId = trim($_POST['itemId']);
        $field = trim($_POST['field']);
        $value = $_POST['value']; // Raw value, validation below

        if (empty($itemId)) returnJsonError('Item ID cannot be empty for inline edit.', 400);
        
        $allowedFields = ['name', 'category', 'sku', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice', 'description', 'imageUrl'];
        if (!in_array($field, $allowedFields)) {
            returnJsonError('Invalid field name for inline edit: ' . htmlspecialchars($field), 400);
        }

        // Validate input based on field type
        switch ($field) {
            case 'stockLevel':
            case 'reorderPoint':
                if (!is_numeric($value) || intval($value) < 0) returnJsonError('Value for ' . $field . ' must be a non-negative integer.', 400);
                $value = intval($value);
                break;
            case 'costPrice':
            case 'retailPrice':
                if (!is_numeric($value) || floatval($value) < 0) returnJsonError('Price for ' . $field . ' must be a non-negative number.', 400);
                $value = floatval($value);
                break;
            default:
                $value = trim(strval($value)); // Ensure it's a string for text fields
                if (empty($value) && in_array($field, ['name', 'category', 'sku'])) {
                    returnJsonError(ucfirst($field) . ' cannot be empty.', 400);
                }
        }
        
        $checkStmt = $pdo->prepare("SELECT id FROM inventory WHERE id = ?");
        $checkStmt->execute([$itemId]);
        if (!$checkStmt->fetch()) {
            returnJsonError('Item not found with ID: ' . htmlspecialchars($itemId), 404);
        }

        $query = "UPDATE inventory SET $field = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        if ($stmt->execute([$value, $itemId])) {
            returnJsonSuccess(ucfirst($field) . ' updated successfully.');
        } else {
            returnJsonError('Failed to update ' . $field . ': ' . ($stmt->errorInfo()[2] ?? 'Unknown error'), 500);
        }
    }
    // Handle full item update via JSON (e.g., for cost price updates from suggested cost)
    else if ($jsonInput && isset($inputData['id']) && isset($inputData['costPrice'])) {
        debugLog("Processing JSON UPDATE for costPrice", $inputData);
        $itemIdToUpdate = trim($inputData['id']);
        
        if (!is_numeric($inputData['costPrice']) || floatval($inputData['costPrice']) < 0) {
            returnJsonError('Cost price must be a non-negative number.', 400);
        }
        
        $checkStmt = $pdo->prepare("SELECT id FROM inventory WHERE id = ?");
        $checkStmt->execute([$itemIdToUpdate]);
        if (!$checkStmt->fetch()) {
            returnJsonError('Item not found with ID: ' . htmlspecialchars($itemIdToUpdate), 404);
        }
        
        $costPrice = floatval($inputData['costPrice']);
        $query = "UPDATE inventory SET costPrice = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        
        if ($stmt->execute([$costPrice, $itemIdToUpdate])) {
            returnJsonSuccess('Cost price updated successfully for item ' . htmlspecialchars($itemIdToUpdate) . '.');
        } else {
            returnJsonError('Failed to update cost price: ' . ($stmt->errorInfo()[2] ?? 'Unknown error'), 500);
        }
    }
    // Handle traditional form submission (add or update, likely via AJAX FormData)
    else if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'update')) {
        debugLog("Processing FORM SUBMISSION (action: $action)", $_POST);
        
        $requiredFields = ['name', 'category', 'sku', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice'];
        if ($action === 'update') $requiredFields[] = 'itemId'; // itemId is required for update

        $errors = [];
        $field_errors = [];

        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field]) || ($_POST[$field] === '' && $_POST[$field] !== '0')) { // Allow '0'
                $errors[] = ucfirst($field) . " is required.";
                $field_errors[] = $field;
            }
        }

        // Specific validations
        if (isset($_POST['stockLevel']) && (!is_numeric($_POST['stockLevel']) || intval($_POST['stockLevel']) < 0)) { $errors[] = "Stock level must be a non-negative integer."; $field_errors[] = 'stockLevel';}
        if (isset($_POST['reorderPoint']) && (!is_numeric($_POST['reorderPoint']) || intval($_POST['reorderPoint']) < 0)) { $errors[] = "Reorder point must be a non-negative integer."; $field_errors[] = 'reorderPoint';}
        if (isset($_POST['costPrice']) && (!is_numeric($_POST['costPrice']) || floatval($_POST['costPrice']) < 0)) { $errors[] = "Cost price must be a non-negative number."; $field_errors[] = 'costPrice';}
        if (isset($_POST['retailPrice']) && (!is_numeric($_POST['retailPrice']) || floatval($_POST['retailPrice']) < 0)) { $errors[] = "Retail price must be a non-negative number."; $field_errors[] = 'retailPrice';}

        if (!empty($errors)) {
            debugLog("Validation errors on form submission", ['errors' => $errors, 'field_errors' => $field_errors]);
            if ($isAjax) {
                returnJsonError("Validation failed.", 400, ['messages' => $errors, 'fields' => array_unique($field_errors)]);
            } else {
                // This path should ideally not be hit if client-side always uses AJAX
                $errorQuery = http_build_query(['message' => implode("<br>", $errors), 'type' => 'error']);
                header("Location: ?page=admin&section=inventory&$errorQuery");
                exit;
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
        $productId = isset($_POST['productId']) ? trim($_POST['productId']) : ''; // May be empty for new items

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

            $sql = "INSERT INTO inventory (id, productId, name, category, sku, stockLevel, reorderPoint, costPrice, retailPrice, description, imageUrl) 
                    VALUES (:id, :productId, :name, :category, :sku, :stockLevel, :reorderPoint, :costPrice, :retailPrice, :description, :imageUrl)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $itemId);
        } else { // action === 'update'
            $itemId = trim($_POST['itemId']); // Already validated to be present
            // For update, productId might be empty if it was auto-generated and not editable, or it might be submitted
            if (empty($productId) && isset($_POST['productId_hidden_for_update'])) { // Assuming a hidden field if it's not directly editable
                $productId = trim($_POST['productId_hidden_for_update']);
            }

            $sql = "UPDATE inventory SET productId = :productId, name = :name, category = :category, sku = :sku, 
                    stockLevel = :stockLevel, reorderPoint = :reorderPoint, costPrice = :costPrice, retailPrice = :retailPrice, 
                    description = :description, imageUrl = :imageUrl WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $itemId);
        }
        
        $stmt->bindParam(':productId', $productId);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':sku', $sku);
        $stmt->bindParam(':stockLevel', $stockLevel, PDO::PARAM_INT);
        $stmt->bindParam(':reorderPoint', $reorderPoint, PDO::PARAM_INT);
        $stmt->bindParam(':costPrice', $costPrice); // PDO handles float correctly with PARAM_STR or type inference
        $stmt->bindParam(':retailPrice', $retailPrice);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':imageUrl', $imageUrl);

        if ($stmt->execute()) {
            $successMessage = "Item " . ($action === 'add' ? "added" : "updated") . " successfully.";
            debugLog($successMessage, ['itemId' => $itemId, 'action' => $action]);
            if ($isAjax) {
                returnJsonSuccess($successMessage, ['itemId' => $itemId, 'action' => $action, 'productId' => $productId]);
            } else {
                $successQuery = http_build_query(['message' => $successMessage, 'type' => 'success', 'edit' => $itemId]);
                header("Location: ?page=admin&section=inventory&$successQuery");
                exit;
            }
        } else {
            $errorInfo = $stmt->errorInfo();
            $dbErrorMsg = 'Failed to ' . $action . ' item: ' . ($errorInfo[2] ?? 'Unknown database error');
            debugLog("Database operation error on $action", ['errorInfo' => $errorInfo, 'itemId' => $itemId]);
            if ($isAjax) {
                returnJsonError($dbErrorMsg, 500);
            } else {
                $errorQuery = http_build_query(['message' => $dbErrorMsg, 'type' => 'error']);
                header("Location: ?page=admin&section=inventory&$errorQuery");
                exit;
            }
        }
    }
    // No valid action or parameters matched
    else {
        debugLog("Invalid request or missing parameters.", [
            'METHOD' => $_SERVER['REQUEST_METHOD'], 
            'ACTION' => $action ?? 'Not set',
            'POST' => $_POST, 
            'GET' => $_GET,
            'JSON_INPUT' => $inputData
        ]);
        if ($isAjax) {
            returnJsonError('Invalid request. Missing required action or parameters.', 400);
        } else {
            // Fallback for non-AJAX, though ideally all interactions are AJAX
            header("Location: ?page=admin&section=inventory&message=Invalid request&type=error");
            exit;
        }
    }

} catch (PDOException $e) {
    $dbError = 'Database error: ' . $e->getMessage();
    debugLog("PDOException caught", ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    if ($isAjax) {
        returnJsonError($dbError, 500);
    } else {
        header("Location: ?page=admin&section=inventory&message=" . urlencode($dbError) . "&type=error");
        exit;
    }
} catch (Exception $e) {
    $generalError = 'An unexpected error occurred: ' . $e->getMessage();
    debugLog("General Exception caught", ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    if ($isAjax) {
        returnJsonError($generalError, 500);
    } else {
        header("Location: ?page=admin&section=inventory&message=" . urlencode($generalError) . "&type=error");
        exit;
    }
}

// If script reaches here, it means an AJAX path wasn't properly handled or exited.
// This is a fallback to ensure *something* is sent if $isAjax was true but no JSON response was triggered.
if ($isAjax) {
    debugLog("FALLBACK: AJAX request reached end of script without explicit JSON response.");
    returnJsonError("An unexpected issue occurred. AJAX request was not fully processed.", 500);
} else {
    // For non-AJAX, if it reaches here, it's likely an unhandled case.
    // A redirect might have been missed or it's an unexpected direct access.
    debugLog("FALLBACK: Non-AJAX request reached end of script. Redirecting to inventory page.");
    header("Location: ?page=admin&section=inventory&message=Unexpected access to processing script&type=error");
    exit;
}

// Ensure output buffer is flushed at the very end if not already handled by exit in JSON functions
ob_end_flush();
?>
