<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authentication check
function checkAuth()
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        Response::json(['error' => 'Not authenticated'], 401);
        exit();
    }

    $userRole = strtolower($_SESSION['user']['role'] ?? '');
    if ($userRole !== 'admin') {
        Response::json(['error' => 'Admin access required'], 403);
        exit();
    }
}

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Detect sale_items SKU column name: prefer 'sku', fallback to 'item_sku'
    $saleItemsSkuCol = 'sku';
    try {
        $row = Database::queryOne("SHOW COLUMNS FROM sale_items LIKE 'sku'");
        if (!$row) {
            $saleItemsSkuCol = 'item_sku';
        }
    } catch (Throwable $eCol) {
        // If table missing or error, default stays 'sku'
    }

    // Get action from query params, form data, or JSON body
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // If no action found in GET/POST, check JSON input
    if (empty($action)) {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $action = $jsonInput['action'] ?? '';
    }

    switch ($action) {

        case 'list':
            checkAuth();

            $sales = Database::queryAll(
                "SELECT s.*, 
                       COUNT(si.`$saleItemsSkuCol`) as item_count,
                       CASE 
                           WHEN s.is_active = 1 AND NOW() BETWEEN s.start_date AND s.end_date THEN 'active'
                           WHEN s.is_active = 1 AND NOW() < s.start_date THEN 'scheduled'
                           WHEN s.is_active = 1 AND NOW() > s.end_date THEN 'expired'
                           ELSE 'inactive'
                       END as status
                FROM sales s
                LEFT JOIN sale_items si ON s.id = si.sale_id
                GROUP BY s.id
                ORDER BY s.created_at DESC"
            );

            Response::json(['success' => true, 'sales' => $sales]);
            break;

        case 'get':
            checkAuth();

            $saleId = $_GET['id'] ?? 0;
            if (!$saleId) {
                Response::json(['error' => 'Sale ID required']);
                break;
            }

            // Get sale details
            $sale = Database::queryOne("SELECT * FROM sales WHERE id = ?", [$saleId]);

            if (!$sale) {
                Response::json(['error' => 'Sale not found']);
                break;
            }

            // Get sale items
            $saleItems = Database::queryAll(
                "SELECT si.`$saleItemsSkuCol` AS item_sku, i.name as item_name, i.retailPrice as original_price
                 FROM sale_items si
                 JOIN items i ON si.`$saleItemsSkuCol` = i.sku
                 WHERE si.sale_id = ?",
                [$saleId]
            );

            $sale['items'] = $saleItems;
            Response::json(['success' => true, 'sale' => $sale]);
            break;

        case 'create':
            checkAuth();

            $input = json_decode(file_get_contents('php://input'), true);

            $name = $input['name'] ?? '';
            $description = $input['description'] ?? '';
            $discountPercentage = $input['discount_percentage'] ?? 0;
            $startDate = $input['start_date'] ?? '';
            $endDate = $input['end_date'] ?? '';
            $isActive = $input['is_active'] ?? true;
            $items = $input['items'] ?? [];

            if (!$name || !$discountPercentage || !$startDate || !$endDate) {
                Response::json(['error' => 'Missing required fields']);
                break;
            }

            Database::beginTransaction();

            try {
                // Create sale
                Database::execute(
                    "INSERT INTO sales (name, description, discount_percentage, start_date, end_date, is_active)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [$name, $description, $discountPercentage, $startDate, $endDate, $isActive]
                );
                $saleId = Database::lastInsertId();

                // Add sale items
                if (!empty($items)) {
                    foreach ($items as $itemSku) {
                        Database::execute("INSERT INTO sale_items (sale_id, `$saleItemsSkuCol`) VALUES (?, ?)", [$saleId, $itemSku]);
                    }
                }

                Database::commit();
                Response::json(['success' => true, 'sale_id' => $saleId, 'message' => 'Sale created successfully']);

            } catch (Exception $e) {
                Database::rollBack();
                Response::json(['error' => 'Failed to create sale: ' . $e->getMessage()]);
            }
            break;

        case 'update':
            checkAuth();

            $input = json_decode(file_get_contents('php://input'), true);

            $saleId = $input['id'] ?? 0;
            $name = $input['name'] ?? '';
            $description = $input['description'] ?? '';
            $discountPercentage = $input['discount_percentage'] ?? 0;
            $startDate = $input['start_date'] ?? '';
            $endDate = $input['end_date'] ?? '';
            $isActive = $input['is_active'] ?? true;
            $items = $input['items'] ?? [];

            if (!$saleId || !$name || !$discountPercentage || !$startDate || !$endDate) {
                Response::json(['error' => 'Missing required fields']);
                break;
            }

            Database::beginTransaction();

            try {
                // Update sale
                Database::execute(
                    "UPDATE sales 
                     SET name = ?, description = ?, discount_percentage = ?, start_date = ?, end_date = ?, is_active = ?
                     WHERE id = ?",
                    [$name, $description, $discountPercentage, $startDate, $endDate, $isActive, $saleId]
                );

                // Remove existing sale items
                Database::execute("DELETE FROM sale_items WHERE sale_id = ?", [$saleId]);

                // Add new sale items
                if (!empty($items)) {
                    foreach ($items as $itemSku) {
                        Database::execute("INSERT INTO sale_items (sale_id, item_sku) VALUES (?, ?)", [$saleId, $itemSku]);
                    }
                }

                Database::commit();
                Response::json(['success' => true, 'message' => 'Sale updated successfully']);

            } catch (Exception $e) {
                Database::rollBack();
                Response::json(['error' => 'Failed to update sale: ' . $e->getMessage()]);
            }
            break;

        case 'delete':
            checkAuth();

            $saleId = $_POST['id'] ?? $_GET['id'] ?? 0;
            if (!$saleId) {
                echo json_encode(['error' => 'Sale ID required']);
                break;
            }

            $result = Database::execute("DELETE FROM sales WHERE id = ?", [$saleId]);

            if ($result) {
                Response::json(['success' => true, 'message' => 'Sale deleted successfully']);
            } else {
                Response::json(['error' => 'Failed to delete sale']);
            }
            break;

        case 'get_active_sales':
            // Get currently active sales for an item (public endpoint)
            $itemSku = $_GET['item_sku'] ?? '';

            if (!$itemSku) {
                http_response_code(200);
                echo json_encode(['success' => true, 'sale' => null]);
                exit;
            }

            $activeSale = Database::queryOne(
                "SELECT s.*, si.`$saleItemsSkuCol` AS item_sku
                 FROM sales s
                 JOIN sale_items si ON s.id = si.sale_id
                 WHERE si.`$saleItemsSkuCol` = ? 
                 AND s.is_active = 1
                 AND NOW() BETWEEN s.start_date AND s.end_date
                 ORDER BY s.discount_percentage DESC
                 LIMIT 1",
                [$itemSku]
            );

            http_response_code(200);
            Response::json(['success' => true, 'sale' => $activeSale ?: null]);
            exit;

        case 'get_all_items':
            checkAuth();

            // Get all items for sale assignment
            $items = Database::queryAll("SELECT sku, name, retailPrice FROM items ORDER BY name");

            Response::json(['success' => true, 'items' => $items]);
            break;

        case 'toggle_active':
            checkAuth();

            $saleId = $_POST['id'] ?? 0;
            if (!$saleId) {
                echo json_encode(['error' => 'Sale ID required']);
                break;
            }

            $result = Database::execute("UPDATE sales SET is_active = NOT is_active WHERE id = ?", [$saleId]);

            if ($result) {
                Response::json(['success' => true, 'message' => 'Sale status updated']);
            } else {
                Response::json(['error' => 'Failed to update sale status']);
            }
            break;

        default:
            Response::json(['error' => 'Invalid action']);
            break;
    }

} catch (PDOException $e) {
    Response::json(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?> 