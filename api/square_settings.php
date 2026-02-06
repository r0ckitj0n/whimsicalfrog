<?php
/**
 * Square Settings API - Conductor
 * Configuration and synchronization for Square integration.
 * Delegating logic to specialized helper classes in includes/square/helpers/
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';
require_once __DIR__ . '/../includes/secret_store.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/square/helpers/SquareConfigHelper.php';
require_once __DIR__ . '/../includes/square/helpers/SquareApiHelper.php';
require_once __DIR__ . '/../includes/square/helpers/SquareSyncHelper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    Database::getInstance();
} catch (Exception $e) {
    Response::serverError('Database connection failed: ' . $e->getMessage());
}

$action = $_GET['action'] ?? $_POST['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? '');

try {
    switch ($action) {
        case 'get_settings':
            Response::success(['settings' => SquareConfigHelper::getSettings()]);
            break;

        case 'save_settings':
            $input = json_decode(file_get_contents('php://input'), true);
            Response::json(SquareConfigHelper::saveSettings($input));
            break;

        case 'test_connection':
            $credentials = SquareConfigHelper::getResolvedCredentials();
            Response::json(SquareSyncHelper::testConnection($credentials));
            break;

        case 'sync_items':
            $credentials = SquareConfigHelper::getResolvedCredentials();
            Response::json(SquareSyncHelper::syncItems(Database::getInstance(), $credentials));
            break;

        case 'get_sync_status':
            Response::json(SquareSyncHelper::getSyncStatus());
            break;

        case 'import_from_square':
            $credentials = SquareConfigHelper::getResolvedCredentials();
            Response::json(SquareSyncHelper::importFromSquare($credentials));
            break;

        default:
            Response::error('Invalid action', 400);
    }
} catch (Exception $e) {
    error_log("Square Settings API Error: " . $e->getMessage());
    Response::error($e->getMessage(), 500);
}
