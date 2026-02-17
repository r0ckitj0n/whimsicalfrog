<?php
/**
 * Item Sizes Management API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/item_sizes/manager.php';
require_once __DIR__ . '/../includes/item_sizes/stock_tools.php';

// Ensure we start the session using the canonical SessionManager configuration.
// This avoids "logged in on some endpoints but not others" when save_path/cookie params drift.
if (class_exists('SessionManager')) {
    SessionManager::init();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

function wf_sizes_is_admin(): bool
{
    return AuthHelper::isAdmin()
        || AuthHelper::hasRole(WF_Constants::ROLE_SUPERADMIN)
        || AuthHelper::hasRole(WF_Constants::ROLE_DEVOPS);
}

$isAdmin = wf_sizes_is_admin();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');
    $allowedActions = [
        WF_Constants::ACTION_GET_SIZES,
        WF_Constants::ACTION_GET_ALL_SIZES,
        WF_Constants::ACTION_ADD_SIZE,
        WF_Constants::ACTION_UPDATE_SIZE,
        WF_Constants::ACTION_DELETE_SIZE,
        WF_Constants::ACTION_SYNC_STOCK,
        WF_Constants::ACTION_DISTRIBUTE_GENERAL_STOCK_EVENLY,
        WF_Constants::ACTION_ENSURE_COLOR_SIZES,
    ];
    if (!in_array($action, $allowedActions, true)) {
        Response::error('Invalid action', null, 400);
    }

    switch ($action) {
        case WF_Constants::ACTION_GET_SIZES:
            $sku = $_GET['item_sku'] ?? '';
            if (empty($sku)) throw new Exception('SKU required');
            $sizes = get_item_sizes($sku, $_GET['color_id'] ?? null, $_GET['gender'] ?? null);
            // Master stock mode: expose items.stock_quantity as the effective stock for every variation.
            // This keeps storefront selling limited by your "how many I can make" number.
            $row = Database::queryOne("SELECT COALESCE(stock_quantity, 0) AS stock_quantity FROM items WHERE sku = ? LIMIT 1", [$sku]);
            $master = (int) ($row['stock_quantity'] ?? 0);
            foreach ($sizes as &$s) {
                $s['stock_level'] = $master;
            }
            unset($s);
            Response::json(['success' => true, 'sizes' => $sizes]);
            break;

        case WF_Constants::ACTION_GET_ALL_SIZES:
            $sku = $_GET['item_sku'] ?? '';
            if (empty($sku)) throw new Exception('SKU required');
            // Graceful fallback: if admin session context is unavailable, return active sizes
            // instead of failing the entire Item Information modal with 403.
            $includeInactive = $isAdmin;
            Response::json(['success' => true, 'sizes' => get_item_sizes($sku, $_GET['color_id'] ?? null, $_GET['gender'] ?? null, $includeInactive)]);
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

        case WF_Constants::ACTION_SYNC_STOCK:
            if (!$isAdmin) Response::forbidden();
            $sku = (string)($input['item_sku'] ?? ($_GET['item_sku'] ?? ''));
            if ($sku === '') throw new Exception('SKU required');
            Response::updated(['new_total_stock' => wf_item_sizes_sync_stock(Database::getInstance(), $sku)]);
            break;

        case WF_Constants::ACTION_DISTRIBUTE_GENERAL_STOCK_EVENLY:
            if (!$isAdmin) Response::forbidden();
            $sku = (string)($input['item_sku'] ?? ($_GET['item_sku'] ?? ''));
            if ($sku === '') throw new Exception('SKU required');
            Response::updated(['new_total_stock' => wf_item_sizes_distribute_evenly(Database::getInstance(), $sku)]);
            break;

        case WF_Constants::ACTION_ENSURE_COLOR_SIZES:
            if (!$isAdmin) Response::forbidden();
            $sku = (string)($input['item_sku'] ?? ($_GET['item_sku'] ?? ''));
            if ($sku === '') throw new Exception('SKU required');
            $res = wf_item_sizes_ensure_color_sizes(Database::getInstance(), $sku);
            Response::updated($res);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
