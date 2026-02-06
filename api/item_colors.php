<?php
/**
 * Item Colors Management API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/session_bootstrap.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/item_colors/manager.php';

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$isAdmin = (isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === WF_Constants::ROLE_ADMIN) || 
           (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false);

try {
    $pdo = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

    switch ($action) {
        case WF_Constants::ACTION_GET_COLORS:
            $sku = $_GET['item_sku'] ?? '';
            if (empty($sku)) throw new Exception('SKU required');
            Response::json(['success' => true, 'colors' => get_item_colors($sku)]);
            break;

        case WF_Constants::ACTION_GET_ALL_COLORS:
            if (!$isAdmin) Response::forbidden();
            $sku = $_GET['item_sku'] ?? '';
            if (empty($sku)) throw new Exception('SKU required');
            Response::json(['success' => true, 'colors' => get_item_colors($sku, true)]);
            break;

        case WF_Constants::ACTION_ADD_COLOR:
            if (!$isAdmin) Response::forbidden();
            Response::updated(handle_add_item_color($input));
            break;

        case WF_Constants::ACTION_UPDATE_COLOR:
            if (!$isAdmin) Response::forbidden();
            Response::updated(handle_update_item_color($input));
            break;

        case WF_Constants::ACTION_DELETE_COLOR:
            if (!$isAdmin) Response::forbidden();
            $id = (int)($input['color_id'] ?? 0);
            $info = Database::queryOne("SELECT item_sku FROM item_colors WHERE id = ?", [$id]);
            if (!$info) throw new Exception('Not found');
            Database::execute("DELETE FROM item_colors WHERE id = ?", [$id]);
            require_once __DIR__ . '/../includes/stock_manager.php';
            Response::updated(['new_total_stock' => syncTotalStockWithColors($pdo, $info['item_sku'])]);
            break;

        case WF_Constants::ACTION_CHECK_AVAILABILITY:
            $sku = $_GET['item_sku'] ?? '';
            $color = $_GET['color_name'] ?? '';
            if (empty($sku)) throw new Exception('SKU required');
            
            if (empty($color)) {
                $res = Database::queryOne("SELECT COUNT(*) as cnt FROM item_colors WHERE item_sku = ? AND is_active = 1", [$sku]);
                Response::json(['success' => true, 'has_colors' => $res['cnt'] > 0]);
            } else {
                $res = Database::queryOne("SELECT stock_level FROM item_colors WHERE item_sku = ? AND color_name = ? AND is_active = 1", [$sku, $color]);
                Response::json(['success' => (bool)$res, 'stock_level' => $res['stock_level'] ?? 0]);
            }
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
