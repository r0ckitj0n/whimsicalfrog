<?php
/**
 * Social Accounts API
 * Full CRUD API for social media account management with connection verification.
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/secret_store.php';
require_once __DIR__ . '/../includes/social/manager.php';

AuthHelper::requireAdmin();

$action = $_GET['action'] ?? $_POST['action'] ?? (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'list' : 'update');

try {
    Database::getInstance();

    switch ($action) {
        case 'list':
            echo json_encode(['success' => true, 'accounts' => fetch_social_accounts()]);
            break;

        case 'get':
            $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0)
                Response::error('ID required', null, 400);
            $account = fetch_social_account($id);
            if (!$account)
                Response::error('Not found', null, 404);
            echo json_encode(['success' => true, 'account' => $account]);
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $result = create_social_account($input);
            echo json_encode($result);
            break;

        case 'update':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            handle_social_update($input);
            echo json_encode(['success' => true, 'message' => 'Updated']);
            break;

        case 'delete':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
            if ($id <= 0)
                Response::error('ID required', null, 400);
            Database::execute("DELETE FROM social_accounts WHERE id = ?", [$id]);
            secret_delete('social_account_token_' . $id);
            secret_delete('social_account_refresh_' . $id);
            echo json_encode(['success' => true, 'message' => 'Deleted']);
            break;

        case 'verify':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
            if ($id <= 0)
                Response::error('ID required', null, 400);
            $result = verify_social_connection($id);
            echo json_encode($result);
            break;

        case 'refresh':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
            if ($id <= 0)
                Response::error('ID required', null, 400);
            $result = refresh_social_token($id);
            echo json_encode($result);
            break;

        case 'providers':
            echo json_encode(['success' => true, 'providers' => get_social_providers()]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

