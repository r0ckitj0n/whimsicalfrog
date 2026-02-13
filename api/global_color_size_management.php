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

        case 'update_global_color':
            handle_update_global_color($input);
            Response::updated();
            break;

        case 'delete_global_color':
            handle_delete_global_color($input);
            Response::updated();
            break;

        case 'get_global_sizes':
            Response::json(['success' => true, 'sizes' => get_global_sizes($_GET['category'] ?? '')]);
            break;

        case 'add_global_size':
            Response::json(['success' => true, 'size_id' => handle_add_global_size($input)['size_id']]);
            break;

        case 'update_global_size':
            handle_update_global_size($input);
            Response::updated();
            break;

        case 'delete_global_size':
            handle_delete_global_size($input);
            Response::updated();
            break;

        case 'assign_sizes_to_item':
            handle_assign_sizes($input);
            Response::json(['success' => true]);
            break;

        case 'get_global_genders':
            Response::json(['success' => true, 'genders' => get_global_genders()]);
            break;

        case 'add_global_gender':
            Response::json(['success' => true, 'gender_id' => handle_add_global_gender($input)['gender_id']]);
            break;

        case 'update_global_gender':
            handle_update_global_gender($input);
            Response::updated();
            break;

        case 'delete_global_gender':
            handle_delete_global_gender($input);
            Response::updated();
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
