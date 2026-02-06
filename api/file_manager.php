<?php
/**
 * File Manager API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/files/manager.php';

// Change working directory to project root
chdir(dirname(__DIR__));

$isAdmin = (isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === WF_Constants::ROLE_ADMIN) || 
           (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false);

if (!$isAdmin) {
    Response::forbidden('Admin access required');
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? ($input['action'] ?? '');

    switch ("$method:$action") {
        case "GET:" . WF_Constants::ACTION_LIST:
            Response::json(listDirectory($_GET['path'] ?? ''));
            break;

        case "GET:" . WF_Constants::ACTION_READ:
            $res = readFileContent($_GET['path'] ?? '');
            if ($res['success']) Response::json($res);
            else Response::error($res['error'], null, 400);
            break;

        case "POST:" . WF_Constants::ACTION_WRITE:
            $res = writeFileContent($input['path'] ?? '', $input['content'] ?? '');
            if ($res['success']) Response::success(['message' => 'Saved', 'bytes' => $res['bytes']]);
            else Response::error($res['error'], null, 400);
            break;

        case "DELETE:" . WF_Constants::ACTION_DELETE:
            $path = sanitizePath($_GET['path'] ?? '');
            if (!isPathAllowed($path)) throw new Exception('Access denied');
            if (is_dir($path)) rmdir($path);
            else unlink($path);
            Response::success(['message' => 'Deleted']);
            break;

        default:
            Response::error('Invalid action or method', null, 400);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
