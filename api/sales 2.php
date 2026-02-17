<?php
/**
 * api/sales.php
 * Sales Management API
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/helpers/SalesHelper.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    Database::getInstance();
    $skuCol = SalesHelper::getSaleItemsSkuCol();
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    if (empty($action)) {
        $json = json_decode(file_get_contents('php://input'), true);
        $action = $json['action'] ?? '';
    }

    switch ($action) {
        case 'list':
            AuthHelper::requireAdmin();
            Response::json(['success' => true, 'sales' => SalesHelper::getSalesList($skuCol)]);
            break;

        case 'get':
            AuthHelper::requireAdmin();
            $id = $_GET['id'] ?? 0;
            if (!$id) Response::error('Sale ID required', null, 400);
            $sale = Database::queryOne("SELECT * FROM sales WHERE id = ?", [$id]);
            if (!$sale) Response::error('Sale not found', null, 404);
            $sale['items'] = Database::queryAll("SELECT si.`$skuCol` AS item_sku, i.name as item_name, i.retail_price as original_price FROM sale_items si JOIN items i ON si.`$skuCol` = i.sku WHERE si.sale_id = ?", [$id]);
            Response::json(['success' => true, 'sale' => $sale]);
            break;

        case 'create':
            AuthHelper::requireAdmin();
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['name']) || !isset($input['discount_percentage'])) Response::error('Missing fields', null, 400);
            try {
                Database::beginTransaction();
                $id = SalesHelper::createSale($input, $skuCol);
                Database::commit();
                Response::json(['success' => true, 'sale_id' => $id, 'message' => 'Created']);
            } catch (Exception $e) {
                Database::rollBack();
                Response::error($e->getMessage());
            }
            break;

        case 'update':
            AuthHelper::requireAdmin();
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? 0;
            if (!$id || empty($input['name'])) Response::error('Missing fields', null, 400);
            try {
                Database::beginTransaction();
                Database::execute("UPDATE sales SET name = ?, description = ?, discount_percentage = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?", [$input['name'], $input['description'] ?? '', $input['discount_percentage'], $input['start_date'], $input['end_date'], $input['is_active'], $id]);
                Database::execute("DELETE FROM sale_items WHERE sale_id = ?", [$id]);
                if (!empty($input['items'])) {
                    foreach ($input['items'] as $sku) Database::execute("INSERT INTO sale_items (sale_id, `$skuCol`) VALUES (?, ?)", [$id, $sku]);
                }
                Database::commit();
                Response::json(['success' => true, 'message' => 'Updated']);
            } catch (Exception $e) {
                Database::rollBack();
                Response::error($e->getMessage());
            }
            break;

        case 'delete':
            AuthHelper::requireAdmin();
            $id = $_POST['id'] ?? $_GET['id'] ?? 0;
            if (!$id) Response::error('ID required', null, 400);
            Database::execute("DELETE FROM sales WHERE id = ?", [$id]);
            Response::json(['success' => true]);
            break;

        case 'get_active_sales':
            $sku = $_GET['item_sku'] ?? '';
            if (!$sku) Response::json(['success' => true, 'sale' => null]);
            $active = Database::queryOne("SELECT s.* FROM sales s JOIN sale_items si ON s.id = si.sale_id WHERE si.`$skuCol` = ? AND s.is_active = 1 AND NOW() BETWEEN s.start_date AND s.end_date ORDER BY s.discount_percentage DESC LIMIT 1", [$sku]);
            Response::json(['success' => true, 'sale' => $active ?: null]);
            break;

        case 'get_all_items':
            AuthHelper::requireAdmin();
            Response::json(['success' => true, 'items' => Database::queryAll("SELECT sku, name, retail_price FROM items ORDER BY name")]);
            break;

        case 'toggle_active':
            AuthHelper::requireAdmin();
            $id = $_POST['id'] ?? 0;
            if (!$id) Response::error('ID required', null, 400);
            Database::execute("UPDATE sales SET is_active = NOT is_active WHERE id = ?", [$id]);
            Response::json(['success' => true]);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
