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
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'delete_row':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) throw new Exception('Invalid JSON');
            $tableName = $input['table'] ?? '';
            $rowData = $input['row_data'] ?? [];
            if (empty($tableName) || empty($rowData)) throw new Exception('Missing parameters');
            
            $where_conditions = [];
            $whereParams = [];
            foreach ($rowData as $col => $val) {
                $where_conditions[] = "`$col` = ?";
                $whereParams[] = $val;
            }
            $sql = "DELETE FROM `$tableName` WHERE " . implode(' AND ', $where_conditions) . " LIMIT 1";
            $affected = Database::execute($sql, $whereParams);
            $affected > 0 ? Response::success(['message' => 'Deleted']) : Response::error('Delete failed', null, 404);
            break;

        case WF_Constants::ACTION_UPDATE_CELL:
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) throw new Exception('Invalid JSON');
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
            if (!$table) throw new Exception('Table required');
            Response::success([
                'structure' => Database::queryAll("DESCRIBE `$table`"),
                'rowCount' => Database::queryOne("SELECT COUNT(*) as c FROM `$table`")['c'] ?? 0,
                'status' => Database::queryOne("SHOW TABLE STATUS WHERE Name = ?", [$table])
            ]);
            break;

        case WF_Constants::ACTION_TABLE_DATA:
            $table = $_GET['table'] ?? '';
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);
            if (!$table) throw new Exception('Table required');
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
