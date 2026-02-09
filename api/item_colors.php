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

function wf_colors_is_admin(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        return true;
    }

    $user = $_SESSION['user'] ?? null;
    if (is_string($user)) {
        $decoded = json_decode($user, true);
        if (is_array($decoded)) {
            $user = $decoded;
            $_SESSION['user'] = $decoded; // normalize shape for subsequent calls
        }
    }

    if (!is_array($user)) {
        return false;
    }

    $role = strtolower(trim((string) ($user['role'] ?? '')));
    return in_array($role, [
        WF_Constants::ROLE_ADMIN,
        WF_Constants::ROLE_SUPERADMIN,
        WF_Constants::ROLE_DEVOPS,
        'administrator'
    ], true);
}

$isAdmin = wf_colors_is_admin();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

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
            $sku = $_GET['item_sku'] ?? '';
            if (empty($sku)) throw new Exception('SKU required');
            // Graceful fallback: if admin session context is unavailable, return active colors
            // instead of failing the entire Item Information modal with 403.
            $includeInactive = $isAdmin;
            Response::json(['success' => true, 'colors' => get_item_colors($sku, $includeInactive)]);
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
