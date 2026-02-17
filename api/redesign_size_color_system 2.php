<?php
/**
 * Redesign Size/Color System API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/inventory/restructure_helper.php';

AuthHelper::requireAdmin();

try {
    Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

    switch ($action) {
        case 'check_if_backwards':
            $sku = $_GET['item_sku'] ?? '';
            if (empty($sku)) throw new Exception('SKU required');
            Response::json(array_merge(['success' => true], analyze_item_structure($sku)));
            break;

        case 'migrate_to_new_structure':
            $sku = $input['item_sku'] ?? '';
            if (empty($sku)) throw new Exception('SKU required');
            $res = migrate_item_structure($sku, $input['new_structure'] ?? []);
            Response::json(array_merge(['success' => true], $res));
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
