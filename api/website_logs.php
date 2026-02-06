<?php
/**
 * Website Logs API - Conductor
 * Management, viewing, and ingestion of system and client logs.
 * Delegating logic to specialized helper classes in includes/logging/helpers/
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/response.php';

// Conductor Helpers
require_once __DIR__ . '/../includes/logging/helpers/LogMaintenanceHelper.php';
require_once __DIR__ . '/../includes/logging/helpers/LogQueryHelper.php';
require_once __DIR__ . '/../includes/logging/helpers/LogExportHelper.php';
require_once __DIR__ . '/../includes/logging/helpers/LogIngestHelper.php';

// Check admin authentication
try {
    AuthHelper::requireAdmin(403, 'Admin access required');
} catch (Throwable $e) {
    Response::json(['success' => false, 'error' => 'Admin access required'], 403);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    Database::getInstance();

    switch ($action) {
        case 'ingest_client_logs':
            $input = Response::getJsonInput();
            Response::json(LogIngestHelper::ingestClientLogs($input['entries'] ?? []));
            break;

        case 'list_logs':
            LogMaintenanceHelper::cleanupOldLogs(); // Auto-cleanup on list
            Response::success(['logs' => LogQueryHelper::buildAvailableLogs()]);
            break;

        case 'get_log':
            $type = $_GET['type'] ?? '';
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 50);
            $filters = $_GET; // Pass all query params as filters

            if (strpos($type, 'file:') === 0) {
                Response::json(array_merge(['success' => true, 'type' => $type], LogQueryHelper::getFileLogContent($type, $page, $limit)));
            } else {
                Response::json(array_merge(['success' => true, 'type' => $type], LogQueryHelper::getDatabaseLogContent($type, $page, $limit, $filters)));
            }
            break;

        case 'search_logs':
            $query = $_GET['query'] ?? '';
            $type = $_GET['type'] ?? '';
            Response::success([
                'results' => LogQueryHelper::searchLogs($query, $type, $_GET),
                'query' => $query
            ]);
            break;

        case 'clear_log':
            $type = $_POST['type'] ?? '';
            if (LogMaintenanceHelper::clearLog($type)) {
                Response::success(null, ucfirst(str_replace('_', ' ', $type)) . ' cleared successfully');
            } else {
                Response::error('Failed to clear log');
            }
            break;

        case 'download_log':
            LogExportHelper::downloadDatabaseLog($_GET['type'] ?? '', $_GET);
            break;

        case 'get_status':
            Response::success(['status' => LogQueryHelper::getLoggingStatus()]);
            break;

        case 'download':
            LogExportHelper::downloadAllLogs();
            break;

        case 'cleanup_old_logs':
            Response::success(['cleanup_result' => LogMaintenanceHelper::cleanupOldLogs()]);
            break;

        case 'distinct_email_types':
            Response::success(['types' => LogQueryHelper::getDistinctEmailTypes()]);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
