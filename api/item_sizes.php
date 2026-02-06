<?php
/**
 * Item Sizes Management API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/item_sizes/manager.php';

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$isAdmin = (isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === WF_Constants::ROLE_ADMIN) || 
           (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false);

try {
    Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

    switch ($action) {
        case WF_Constants::ACTION_GET_SIZES:
            $sku = $_GET['item_sku'] ?? '';
            if (empty($sku)) throw new Exception('SKU required');
            Response::json(['success' => true, 'sizes' => get_item_sizes($sku, $_GET['color_id'] ?? null, $_GET['gender'] ?? null)]);
            break;

        case WF_Constants::ACTION_GET_ALL_SIZES:
            if (!$isAdmin) Response::forbidden();
            $sku = $_GET['item_sku'] ?? '';
            if (empty($sku)) throw new Exception('SKU required');
            Response::json(['success' => true, 'sizes' => get_item_sizes($sku, $_GET['color_id'] ?? null, $_GET['gender'] ?? null, true)]);
            break;

        case WF_Constants::ACTION_ADD_SIZE:
            if (!$isAdmin) Response::forbidden();
            Response::updated(handle_add_size($input));
            break;

        case WF_Constants::ACTION_UPDATE_SIZE:
            if (!$isAdmin) Response::forbidden();
            Response::updated(handle_update_size($input));
            break;

        case WF_Constants::ACTION_DELETE_SIZE:
            if (!$isAdmin) Response::forbidden();
            $id = (int)($input['size_id'] ?? 0);
            $info = Database::queryOne("SELECT item_sku, color_id FROM item_sizes WHERE id = ?", [$id]);
            if (!$info) throw new Exception('Not found');
            Database::execute("DELETE FROM item_sizes WHERE id = ?", [$id]);
            if (!empty($info['color_id'])) syncColorStockWithSizes(Database::getInstance(), $info['color_id']);
            Response::updated(['new_total_stock' => syncTotalStockWithSizes(Database::getInstance(), $info['item_sku'])]);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
