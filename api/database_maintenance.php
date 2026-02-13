<?php
/**
 * Database Maintenance API - Conductor
 * Manages database connection settings, credentials, maintenance, and backups.
 * Delegating logic to specialized helper classes in includes/database/helpers/
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/database/helpers/DatabaseConfigHelper.php';
require_once __DIR__ . '/../includes/database/helpers/DatabaseStatsHelper.php';
require_once __DIR__ . '/../includes/database/helpers/DatabaseMaintenanceHelper.php';
require_once __DIR__ . '/../includes/database/helpers/DatabaseBackupHelper.php';
require_once __DIR__ . '/../includes/database/helpers/DatabaseSchemaHelper.php';
require_once __DIR__ . '/../includes/database/helpers/DatabaseImportHelper.php';

// Optional admin token bypass for automated deploys
function wf_is_token_valid(): bool {
    $provided = $_GET['admin_token'] ?? $_POST['admin_token'] ?? (json_decode(file_get_contents('php://input'), true)['admin_token'] ?? '');
    if ($provided === '') return false;
    $expected = getenv('WF_ADMIN_TOKEN') ?: (defined('WF_ADMIN_TOKEN') ? WF_ADMIN_TOKEN : '');
    return $expected !== '' && hash_equals($expected, $provided);
}

if (!wf_is_token_valid()) {
    requireAdmin(true);
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? '';
$allowedActions = [
    'get_config',
    'test_connection',
    'update_config',
    'get_connection_stats',
    'analyze_size',
    'performance_monitor',
    'optimize_tables',
    'analyze_indexes',
    'cleanup_database',
    'repair_tables',
    'check_foreign_keys',
    'list_backups',
    'create_backup',
    'drop_all_tables',
    'restore_database',
    'get_schema',
    'initialize_database',
    'import_sql',
    'import_csv',
    'import_json',
    'export_tables'
];
if (!in_array($action, $allowedActions, true)) {
    Response::error('Invalid action', null, 400);
}
$mutatingActions = [
    'update_config',
    'optimize_tables',
    'analyze_indexes',
    'cleanup_database',
    'repair_tables',
    'create_backup',
    'drop_all_tables',
    'restore_database',
    'initialize_database',
    'import_sql',
    'import_csv',
    'import_json'
];
if (in_array($action, $mutatingActions, true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::methodNotAllowed('Method not allowed');
}

try {
    switch ($action) {
        // --- Configuration ---
        case 'get_config':
            Response::json(DatabaseConfigHelper::getConfig());
            break;
        case 'test_connection':
            Response::json(DatabaseConfigHelper::testConnection($input));
            break;
        case 'update_config':
            Response::json(DatabaseConfigHelper::updateConfig($input));
            break;

        // --- Statistics & Monitoring ---
        case 'get_connection_stats':
            Response::json(DatabaseStatsHelper::getConnectionStats());
            break;
        case 'analyze_size':
            Response::json(DatabaseStatsHelper::analyzeDatabaseSize());
            break;
        case 'performance_monitor':
            Response::json(DatabaseStatsHelper::performanceMonitor());
            break;

        // --- Maintenance ---
        case 'optimize_tables':
            Response::json(DatabaseMaintenanceHelper::optimizeTables());
            break;
        case 'analyze_indexes':
            Response::json(DatabaseMaintenanceHelper::analyzeIndexes());
            break;
        case 'cleanup_database':
            Response::json(DatabaseMaintenanceHelper::cleanupDatabase());
            break;
        case 'repair_tables':
            Response::json(DatabaseMaintenanceHelper::repairTables());
            break;
        case 'check_foreign_keys':
            Response::json(DatabaseMaintenanceHelper::checkForeignKeys());
            break;

        // --- Backups & Restore ---
        case 'list_backups':
            Response::json(DatabaseBackupHelper::listBackups());
            break;
        case 'create_backup':
            Response::json(DatabaseBackupHelper::createBackup());
            break;
        case 'drop_all_tables':
            $skipRaw = $_GET['skip_tables'] ?? $_POST['skip_tables'] ?? ($input['skip_tables'] ?? '');
            $skip = [];
            if (is_array($skipRaw)) {
                $skip = array_values(array_filter(array_map('strval', $skipRaw), static fn($v) => $v !== ''));
            } elseif (is_string($skipRaw) && trim($skipRaw) !== '') {
                $skip = array_values(array_filter(array_map('trim', explode(',', $skipRaw)), static fn($v) => $v !== ''));
            }
            Response::json(DatabaseBackupHelper::dropAllTables($skip));
            break;
        case 'restore_database':
            Response::json(DatabaseBackupHelper::restoreDatabase($input, $_FILES));
            break;

        // --- Schema ---
        case 'get_schema':
            Response::json(DatabaseSchemaHelper::getDatabaseSchema());
            break;
        case 'initialize_database':
            Response::json(DatabaseSchemaHelper::initializeDatabase());
            break;

        // --- Import/Export ---
        case 'import_sql':
            Response::json(DatabaseImportHelper::importSQL($input['sql_content'] ?? ''));
            break;
        case 'import_csv':
            Response::json(DatabaseImportHelper::importCSV($input));
            break;
        case 'import_json':
            Response::json(DatabaseImportHelper::importJSON($input));
            break;
        case 'export_tables':
            DatabaseImportHelper::exportTables($_GET['tables'] ?? '');
            break;

        default:
            Response::error('Invalid action', 400);
    }
} catch (Exception $e) {
    error_log("Database Maintenance API Error: " . $e->getMessage());
    Response::error($e->getMessage(), 500);
}
