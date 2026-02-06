<?php
/**
 * Global Color and Size Management API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/global_management/manager.php';

AuthHelper::requireAdmin();

try {
    Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');

    switch ($action) {
        case 'get_global_colors':
            Response::json(['success' => true, 'colors' => get_global_colors($_GET['category'] ?? '')]);
            break;

        case 'add_global_color':
            Response::json(['success' => true, 'color_id' => handle_add_global_color($input)['color_id']]);
            break;

        case 'get_global_sizes':
            Response::json(['success' => true, 'sizes' => get_global_sizes($_GET['category'] ?? '')]);
            break;

        case 'assign_sizes_to_item':
            handle_assign_sizes($input);
            Response::json(['success' => true]);
            break;

        case 'get_global_genders':
            Response::json(['success' => true, 'genders' => get_global_genders()]);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
