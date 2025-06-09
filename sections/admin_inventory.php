<?php
// sections/admin_inventory.php

// Start output buffering at the very beginning
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Admin authentication check is now handled in index.php before this script is included.

// Database Connection
require_once __DIR__ . '/../api/config.php'; // Use the centralized config

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Error: Could not connect to the database. Please try again later.");
}


$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$debug_messages = []; // For collecting debug messages for AJAX responses

// Helper function to calculate cost breakdown totals
function calculateCostBreakdownTotals($pdo, $inventoryId) {
    $totals = [
        'materialTotal' => 0,
        'laborTotal' => 0,
        'energyTotal' => 0,
        'equipmentTotal' => 0,
        'suggestedCost' => 0
    ];

    $costTypes = ['materials', 'labor', 'energy', 'equipment'];
    foreach ($costTypes as $type) {
        $stmt = $pdo->prepare("SELECT SUM(cost) as total FROM inventory_" . $type . " WHERE inventoryId = :inventoryId");
        $stmt->bindParam(':inventoryId', $inventoryId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && $result['total']) {
            $key = substr($type, 0, -1) . 'Total'; // materialTotal, laborTotal etc.
            $totals[$key] = floatval($result['total']);
        }
    }
    $totals['suggestedCost'] = $totals['materialTotal'] + $totals['laborTotal'] + $totals['energyTotal'] + $totals['equipmentTotal'];
    return $totals;
}


// Handle Add/Update/Delete operations
$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? '';

// This block handles POST requests if the form were submitted directly to this page (admin_inventory.php)
// However, with AJAX submitting to process_inventory_update.php, this block might become less relevant for form submissions
// but could still be used if there are non-AJAX ways to trigger these actions or for initial page load messages.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && ($_POST['action'] === 'add' || $_POST['action'] === 'update')) {
        $action = $_POST['action'];
        $itemId = $_POST['itemId'] ?? null;

        $requiredFields = ['name', 'category', 'sku', 'stockLevel', 'reorderPoint', 'costPrice', 'retailPrice'];
        $errors = [];
        $field_errors = []; // For highlighting specific fields

        foreach ($requiredFields as $field) {
            if (empty($_POST[$field]) && $_POST[$field] !== '0') { // Allow '0' as a valid value
                $errors[] = ucfirst($field) . " is required.";
                $field_errors[] = $field;
            }
        }
        // Specific validation for numeric fields
        if (!isset($_POST['stockLevel']) || !is_numeric($_POST['stockLevel']) || (int)$_POST['stockLevel'] < 0) { $errors[] = "Stock level must be a non-negative number."; $field_errors[] = 'stockLevel';}
        if (!isset($_POST['reorderPoint']) || !is_numeric($_POST['reorderPoint']) || (int)$_POST['reorderPoint'] < 0) { $errors[] = "Reorder point must be a non-negative number."; $field_errors[] = 'reorderPoint';}
        if (!isset($_POST['costPrice']) || !is_numeric($_POST['costPrice']) || (float)$_POST['costPrice'] < 0) { $errors[] = "Cost price must be a non-negative number."; $field_errors[] = 'costPrice';}
        if (!isset($_POST['retailPrice']) || !is_numeric($_POST['retailPrice']) || (float)$_POST['retailPrice'] < 0) { $errors[] = "Retail price must be a non-negative number."; $field_errors[] = 'retailPrice';}


        if (!empty($errors)) {
            if ($isAjaxRequest) { // This $isAjaxRequest check might not be hit if form goes to process_inventory_update.php
                ob_end_clean();
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Validation failed.', 'errors' => $errors, 'field_errors' => array_unique($field_errors)]);
                exit;
            }
            $message = implode("<br>", $errors);
            $messageType = 'error';
        } else {
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
            $sku = filter_input(INPUT_POST, 'sku', FILTER_SANITIZE_STRING);
            $stockLevel = filter_input(INPUT_POST, 'stockLevel', FILTER_VALIDATE_INT);
            $reorderPoint = filter_input(INPUT_POST, 'reorderPoint', FILTER_VALIDATE_INT);
            $costPrice = filter_input(INPUT_POST, 'costPrice', FILTER_VALIDATE_FLOAT);
            $retailPrice = filter_input(INPUT_POST, 'retailPrice', FILTER_VALIDATE_FLOAT);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
            $productId = filter_input(INPUT_POST, 'productId', FILTER_SANITIZE_STRING);
            $imageUrl = filter_input(INPUT_POST, 'imageUrl', FILTER_SANITIZE_URL);

            try {
                if ($action === 'add') {
                    $stmt = $pdo->query("SELECT id FROM inventory ORDER BY CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC LIMIT 1");
                    $lastIdRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    $lastIdNum = $lastIdRow ? (int)substr($lastIdRow['id'], 1) : 0;
                    $newNumericId = $lastIdNum + 1;
                    $itemId = 'I' . str_pad($newNumericId, 3, '0', STR_PAD_LEFT);

                    if (empty($productId)) {
                        $stmtProd = $pdo->query("SELECT productId FROM inventory ORDER BY CAST(SUBSTRING(productId, 2) AS UNSIGNED) DESC LIMIT 1");
                        $lastProdIdRow = $stmtProd->fetch(PDO::FETCH_ASSOC);
                        $lastProdIdNum = $lastProdIdRow ? (int)substr($lastProdIdRow['productId'], 1) : 0;
                        $newNumericProdId = $lastProdIdNum + 1;
                        $productId = 'P' . str_pad($newNumericProdId, 3, '0', STR_PAD_LEFT);
                    }

                    $stmt = $pdo->prepare("INSERT INTO inventory (id, productId, name, category, sku, stockLevel, reorderPoint, costPrice, retailPrice, description, imageUrl) VALUES (:id, :productId, :name, :category, :sku, :stockLevel, :reorderPoint, :costPrice, :retailPrice, :description, :imageUrl)");
                    $stmt->bindParam(':id', $itemId, PDO::PARAM_STR);
                } else { // 'update'
                    $stmt = $pdo->prepare("UPDATE inventory SET productId = :productId, name = :name, category = :category, sku = :sku, stockLevel = :stockLevel, reorderPoint = :reorderPoint, costPrice = :costPrice, retailPrice = :retailPrice, description = :description, imageUrl = :imageUrl WHERE id = :id");
                    $stmt->bindParam(':id', $itemId, PDO::PARAM_STR);
                }

                $stmt->bindParam(':productId', $productId, PDO::PARAM_STR);
                $stmt->bindParam(':name', $name, PDO::PARAM_STR);
                $stmt->bindParam(':category', $category, PDO::PARAM_STR);
                $stmt->bindParam(':sku', $sku, PDO::PARAM_STR);
                $stmt->bindParam(':stockLevel', $stockLevel, PDO::PARAM_INT);
                $stmt->bindParam(':reorderPoint', $reorderPoint, PDO::PARAM_INT);
                $stmt->bindParam(':costPrice', $costPrice, PDO::PARAM_STR); // PDO::PARAM_STR for float
                $stmt->bindParam(':retailPrice', $retailPrice, PDO::PARAM_STR); // PDO::PARAM_STR for float
                $stmt->bindParam(':description', $description, PDO::PARAM_STR);
                $stmt->bindParam(':imageUrl', $imageUrl, PDO::PARAM_STR);
                $stmt->execute();

                $message = "Inventory item " . ($action === 'add' ? "added" : "updated") . " successfully.";
                $messageType = 'success';

                // This AJAX response part will not be hit if form action points to process_inventory_update.php
                if ($isAjaxRequest) {
                    ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => $message, 'action' => $action, 'itemId' => $itemId]);
                    exit;
                }
                // Redirect for non-AJAX form submissions to this page
                $redirectUrl = "?page=admin&section=inventory&message=" . urlencode($message) . "&type=" . $messageType;
                if ($action === 'update' || $action === 'add') {
                     $redirectUrl .= "&edit=" . $itemId; // Keep user in modal with new/updated item
                }
                header('Location: ' . $redirectUrl);
                exit;

            } catch (PDOException $e) {
                error_log("Database operation error: " . $e->getMessage());
                $message = "Error " . ($action === 'add' ? "adding" : "updating") . " item: " . $e->getMessage();
                $messageType = 'error';
                if ($isAjaxRequest) { // Not hit if form action is different
                    ob_end_clean();
                    header('Content-Type: application/json');
                    http_response_code(500);
                    echo json_encode(['success' => false, 'error' => 'Database operation failed.', 'details' => $e->getMessage()]);
                    exit;
                }
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete'])) {
    $idToDelete = $_GET['delete'];
    try {
        $pdo->beginTransaction();
        $costTables = ['inventory_materials', 'inventory_labor', 'inventory_energy', 'inventory_equipment'];
        foreach ($costTables as $table) {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE inventoryId = :inventoryId");
            $stmt->bindParam(':inventoryId', $idToDelete, PDO::PARAM_STR);
            $stmt->execute();
        }
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = :id");
        $stmt->bindParam(':id', $idToDelete, PDO::PARAM_STR);
        $stmt->execute();
        $pdo->commit();
        $message = "Item $idToDelete and its associated costs deleted successfully.";
        $messageType = 'success';
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting item $idToDelete: " . $e->getMessage());
        $message = "Error deleting item: " . $e->getMessage();
        $messageType = 'error';
    }
    header('Location: ?page=admin&section=inventory&message=' . urlencode($message) . '&type=' . $messageType);
    exit;
}


