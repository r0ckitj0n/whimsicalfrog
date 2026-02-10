<?php
/**
 * Database Tables Management API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database/tables_manager.php';
requireAdmin(true);

try {
    Database::getInstance();
    $isValidIdentifier = static function ($identifier) {
        return is_string($identifier) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) === 1;
    };
    $tableExists = static function ($tableName) {
        $row = Database::queryOne(
            "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
            [$tableName]
        );
        return ((int)($row['c'] ?? 0)) > 0;
    };
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $allowedActions = [
        'delete_row',
        WF_Constants::ACTION_UPDATE_CELL,
        WF_Constants::ACTION_LIST_TABLES,
        WF_Constants::ACTION_TABLE_INFO,
        WF_Constants::ACTION_TABLE_DATA,
        WF_Constants::ACTION_GET_DOCUMENTATION
    ];
    if (!in_array($action, $allowedActions, true)) {
        Response::error('Invalid action', null, 400);
    }

    switch ($action) {
        case 'delete_row':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                throw new Exception('Invalid JSON');
            }
            $tableName = $input['table'] ?? '';
            $rowData = $input['row_data'] ?? [];
            if (!$isValidIdentifier($tableName) || empty($rowData) || !is_array($rowData)) {
                throw new Exception('Missing or invalid parameters');
            }
            if (!$tableExists($tableName)) {
                throw new Exception('Unknown table');
            }

            $where_conditions = [];
            $whereParams = [];
            foreach ($rowData as $col => $val) {
                if (!$isValidIdentifier($col)) {
                    throw new Exception('Invalid row identifier');
                }
                $where_conditions[] = "`$col` = ?";
                $whereParams[] = $val;
            }
            $sql = "DELETE FROM `$tableName` WHERE " . implode(' AND ', $where_conditions) . " LIMIT 1";
            $affected = Database::execute($sql, $whereParams);
            $affected > 0 ? Response::success(['message' => 'Deleted']) : Response::error('Delete failed', null, 404);
            break;

        case WF_Constants::ACTION_UPDATE_CELL:
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                throw new Exception('Invalid JSON');
            }
            $affected = handle_update_cell($input);
            $affected > 0 ? Response::success(['message' => 'Updated']) : Response::noChanges();
            break;

        case WF_Constants::ACTION_LIST_TABLES:
            $rows = Database::queryAll("SHOW TABLES");
            $tables = array_column($rows, array_key_first($rows[0] ?? ['Tables_in_db' => null]));
            Response::success(['tables' => $tables]);
            break;

        case WF_Constants::ACTION_TABLE_INFO:
            $table = $_GET['table'] ?? '';
            if (!$isValidIdentifier($table) || !$tableExists($table)) {
                throw new Exception('Table required');
            }
            Response::success([
                'structure' => Database::queryAll("DESCRIBE `$table`"),
                'rowCount' => Database::queryOne("SELECT COUNT(*) as c FROM `$table`")['c'] ?? 0,
                'status' => Database::queryOne("SHOW TABLE STATUS WHERE Name = ?", [$table])
            ]);
            break;

        case WF_Constants::ACTION_TABLE_DATA:
            $table = $_GET['table'] ?? '';
            $limit = max(1, min((int)($_GET['limit'] ?? 50), 500));
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            if (!$isValidIdentifier($table) || !$tableExists($table)) {
                throw new Exception('Table required');
            }
            $sql = "SELECT * FROM `$table` LIMIT $limit OFFSET $offset";
            Response::success(['data' => Database::queryAll($sql)]);
            break;

        case WF_Constants::ACTION_GET_DOCUMENTATION:
            Response::success(['documentation' => getTableDocumentation()]);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
