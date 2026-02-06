<?php
/**
 * Action Icons Management API
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

@ini_set('display_errors', 0);
@ini_set('html_errors', 0);

header('Content-Type: application/json');

if (!((strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) || (strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false))) {
    AuthHelper::requireAdmin();
}

try {
    $db = Database::getInstance();

    // Check if table exists
    $tableExists = Database::queryOne("SHOW TABLES LIKE 'action_icons'");

    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if (!$tableExists) {
        Response::success(['icons' => [], 'success' => true]);
        exit;
    }

    switch ($action) {
        case 'list':
            $icons = Database::queryAll('SELECT * FROM action_icons ORDER BY id ASC');
            Response::success(['icons' => $icons, 'success' => true]);
            break;

        default:
            Response::error('Action not implemented');
    }

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