// Fetch inventory data for display
$search = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$stockFilter = $_GET['stock'] ?? '';

$query = "SELECT i.* FROM inventory i WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (i.name LIKE :search OR i.sku LIKE :search OR i.category LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($categoryFilter)) {
    $query .= " AND i.category = :category";
    $params[':category'] = $categoryFilter;
}
if (!empty($stockFilter)) {
    if ($stockFilter === 'low') {
        $query .= " AND i.stockLevel <= i.reorderPoint AND i.stockLevel > 0";
    } elseif ($stockFilter === 'out') {
        $query .= " AND i.stockLevel = 0";
    } elseif ($stockFilter === 'in') {
        $query .= " AND i.stockLevel > 0";
    }
}
$query .= " ORDER BY i.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoryStmt = $pdo->query("SELECT DISTINCT category FROM inventory ORDER BY category ASC");
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

$totalItems = count($inventoryItems);
$lowStockItems = 0;
$totalCostValue = 0;
$totalRetailValue = 0;
foreach ($inventoryItems as $item) {
    if ($item['stockLevel'] <= $item['reorderPoint'] && $item['stockLevel'] > 0) {
        $lowStockItems++;
    }
    $totalCostValue += ($item['costPrice'] ?? 0) * ($item['stockLevel'] ?? 0);
    $totalRetailValue += ($item['retailPrice'] ?? 0) * ($item['stockLevel'] ?? 0);
}
$numCategories = count($categories);


$editItem = null;
$costBreakdownPHP = [
    'materials' => [], 'labor' => [], 'energy' => [], 'equipment' => [],
    'totals' => ['materialTotal' => 0, 'laborTotal' => 0, 'energyTotal' => 0, 'equipmentTotal' => 0, 'suggestedCost' => 0]
];
$modalMode = ''; // 'add' or 'edit'

if (isset($_GET['edit'])) {
    $modalMode = 'edit';
    $editId = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = :id");
    $stmt->bindParam(':id', $editId, PDO::PARAM_STR);
    $stmt->execute();
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($editItem) {
        $costTypes = ['materials', 'labor', 'energy', 'equipment'];
        foreach ($costTypes as $type) {
            $stmt = $pdo->prepare("SELECT * FROM inventory_" . $type . " WHERE inventoryId = :inventoryId ORDER BY id ASC");
            $stmt->bindParam(':inventoryId', $editId, PDO::PARAM_STR);
            $stmt->execute();
            $costBreakdownPHP[$type] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        $costBreakdownPHP['totals'] = calculateCostBreakdownTotals($pdo, $editId);
    } else {
        $message = "Error: Item with ID $editId not found for editing.";
        $messageType = 'error';
        unset($_GET['edit']); // Clear the edit parameter to prevent modal from trying to open
        $modalMode = ''; // Reset modal mode
    }
} elseif (isset($_GET['add'])) {
    $modalMode = 'add';
    // Default values for a new item
    $editItem = [
        'id' => '', // Will be generated on save
        'productId' => '', // Will be generated on save if not provided
        'name' => '', 'category' => '', 'sku' => '',
        'stockLevel' => 0, 'reorderPoint' => 5,
        'costPrice' => 0.00, 'retailPrice' => 0.00,
        'description' => '', 'imageUrl' => 'images/placeholder.png'
    ];
    // For a new item, cost breakdown is empty initially
    $costBreakdownPHP = [
        'materials' => [], 'labor' => [], 'energy' => [], 'equipment' => [],
        'totals' => ['materialTotal' => 0, 'laborTotal' => 0, 'energyTotal' => 0, 'equipmentTotal' => 0, 'suggestedCost' => 0]
    ];
}

// Display toast messages passed via URL (e.g., after a redirect)
if (!empty($message) && !$isAjaxRequest) {
    echo "<script>\n        document.addEventListener('DOMContentLoaded', function() {\n            showToast('" . addslashes($message) . "', '" . addslashes($messageType) . "');\n        });\n    </script>";
}
?>

<div class="container mx-auto px-4 py-8">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Total Items</h3>
            <p class="text-2xl font-bold text-green-700"><?php echo $totalItems; ?></p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Low Stock</h3>
            <p class="text-2xl font-bold text-orange-500"><?php echo $lowStockItems; ?></p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Categories</h3>
            <p class="text-2xl font-bold text-blue-500"><?php echo $numCategories; ?></p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Total Cost Value</h3>
            <p class="text-2xl font-bold text-purple-700">$<?php echo number_format($totalCostValue, 2); ?></p>
        </div>
        <div class="bg-white p-3 rounded-lg shadow-md text-center">
            <h3 class="text-sm font-semibold text-gray-500 mb-1">Total Retail Value</h3>
            <p class="text-2xl font-bold text-indigo-700">$<?php echo number_format($totalRetailValue, 2); ?></p>
        </div>
    </div>

    <style>
        .toast-notification {
            position: fixed; top: 20px; right: 20px; padding: 12px 20px;
            border-radius: 4px; color: white; font-weight: 500; z-index: 9999;
            opacity: 0; transform: translateY(-20px); box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: opacity 0.3s, transform 0.3s;
        }
        .toast-notification.show { opacity: 1; transform: translateY(0); }
        .toast-notification.success { background-color: #48bb78; }
        .toast-notification.error { background-color: #f56565; }
        .inventory-table { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 20px; }
        .inventory-table th { background-color: #87ac3a; color: white; padding: 12px; text-align: left; font-weight: 600; position: sticky; top: 0; z-index: 10; }
        .inventory-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .inventory-table tr:hover { background-color: #f7fafc; }
        .inventory-table th:first-child { border-top-left-radius: 8px; }
        .inventory-table th:last-child { border-top-right-radius: 8px; }
        .action-btn { padding: 6px 12px; border-radius: 4px; cursor: pointer; margin-right: 4px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 16px; }
        .edit-btn { background-color: #4299e1; color: white; } .edit-btn:hover { background-color: #3182ce; }
        .delete-btn { background-color: #f56565; color: white; } .delete-btn:hover { background-color: #e53e3e; }
        .cost-breakdown { background-color: #f9fafb; border-radius: 8px; padding: 12px; border: 1px solid #e2e8f0; }
        .cost-breakdown h3 { color: #374151; font-size: 16px; font-weight: 600; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #d1d5db; }
        .cost-breakdown-section h4 { color: #4b5563; font-size: 14px; font-weight: 600; margin-bottom: 6px; }
        .cost-item { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px dashed #e5e7eb; font-size: 13px; }
        .cost-item:last-child { border-bottom: none; }
        .cost-item-name { font-weight: 500; color: #374151; flex-grow: 1; margin-right: 8px; word-break: break-word; }
        .cost-item-value { font-weight: 600; color: #1f2937; white-space: nowrap; }
        .cost-totals { background-color: #f3f4f6; padding: 10px; border-radius: 6px; margin-top: 12px; font-size: 13px; }
        .cost-total-row { display: flex; justify-content: space-between; padding: 3px 0; }
        .cost-label { font-size: 13px; color: #6b7280; }
        .modal-outer { position: fixed; inset: 0; background-color: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 50; }
        .modal-content-wrapper { background-color: white; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); padding: 1.5rem; width: 100%; max-width: 64rem; max-height: 90vh; display: flex; flex-direction: column; }
        .modal-form-container { flex-grow: 1; overflow-y: auto; display: flex; flex-direction: column; padding-right: 0.5rem; }
        @media (min-width: 768px) { .modal-form-container { flex-direction: row; } }
        .modal-form-main-column { flex: 1; padding-right: 0.75rem; }
        @media (max-width: 767px) { .modal-form-main-column { padding-right: 0; } }
        .modal-form-cost-column { width: 100%; padding-left: 0; margin-top: 1.5rem; }
        @media (min-width: 768px) { .modal-form-cost-column { flex: 0 0 40%; padding-left: 0.75rem; margin-top: 0; } }
        .editable { position: relative; padding: 6px 8px; border-radius: 4px; cursor: pointer; transition: all 0.2s; }
        .editable:hover { background-color: #edf2f7; }
        .editable:hover::after { content: "✏️"; position: absolute; right: 5px; top: 50%; transform: translateY(-50%); font-size: 12px; opacity: 0.5; }
        .editing { padding: 0 !important; background-color: #ebf8ff !important; }
        .editing input, .editing select { width: 100%; padding: 6px 8px; border: 2px solid #4299e1; border-radius: 4px; font-size: inherit; font-family: inherit; background-color: white; }
        .editing-buttons { position: absolute; right: 0; top: 100%; display: flex; z-index: 20; }
        .editing-buttons button { padding: 4px 8px; font-size: 12px; border: none; cursor: pointer; }
        .save-btn { background-color: #48bb78; color: white; border-radius: 0 0 0 4px; }
        .cancel-btn { background-color: #f56565; color: white; border-radius: 0 0 4px 0; }
        .loading-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: #fff; animation: spin 1s ease-in-out infinite; }
        .loading-spinner.dark { border: 2px solid rgba(0,0,0,0.1); border-top-color: #333; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes highlight { 0% { background-color: #c6f6d5; } 100% { background-color: transparent; } }
        .highlight { animation: highlight 1.5s ease-out; }
        .field-error-highlight { border-color: #f56565 !important; box-shadow: 0 0 0 1px #f56565 !important; }
        .image-preview { position: relative; width: 100%; max-width: 200px; margin-bottom: 10px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .image-preview img { width: 100%; height: auto; display: block; }
        .image-upload-overlay { position: absolute; bottom: 0; left: 0; right: 0; background-color: rgba(0,0,0,0.6); padding: 8px; text-align: center; opacity: 0; transition: opacity 0.2s; }
        .image-preview:hover .image-upload-overlay { opacity: 1; }
        .image-upload-btn { background-color: #4299e1; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .image-upload-btn:hover { background-color: #3182ce; }
        .cost-item-actions { display: flex; align-items: center; margin-left: 8px; }
        .cost-edit-btn, .cost-delete-btn { padding: 2px; margin-left: 4px; border: none; background: none; border-radius: 3px; cursor: pointer; font-size: 11px; opacity: 0.7; transition: opacity 0.2s; }
        .cost-edit-btn { color: #4299e1; } .cost-delete-btn { color: #f56565; }
        .cost-edit-btn:hover, .cost-delete-btn:hover { opacity: 1; }
        .add-cost-btn { display: inline-flex; align-items: center; padding: 4px 8px; background-color: #edf2f7; border: 1px dashed #cbd5e0; border-radius: 4px; color: #4a5568; font-size: 12px; cursor: pointer; margin-top: 6px; transition: all 0.2s; }
        .add-cost-btn:hover { background-color: #e2e8f0; border-color: #a0aec0; }
        .add-cost-btn svg { width: 12px; height: 12px; margin-right: 4px; }
        .cost-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 100; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .cost-modal.show { opacity: 1; pointer-events: auto; }
        .cost-modal-content { background-color: white; border-radius: 8px; padding: 20px; width: 100%; max-width: 400px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transform: scale(0.9); transition: transform 0.3s; }
        .cost-modal.show .cost-modal-content { transform: scale(1); }
    </style>

    <form method="GET" action="" class="flex flex-col md:flex-row gap-4 mb-6">
        <input type="hidden" name="page" value="admin">
        <input type="hidden" name="section" value="inventory">
        <div class="flex-1">
            <input type="text" name="search" placeholder="Search inventory..." class="w-full p-2 border border-gray-300 rounded" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="flex-1">
            <select name="category" class="w-full p-2 border border-gray-300 rounded">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($categoryFilter === $cat) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1">
            <select name="stock" class="w-full p-2 border border-gray-300 rounded">
                <option value="">All Stock Levels</option>
                <option value="low" <?php echo ($stockFilter === 'low') ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out" <?php echo ($stockFilter === 'out') ? 'selected' : ''; ?>>Out of Stock</option>
                <option value="in" <?php echo ($stockFilter === 'in') ? 'selected' : ''; ?>>In Stock</option>
            </select>
        </div>
        <div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded">Filter</button>
        </div>
        <div>
            <a href="?page=admin&section=inventory&add=1" class="bg-green-600 hover:bg-green-700 text-white p-2 rounded inline-block text-center">Add New Item</a>
        </div>
    </form>

    <div class="overflow-x-auto bg-white rounded-lg shadow-md">
        <table class="inventory-table" id="inventoryTable">
            <thead>
                <tr>
                    <th>Name</th><th>Category</th><th>SKU</th><th>Stock</th>
                    <th>Reorder Point</th><th>Cost Price</th><th>Retail Price</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventoryItems)): ?>
                    <tr><td colspan="8" class="text-center py-4">No inventory items found.</td></tr>
                <?php else: ?>
                    <?php foreach ($inventoryItems as $item): ?>
                        <tr data-id="<?php echo htmlspecialchars($item['id']); ?>">
                            <td class="editable" data-field="name"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td class="editable" data-field="category"><?php echo htmlspecialchars($item['category']); ?></td>
                            <td><?php echo htmlspecialchars($item['sku']); ?></td>
                            <td class="editable" data-field="stockLevel"><?php echo htmlspecialchars($item['stockLevel']); ?></td>
                            <td class="editable" data-field="reorderPoint"><?php echo htmlspecialchars($item['reorderPoint']); ?></td>
                            <td class="editable" data-field="costPrice">$<?php echo number_format(floatval($item['costPrice'] ?? 0), 2); ?></td>
                            <td class="editable" data-field="retailPrice">$<?php echo number_format(floatval($item['retailPrice'] ?? 0), 2); ?></td>
                            <td>
                                <a href="?page=admin&section=inventory&edit=<?php echo htmlspecialchars($item['id']); ?>" class="action-btn edit-btn" title="Edit Item">✏️</a>
                                <a href="?page=admin&section=inventory&delete=<?php echo htmlspecialchars($item['id']); ?>" class="action-btn delete-btn" title="Delete Item" onclick="return confirm('Are you sure you want to delete this item \'<?php echo htmlspecialchars(addslashes($item['name'])); ?>\'?')">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($modalMode === 'edit' && $editItem || $modalMode === 'add'): ?>
    <div id="inventoryModal" class="modal-outer" style="display: flex;">
        <div class="modal-content-wrapper">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-green-700"><?php echo $modalMode === 'add' ? 'Add New Item' : 'Edit Item (' . htmlspecialchars($editItem['name'] ?? 'N/A') . ')'; ?></h2>
                <a href="?page=admin&section=inventory" class="text-gray-500 hover:text-gray-700 text-2xl leading-none">&times;</a>
            </div>

            <form id="inventoryForm" method="POST" action="/process_inventory_update.php" class="flex flex-col flex-grow overflow-hidden">
                <input type="hidden" name="action" value="<?php echo $modalMode === 'add' ? 'add' : 'update'; ?>">
                <?php if ($modalMode === 'edit' && isset($editItem['id'])): ?>
                    <input type="hidden" name="itemId" value="<?php echo htmlspecialchars($editItem['id']); ?>">
                <?php endif; ?>

                <div class="modal-form-container gap-6">
                    <div class="modal-form-main-column space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="productId" class="block text-sm font-medium text-gray-700">Product ID</label>
                                <input type="text" id="productId" name="productId" class="mt-1 block w-full p-2 border border-gray-300 rounded bg-gray-100" readonly value="<?php echo htmlspecialchars($editItem['productId'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                                <input type="text" id="name" name="name" class="mt-1 block w-full p-2 border border-gray-300 rounded" required value="<?php echo htmlspecialchars($editItem['name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                                <input type="text" id="category" name="category" class="mt-1 block w-full p-2 border border-gray-300 rounded" required value="<?php echo htmlspecialchars($editItem['category'] ?? ''); ?>">
                            </div>
                            <div>
                                <label for="sku" class="block text-sm font-medium text-gray-700">SKU</label>
                                <input type="text" id="sku" name="sku" class="mt-1 block w-full p-2 border border-gray-300 rounded" required value="<?php echo htmlspecialchars($editItem['sku'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="stockLevel" class="block text-sm font-medium text-gray-700">Stock Level</label>
                                <input type="number" id="stockLevel" name="stockLevel" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" required value="<?php echo htmlspecialchars($editItem['stockLevel'] ?? '0'); ?>">
                            </div>
                            <div>
                                <label for="reorderPoint" class="block text-sm font-medium text-gray-700">Reorder Point</label>
                                <input type="number" id="reorderPoint" name="reorderPoint" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" required value="<?php echo htmlspecialchars($editItem['reorderPoint'] ?? '5'); ?>">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="costPrice" class="block text-sm font-medium text-gray-700">Cost Price ($)</label>
                                <div class="flex items-center">
                                    <input type="number" id="costPrice" name="costPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" step="0.01" required value="<?php echo number_format(floatval($editItem['costPrice'] ?? 0), 2, '.', ''); ?>">
                                    <span class="suggested-cost ml-2 text-xs text-gray-500"></span>
                                </div>
                            </div>
                            <div>
                                <label for="retailPrice" class="block text-sm font-medium text-gray-700">Retail Price ($)</label>
                                <input type="number" id="retailPrice" name="retailPrice" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" step="0.01" required value="<?php echo number_format(floatval($editItem['retailPrice'] ?? 0), 2, '.', ''); ?>">
                            </div>
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="description" name="description" rows="3" class="mt-1 block w-full p-2 border border-gray-300 rounded"><?php echo htmlspecialchars($editItem['description'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Product Image</label>
                            <div class="flex items-start">
                                <div class="image-preview" id="imagePreviewContainer">
                                    <img src="<?php echo htmlspecialchars($editItem['imageUrl'] ?? 'images/placeholder.png'); ?>" alt="Product Image" id="productImagePreview" onerror="this.src='images/placeholder.png';">
                                    <div class="image-upload-overlay">
                                        <button type="button" class="image-upload-btn" id="uploadImageBtn">Change Image</button>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1">
                                    <input type="hidden" id="imageUrl" name="imageUrl" value="<?php echo htmlspecialchars($editItem['imageUrl'] ?? 'images/placeholder.png'); ?>">
                                    <p class="text-sm text-gray-500 mb-2">Upload a product image. It will be saved as [ProductID].extension.</p>
                                    <p class="text-xs text-gray-400">Supported: JPG, PNG, GIF, WEBP. Max: 5MB.</p>
                                    <input type="file" id="imageInput" accept="image/*" class="hidden">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-form-cost-column">
                        <div class="cost-breakdown">
                            <h3>Cost Breakdown</h3>
                            <?php foreach (['materials', 'labor', 'energy', 'equipment'] as $costType): ?>
                            <div class="cost-breakdown-section <?php echo $costType !== 'materials' ? 'mt-4' : ''; ?>">
                                <h4 class="font-semibold text-gray-700 mb-1 text-sm"><?php echo ucfirst($costType); ?></h4>
                                <div class="mb-2" id="<?php echo $costType; ?>List">
                                    <!-- Cost items will be rendered here by JavaScript -->
                                </div>
                                <button type="button" class="add-cost-btn" onclick="addCostItem('<?php echo $costType; ?>')">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 mr-1"><path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                                    Add <?php echo ucfirst(substr($costType, 0, -1)); ?>
                                </button>
                            </div>
                            <?php endforeach; ?>
                            <div class="cost-totals">
                                <div class="cost-total-row"><span class="cost-label">Materials Total:</span> <span class="cost-item-value" id="materialsTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row"><span class="cost-label">Labor Total:</span> <span class="cost-item-value" id="laborTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row"><span class="cost-label">Energy Total:</span> <span class="cost-item-value" id="energyTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row"><span class="cost-label">Equipment Total:</span> <span class="cost-item-value" id="equipmentTotalDisplay">$0.00</span></div>
                                <div class="cost-total-row border-t border-gray-300 pt-2 mt-2">
                                    <span class="font-semibold">Suggested Cost:</span> <span class="font-bold text-purple-700" id="suggestedCostDisplay">$0.00</span>
                                </div>
                                <div class="mt-2 text-sm text-gray-600">
                                    <button type="button" onclick="useSuggestedCost()" class="text-blue-600 hover:text-blue-800 underline text-xs">Use suggested cost for item</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 mt-auto pt-4 border-t">
                    <a href="?page=admin&section=inventory" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400 inline-block">Cancel</a>
                    <button type="submit" id="saveItemBtn" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        <span class="button-text">Save Item</span>
                        <span class="loading-spinner hidden"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div id="costModal" class="cost-modal">
        <div class="cost-modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 id="costModalTitle" class="text-lg font-semibold text-gray-700">Edit Cost Item</h3>
                <button type="button" class="text-gray-500 hover:text-gray-700 text-2xl leading-none" onclick="closeCostModal()">&times;</button>
            </div>
            <form id="costForm" class="space-y-4">
                <input type="hidden" id="costItemId" value="">
                <input type="hidden" id="costType" value="">
                <div id="materialNameField" class="hidden">
                    <label for="materialName" class="block text-sm font-medium text-gray-700">Material Name</label>
                    <input type="text" id="materialName" class="mt-1 block w-full p-2 border border-gray-300 rounded">
                </div>
                <div id="descriptionField" class="hidden">
                    <label for="itemDescription" class="block text-sm font-medium text-gray-700">Description</label>
                    <input type="text" id="itemDescription" class="mt-1 block w-full p-2 border border-gray-300 rounded">
                </div>
                <div>
                    <label for="itemCost" class="block text-sm font-medium text-gray-700">Cost ($)</label>
                    <input type="number" id="itemCost" class="mt-1 block w-full p-2 border border-gray-300 rounded" min="0" step="0.01" required>
                </div>
                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400" onclick="closeCostModal()">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        <span id="costSubmitText">Save</span>
                        <span id="costSubmitSpinner" class="loading-spinner hidden"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteCostModal" class="cost-modal">
        <div class="cost-modal-content">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-red-600">Confirm Delete Cost</h3>
                <button type="button" class="text-gray-500 hover:text-gray-700 text-2xl leading-none" onclick="closeDeleteCostModal()">&times;</button>
            </div>
            <p class="mb-4" id="deleteCostConfirmText">Are you sure you want to delete this cost item?</p>
            <div class="flex justify-end space-x-3">
                <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400" onclick="closeDeleteCostModal()">Cancel</button>
                <button type="button" id="confirmDeleteCostBtn" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                    <span id="confirmDeleteCostBtnText">Delete</span>
                    <span id="deleteCostSpinner" class="loading-spinner hidden"></span>
                </button>
            </div>
            <input type="hidden" id="deleteCostItemId" value="">
            <input type="hidden" id="deleteCostType" value="">
        </div>
    </div>
</div>

<script>
let costBreakdown = <?php echo ($modalMode === 'edit' || $modalMode === 'add') && isset($costBreakdownPHP) ? json_encode($costBreakdownPHP) : '{}'; ?>;
let currentItemId = <?php echo ($modalMode === 'edit' && isset($editItem['id'])) ? json_encode($editItem['id']) : 'null'; ?>;

console.log('Initial currentItemId:', currentItemId);
console.log('Initial costBreakdown JS object:', JSON.parse(JSON.stringify(costBreakdown)));

function showToast(message, type = 'info') {
    console.log(`Toast: [${type}] ${message}`);
    const existingToast = document.querySelector('.toast-notification.show');
    if (existingToast) { existingToast.remove(); }
    const toast = document.createElement('div');
    toast.className = 'toast-notification ' + (type || 'info');
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => { toast.classList.add('show'); }, 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => { toast.remove(); }, 300);
    }, 3000);
}

function escapeHtml(unsafe) {
    if (typeof unsafe !== 'string') return unsafe;
    return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function addslashes(str) {
    if (typeof str !== 'string') return str;
    return (str + '').replace(/[\\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
}


function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin Inventory DOMContentLoaded');

    const inventoryTable = document.getElementById('inventoryTable');
    if (inventoryTable) {
        console.log('Inventory table found, attaching inline edit listeners.');
        inventoryTable.addEventListener('click', function(e) {
            const cell = e.target.closest('.editable');
            if (!cell || cell.classList.contains('editing')) { return; }

            const field = cell.dataset.field;
            const itemId = cell.closest('tr').dataset.id;
            const currentValue = cell.innerText.trim();
            let valueForInput = currentValue;

            if (field === 'costPrice' || field === 'retailPrice') {
                valueForInput = parseFloat(currentValue.replace('$', '')) || 0;
            } else if (field === 'stockLevel' || field === 'reorderPoint') {
                valueForInput = parseInt(currentValue, 10) || 0;
            }

            console.log(`Inline edit started: ItemID=${itemId}, Field=${field}, CurrentValue=${currentValue}`);

            cell.classList.add('editing');
            cell.dataset.originalContent = cell.innerHTML;

            let inputElement;
            if (field === 'stockLevel' || field === 'reorderPoint') {
                inputElement = document.createElement('input'); inputElement.type = 'number'; inputElement.min = '0';
            } else if (field === 'costPrice' || field === 'retailPrice') {
                inputElement = document.createElement('input'); inputElement.type = 'number'; inputElement.min = '0'; inputElement.step = '0.01';
            } else {
                inputElement = document.createElement('input'); inputElement.type = 'text';
            }
            inputElement.value = valueForInput;
            cell.innerHTML = '';
            cell.appendChild(inputElement);
            inputElement.focus();

            const buttonsDiv = document.createElement('div');
            buttonsDiv.className = 'editing-buttons';
            const saveButton = document.createElement('button');
            saveButton.className = 'save-btn'; saveButton.innerHTML = '✓'; saveButton.title = 'Save'; saveButton.type = 'button';
            const cancelButton = document.createElement('button');
            cancelButton.className = 'cancel-btn'; cancelButton.innerHTML = '✕'; cancelButton.title = 'Cancel'; cancelButton.type = 'button';
            buttonsDiv.appendChild(saveButton); buttonsDiv.appendChild(cancelButton);
            cell.appendChild(buttonsDiv);

            saveButton.onclick = function(event) { event.stopPropagation(); saveInlineEdit(cell, itemId, field, inputElement.value); };
            cancelButton.onclick = function(event) { event.stopPropagation(); cancelInlineEdit(cell); };
            inputElement.addEventListener('keydown', function(ev) {
                if (ev.key === 'Enter') { ev.preventDefault(); saveInlineEdit(cell, itemId, field, this.value); }
                else if (ev.key === 'Escape') { cancelInlineEdit(cell); }
            });
            inputElement.addEventListener('click', ev => ev.stopPropagation());
        });
    } else {
        console.error('Inventory table #inventoryTable not found for inline editing.');
    }

    function saveInlineEdit(cell, itemId, field, value) {
        console.log(`Saving inline edit: ItemID=${itemId}, Field=${field}, NewValue=${value}`);
        const originalHTML = cell.innerHTML;
        cell.innerHTML = '<div class="flex justify-center items-center h-full"><div class="loading-spinner dark"></div></div>';

        const formData = new FormData();
        formData.append('itemId', itemId);
        formData.append('field', field);
        formData.append('value', value);
        // action is not needed for inline edit as process_inventory_update.php detects it by field presence

        fetch('/process_inventory_update.php', { // Ensure this points to the correct processing script (absolute path)
            method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) { return response.json().then(errData => { throw errData; }); }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                let displayValue = value;
                if (field === 'costPrice' || field === 'retailPrice') {
                    displayValue = '$' + parseFloat(value).toFixed(2);
                }
                cell.innerHTML = displayValue;
                cell.classList.add('highlight');
                setTimeout(() => cell.classList.remove('highlight'), 1500);
                showToast(data.message || 'Updated successfully!', 'success');
            } else {
                showToast('Error: ' + (data.error || 'Unknown error during update.'), 'error');
                cell.innerHTML = cell.dataset.originalContent;
            }
        })
        .catch(error => {
            console.error('Error updating item via inline edit:', error);
            let errorMsg = 'Failed to update. Please try again.';
            if(error && error.error) errorMsg = error.error;
            else if (error && error.message) errorMsg = error.message;
            showToast(errorMsg, 'error');
            cell.innerHTML = cell.dataset.originalContent;
        })
        .finally(() => {
            cell.classList.remove('editing');
        });
    }

    function cancelInlineEdit(cell) {
        console.log('Cancelling inline edit.');
        cell.innerHTML = cell.dataset.originalContent;
        cell.classList.remove('editing');
    }

    const inventoryForm = document.getElementById('inventoryForm');
    if (inventoryForm) {
        console.log('Inventory form #inventoryForm found.');
        inventoryForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const saveButton = document.getElementById('saveItemBtn');
            const saveButtonText = saveButton.querySelector('.button-text');
            const saveButtonSpinner = saveButton.querySelector('.loading-spinner');

            // form.action is now an absolute path: "/process_inventory_update.php"
            console.log('Submitting inventory form via AJAX. Action URL:', form.action);
            console.log('Form Data:', Object.fromEntries(formData.entries()));

            saveButtonText.classList.add('hidden');
            saveButtonSpinner.classList.remove('hidden');
            saveButton.disabled = true;

            form.querySelectorAll('.field-error-highlight').forEach(el => el.classList.remove('field-error-highlight'));

            fetch(form.action, { 
                method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => {
                console.log('Raw response status:', response.status, response.statusText);
                return response.text().then(text => {
                    console.log('Raw response text:', text);
                    if (!response.ok) {
                        let errorData = { error: `HTTP error! Status: ${response.status}`, details: text.substring(0, 500), rawText: text };
                        try { const parsedError = JSON.parse(text); errorData = {...errorData, ...parsedError}; } catch (e) {}\n                        throw errorData;
                    }
                    try { return JSON.parse(text); } catch (e) {
                        console.error("Failed to parse JSON from successful response:", e, text);
                        throw { error: "Invalid JSON response from server.", details: text.substring(0,500), rawText: text };
                    }
                });
            })
            .then(data => {
                console.log('Parsed JSON data from form submission:', data);
                if (data.success) {
                    let redirectUrl = '?page=admin&section=inventory';
                    // Determine itemId from response if available, or from form data for new items
                    let itemIdForRedirect = data.itemId || formData.get('itemId');
                    if (data.action === 'add' && data.itemId) { // if process_inventory_update returns new itemId
                        itemIdForRedirect = data.itemId;
                    }

                    if (itemIdForRedirect) {
                        redirectUrl += '&edit=' + itemIdForRedirect;
                    }
                    redirectUrl += '&message=' + encodeURIComponent(data.message || 'Operation successful.') + '&type=success';
                    window.location.href = redirectUrl;
                } else {
                    let mainError = data.error || 'Failed to save item.';
                    if (data.errors && data.errors.length > 0) {
                        mainError += " Details: " + data.errors.join(', ');
                    }
                    if (data.field_errors) {
                        data.field_errors.forEach(fieldName => {
                            const fieldElement = form.querySelector(`[name="${fieldName}"]`);
                            if (fieldElement) fieldElement.classList.add('field-error-highlight');
                        });
                         mainError += " Please check highlighted fields.";
                    }
                    showToast(mainError, 'error');
                    saveButtonText.classList.remove('hidden');
                    saveButtonSpinner.classList.add('hidden');
                    saveButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error saving item (Fetch Catch):', error);
                let errorMessage = 'An unexpected error occurred.';
                if (error && error.error) errorMessage = error.error;
                else if (typeof error === 'string') errorMessage = error;
                else if (error && error.message) errorMessage = error.message;

                let errorDetails = "";
                if (error && error.details) errorDetails = String(error.details).substring(0,200);

                showToast(errorMessage + (errorDetails ? ` Details: ${errorDetails}...` : ''), 'error');
                saveButtonText.classList.remove('hidden');
                saveButtonSpinner.classList.add('hidden');
                saveButton.disabled = false;
            });
        });
    } else {
         console.log('Main inventory form #inventoryForm not found (this is normal if modal is not open).');
    }

    const uploadImageBtn = document.getElementById('uploadImageBtn');
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('productImagePreview');
    const imageUrlInput = document.getElementById('imageUrl');

    if (uploadImageBtn && imageInput && imagePreview && imageUrlInput) {
        console.log('Image upload elements found.');
        uploadImageBtn.addEventListener('click', () => imageInput.click());
        imageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) { showToast('Invalid file type. Use JPG, PNG, GIF, WEBP.', 'error'); return; }
                if (file.size > 5 * 1024 * 1024) { showToast('File size exceeds 5MB limit.', 'error'); return; }

                let effectiveProductId = document.getElementById('productId')?.value;
                let itemIdForUpload = currentItemId || document.querySelector('#inventoryForm input[name="itemId"]')?.value;

                if (!itemIdForUpload && document.querySelector('#inventoryForm input[name="action"]')?.value === 'add') {
                     showToast('Please save the new item first to get an Item ID before uploading an image.', 'error');
                     return;
                }
                 if (!itemIdForUpload) { 
                    showToast('Cannot upload image: Item ID is missing.', 'error');
                    return;
                }

                uploadImageBtn.innerHTML = '<span class="loading-spinner"></span> Uploading...';
                uploadImageBtn.disabled = true;

                const formData = new FormData();
                formData.append('image', file);
                formData.append('itemId', itemIdForUpload); // Pass itemId
                formData.append('productId', effectiveProductId || ''); // Pass productId if available

                fetch('/process_image_upload.php', { method: 'POST', body: formData }) // Absolute path
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        imagePreview.src = data.data.imageUrl + '?' + new Date().getTime();
                        imageUrlInput.value = data.data.imageUrl;
                        showToast('Image uploaded successfully!', 'success');
                    } else {
                        showToast('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error uploading image:', error);
                    showToast('Failed to upload image.', 'error');
                })
                .finally(() => {
                    uploadImageBtn.innerHTML = 'Change Image';
                    uploadImageBtn.disabled = false;
                });
            }
        });
    } else {
        console.log('Image upload elements not all found (this is normal if modal is not open).');
    }

    const costForm = document.getElementById('costForm');
    if (costForm) {
        console.log('Cost form #costForm found.');
        costForm.addEventListener('submit', e => { e.preventDefault(); saveCostItem(); });
    }
    const confirmDeleteCostBtn = document.getElementById('confirmDeleteCostBtn');
    if (confirmDeleteCostBtn) {
        console.log('Confirm delete cost button found.');
        confirmDeleteCostBtn.addEventListener('click', confirmDeleteCostItem);
    }

    // Initial rendering of cost breakdown if modal is open for edit/add
    if (document.getElementById('inventoryModal')?.style.display === 'flex' && currentItemId) {
        console.log('Modal is open for editing/adding, attempting to render initial cost breakdown for item:', currentItemId);
        refreshCostBreakdown(true); // true to use existing PHP-passed data first
    } else if (document.getElementById('inventoryModal')?.style.display === 'flex' && !currentItemId && <?php echo json_encode($modalMode === 'add'); ?>) {
        console.log('Modal is open for ADDING a new item, rendering empty cost breakdown.');
        costBreakdown = { materials: [], labor: [], energy: [], equipment: [], totals: { materialTotal: 0, laborTotal: 0, energyTotal: 0, equipmentTotal: 0, suggestedCost: 0 }};
        renderAllCostLists(); 
        updateTotalsDisplay();
    } else if (document.getElementById('inventoryModal')?.style.display === 'flex') {
        console.warn('Modal is open, but costBreakdown data seems incomplete or currentItemId is missing for non-add mode.', costBreakdown, currentItemId);
        updateTotalsDisplay(); // Ensure totals are at least zeroed out
        renderCostList('materials', []); renderCostList('labor', []); renderCostList('energy', []); renderCostList('equipment', []);
    }
});

function addCostItem(type) {
    console.log('Adding cost item of type:', type);
    if (!currentItemId && <?php echo json_encode($modalMode === 'add'); ?>) { // Only block if adding a NEW main item
        showToast('Please save the main item first before adding costs.', 'error');
        return;
    }
     if (!currentItemId && <?php echo json_encode($modalMode !== 'add'); ?>) { // Should not happen if editing, but safety
        showToast('Error: Main item ID is missing. Cannot add costs.', 'error');
        return;
    }
    document.getElementById('costForm').reset();
    document.getElementById('costItemId').value = '';
    document.getElementById('costType').value = type;
    document.getElementById('costModalTitle').textContent = 'Add ' + capitalizeFirstLetter(type) + ' Cost';
    document.getElementById('materialNameField').classList.toggle('hidden', type !== 'materials');
    document.getElementById('descriptionField').classList.toggle('hidden', type === 'materials');
    document.getElementById('costModal').classList.add('show');
}

function editCostItem(type, id) {
    console.log(`Editing cost item. Type: ${type}, ID: ${id}`);
    document.getElementById('costForm').reset();
    document.getElementById('costItemId').value = id;
    document.getElementById('costType').value = type;
    document.getElementById('costModalTitle').textContent = 'Edit ' + capitalizeFirstLetter(type) + ' Cost';

    let itemToEdit;
    if (!costBreakdown || !costBreakdown[type]) {
        console.error(`Cost type ${type} not found in costBreakdown data.`, costBreakdown);
        showToast(`Error: Data for ${type} costs is missing.`, 'error');
        return;
    }
    itemToEdit = costBreakdown[type].find(item => String(item.id) === String(id));


    if (!itemToEdit) {
        console.error(`Item with ID ${id} of type ${type} not found in costBreakdown data.`);
        showToast(`Error: Cost item not found. Please refresh.`, 'error');
        return;
    }

    if (type === 'materials') {
        document.getElementById('materialNameField').classList.remove('hidden');
        document.getElementById('descriptionField').classList.add('hidden');
        document.getElementById('materialName').value = itemToEdit.name;
    } else {
        document.getElementById('materialNameField').classList.add('hidden');
        document.getElementById('descriptionField').classList.remove('hidden');
        document.getElementById('itemDescription').value = itemToEdit.description;
    }
    document.getElementById('itemCost').value = itemToEdit.cost;
    document.getElementById('costModal').classList.add('show');
}

function saveCostItem() {
    const id = document.getElementById('costItemId').value;
    const type = document.getElementById('costType').value;
    const cost = document.getElementById('itemCost').value;
    const name = (type === 'materials') ? document.getElementById('materialName').value : '';
    const description = (type !== 'materials') ? document.getElementById('itemDescription').value : '';

    console.log(`Saving cost item. ID: ${id || 'NEW'}, Type: ${type}, Cost: ${cost}`);

    if (!currentItemId) {
        showToast('Cannot save cost: Main item ID is missing. Please save the main item first.', 'error');
        return;
    }
    if (!cost || parseFloat(cost) < 0) { showToast('Please enter a valid cost.', 'error'); return; }
    if (type === 'materials' && !name.trim()) { showToast('Material name cannot be empty.', 'error'); return; }
    if (type !== 'materials' && !description.trim()) { showToast('Description cannot be empty.', 'error'); return; }

    document.getElementById('costSubmitText').classList.add('hidden');
    document.getElementById('costSubmitSpinner').classList.remove('hidden');

    const payload = { costType: type, cost: parseFloat(cost), name: name, description: description, inventoryId: currentItemId };
    const isUpdate = id !== '';
    const method = isUpdate ? 'PUT' : 'POST';

    if (isUpdate) { payload.id = id; }

    fetch('/process_cost_breakdown.php', { // Absolute path
        method: method,
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeCostModal();
            showToast(result.message || 'Cost item saved.', 'success');
            refreshCostBreakdown(); // Refresh to show new/updated item and totals
        } else {
            showToast('Error: ' + (result.error || 'Failed to save cost item.'), 'error');
        }
    })
    .catch(error => {
        console.error('Error saving cost item:', error);
        showToast('Save failed. ' + (error.message || 'An unexpected error occurred.'), 'error');
    })
    .finally(() => {
        document.getElementById('costSubmitText').classList.remove('hidden');
        document.getElementById('costSubmitSpinner').classList.add('hidden');
    });
}

function deleteCostItem(type, id, name) {
    console.log(`Attempting to delete cost item. Type: ${type}, ID: ${id}, Name: ${name}`);
    if (!currentItemId) {
        showToast('Cannot delete cost: Main item ID is missing.', 'error');
        return;
    }
    document.getElementById('deleteCostItemId').value = id;
    document.getElementById('deleteCostType').value = type;
    document.getElementById('deleteCostConfirmText').textContent = `Delete the ${type.slice(0,-1)} "${escapeHtml(name)}"?`;
    document.getElementById('deleteCostModal').classList.add('show');
}

function confirmDeleteCostItem() {
    const id = document.getElementById('deleteCostItemId').value;
    const type = document.getElementById('deleteCostType').value;
    console.log(`Confirming delete. Type: ${type}, ID: ${id}`);

    if (!currentItemId) {
        showToast('Cannot delete cost: Main item ID is missing.', 'error');
        closeDeleteCostModal();
        return;
    }

    const btnText = document.getElementById('confirmDeleteCostBtnText');
    const spinner = document.getElementById('deleteCostSpinner');
    btnText.classList.add('hidden');
    spinner.classList.remove('hidden');

    fetch(`/process_cost_breakdown.php?inventoryId=${currentItemId}&costType=${type}&id=${id}`, { // Absolute path
        method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeDeleteCostModal();
            showToast(result.message || 'Cost item deleted.', 'success');
            refreshCostBreakdown(); // Refresh to show updated list and totals
        } else {
            showToast('Error: ' + (result.error || 'Delete failed.'), 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting cost item:', error);
        showToast('Delete failed. ' + (error.message || 'An unexpected error occurred.'), 'error');
    })
    .finally(() => {
        btnText.classList.remove('hidden');
        spinner.classList.add('hidden');
    });
}

function refreshCostBreakdown(useExistingData = false) {
    console.log('Refreshing cost breakdown. currentItemId:', currentItemId, 'Use existing data:', useExistingData);
    if (!currentItemId && !useExistingData) {
        console.warn('Cannot refresh costs, currentItemId is not set (and not using existing data).');
        // For "Add New Item" mode where currentItemId is null until first save
        costBreakdown = { materials: [], labor: [], energy: [], equipment: [], totals: { materialTotal: 0, laborTotal: 0, energyTotal: 0, equipmentTotal: 0, suggestedCost: 0 }};
        renderAllCostLists(); updateTotalsDisplay(); return;
    }

    if (useExistingData && costBreakdown && costBreakdown.materials !== undefined) {
        console.log('Using existing costBreakdown data to re-render.');
        renderAllCostLists(); updateTotalsDisplay(); return;
    }
    // If currentItemId is null (e.g. adding new item), don't fetch, just use empty/default data.
    if (!currentItemId) {
        console.log('No currentItemId, not fetching new cost breakdown. Will use empty data.');
        costBreakdown = { materials: [], labor: [], energy: [], equipment: [], totals: { materialTotal: 0, laborTotal: 0, energyTotal: 0, equipmentTotal: 0, suggestedCost: 0 }};
        renderAllCostLists(); updateTotalsDisplay(); return;
    }

    fetch(`/process_cost_breakdown.php?inventoryId=${currentItemId}&costType=all`, { // Absolute path
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        console.log('Fetched new cost breakdown data:', data);
        if (data.success && data.data) {
            costBreakdown = data.data;
            renderAllCostLists(); updateTotalsDisplay();
        } else {
            showToast('Error refreshing costs: ' + (data.error || 'Invalid data received'), 'error');
            costBreakdown = { materials: [], labor: [], energy: [], equipment: [], totals: { materialTotal: 0, laborTotal: 0, energyTotal: 0, equipmentTotal: 0, suggestedCost: 0 }};
            renderAllCostLists(); updateTotalsDisplay();
        }
    })
    .catch(error => {
        console.error('Error fetching/refreshing costs:', error);
        showToast('Failed to refresh costs. ' + (error.message || ''), 'error');
    });
}

function renderAllCostLists() {
    if (!costBreakdown) {
        console.error("renderAllCostLists called but costBreakdown is null/undefined.");
        costBreakdown = { materials: [], labor: [], energy: [], equipment: [], totals: { materialTotal: 0, laborTotal: 0, energyTotal: 0, equipmentTotal: 0, suggestedCost: 0 }};
    }
    renderCostList('materials', costBreakdown.materials || []);
    renderCostList('labor', costBreakdown.labor || []);
    renderCostList('energy', costBreakdown.energy || []);
    renderCostList('equipment', costBreakdown.equipment || []);
}

function renderCostList(type, items) {
    const listElement = document.getElementById(type + 'List');
    if (!listElement) {
        console.error(`Element #${type}List not found for rendering.`); return;
    }
    listElement.innerHTML = '';
    console.log(`Rendering cost list for type: ${type}, items:`, items);

    if (items && items.length > 0) {
        items.forEach(item => {
            const itemName = (type === 'materials') ? (item.name || 'N/A') : (item.description || 'N/A');
            const itemCost = parseFloat(item.cost || 0).toFixed(2);
            const itemDiv = document.createElement('div');
            itemDiv.className = 'cost-item';
            itemDiv.dataset.id = item.id;
            itemDiv.innerHTML = `
                <span class="cost-item-name">${escapeHtml(itemName)}</span>
                <div class="flex items-center">
                    <span class="cost-item-value">$${itemCost}</span>
                    <div class="cost-item-actions">
                        <button type="button" class="cost-edit-btn" onclick="editCostItem('${type}', ${item.id})">✏️</button>
                        <button type="button" class="cost-delete-btn" onclick="deleteCostItem('${type}', ${item.id}, '${escapeHtml(addslashes(itemName))}')">🗑️</button>
                    </div>
                </div>`;
            listElement.appendChild(itemDiv);
        });
    } else {
        listElement.innerHTML = `<div class="text-gray-500 text-sm italic">No ${type} data</div>`;
    }
}

function updateTotalsDisplay() {
    console.log('Updating totals display. Current costBreakdown.totals:', costBreakdown ? costBreakdown.totals : 'costBreakdown is undefined');
    if (!costBreakdown || !costBreakdown.totals) {
        console.warn('costBreakdown.totals is undefined or null. Setting defaults for display.');
        costBreakdown = costBreakdown || {}; // Ensure costBreakdown itself is an object
        costBreakdown.totals = { materialTotal: 0, laborTotal: 0, energyTotal: 0, equipmentTotal: 0, suggestedCost: 0 };
    }

    const matTotal = parseFloat(costBreakdown.totals.materialTotal || 0).toFixed(2);
    const labTotal = parseFloat(costBreakdown.totals.laborTotal || 0).toFixed(2);
    const engTotal = parseFloat(costBreakdown.totals.energyTotal || 0).toFixed(2);
    const equTotal = parseFloat(costBreakdown.totals.equipmentTotal || 0).toFixed(2);
    const sugCost = parseFloat(costBreakdown.totals.suggestedCost || 0).toFixed(2);

    document.getElementById('materialsTotalDisplay').textContent = '$' + matTotal;
    document.getElementById('laborTotalDisplay').textContent = '$' + labTotal;
    document.getElementById('energyTotalDisplay').textContent = '$' + engTotal;
    document.getElementById('equipmentTotalDisplay').textContent = '$' + equTotal;
    document.getElementById('suggestedCostDisplay').textContent = '$' + sugCost;

    const costPriceInput = document.getElementById('costPrice');
    if (costPriceInput) {
        let suggestedSpan = costPriceInput.parentElement.querySelector('.suggested-cost');
        if (!suggestedSpan) {
            suggestedSpan = document.createElement('span');
            suggestedSpan.className = 'suggested-cost ml-2 text-xs text-gray-500';
            costPriceInput.parentElement.appendChild(suggestedSpan);
        }
        if (parseFloat(sugCost) > 0) {
            suggestedSpan.textContent = `(Suggested: $${sugCost})`;
        } else {
            suggestedSpan.textContent = '';
        }
    }
     console.log(`Totals updated: Mat=$${matTotal}, Lab=$${labTotal}, Eng=$${engTotal}, Equ=$${equTotal}, Sug=$${sugCost}`);
}

function closeCostModal() { document.getElementById('costModal').classList.remove('show'); }
function closeDeleteCostModal() { document.getElementById('deleteCostModal').classList.remove('show'); }

function useSuggestedCost() {
    if (costBreakdown && costBreakdown.totals && costBreakdown.totals.suggestedCost !== undefined) {
        const costPriceField = document.getElementById('costPrice');
        if (costPriceField) {
            costPriceField.value = parseFloat(costBreakdown.totals.suggestedCost).toFixed(2);
            costPriceField.classList.add('highlight');
            setTimeout(() => costPriceField.classList.remove('highlight'), 1500);
            showToast('Suggested cost applied to Cost Price field.', 'success');
        } else {
            showToast('Cost Price field not found.', 'error');
        }
    } else {
        showToast('No suggested cost available to apply.', 'error');
    }
}

// Handle messages from URL parameters (e.g., after non-AJAX redirect)
const urlParams = new URLSearchParams(window.location.search);
const messageFromUrl = urlParams.get('message');
const typeFromUrl = urlParams.get('type');
if (messageFromUrl && typeFromUrl) {
    showToast(messageFromUrl, typeFromUrl);
    // Clean URL parameters to prevent message from showing again on refresh
    const newUrl = window.location.pathname + '?page=admin&section=inventory' + (urlParams.get('edit') ? '&edit=' + urlParams.get('edit') : '');
    window.history.replaceState({}, document.title, newUrl);
}
</script>
<?php
ob_end_flush(); // Send the buffered output to the browser
?>
