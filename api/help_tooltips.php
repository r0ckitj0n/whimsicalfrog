<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';

$action = $_GET['action'] ?? 'get';
$adminOnlyActions = ['update', 'create', 'delete', 'list_all', 'upsert'];

if (in_array($action, $adminOnlyActions)) {
    AuthHelper::requireAdmin();
}

try {
    Database::getInstance();

    switch ($action) {
        case 'get':
        case 'get_tooltips':
            $pageContext = $_GET['page_context'] ?? $_GET['page'] ?? null;
            $elementId = $_GET['element_id'] ?? null;
            $sql = "SELECT * FROM help_tooltips WHERE is_active = 1";
            $params = [];
            if ($pageContext) {
                $sql .= " AND (page_context = ? OR page_context = 'common')";
                $params[] = $pageContext;
            }
            if ($elementId) {
                $sql .= " AND element_id = ?";
                $params[] = $elementId;
            }
            $sql .= " ORDER BY page_context, element_id";
            Response::success(['tooltips' => Database::queryAll($sql, $params)]);
            break;

        case 'list_all':
            Response::success(['tooltips' => Database::queryAll("SELECT * FROM help_tooltips ORDER BY page_context, element_id")]);
            break;

        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "UPDATE help_tooltips SET element_id = ?, page_context = ?, title = ?, content = ?, position = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $res = Database::execute($sql, [
                $data['element_id'], 
                $data['page_context'], 
                $data['title'], 
                $data['content'], 
                $data['position'] ?? 'top', 
                $data['is_active'] ?? 1, 
                $data['id']
            ]);
            $res > 0 ? Response::updated() : Response::noChanges();
            break;

        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "INSERT INTO help_tooltips (element_id, page_context, title, content, position, is_active) VALUES (?, ?, ?, ?, ?, ?)";
            $res = Database::execute($sql, [
                $data['element_id'], 
                $data['page_context'], 
                $data['title'], 
                $data['content'], 
                $data['position'] ?? 'top', 
                $data['is_active'] ?? 1
            ]);
            Response::success(['id' => Database::lastInsertId()]);
            break;

        case 'upsert':
            $data = json_decode(file_get_contents('php://input'), true);
            $sql = "INSERT INTO help_tooltips (element_id, page_context, title, content, position, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        page_context=VALUES(page_context), 
                        title=VALUES(title), 
                        content=VALUES(content), 
                        position=VALUES(position), 
                        is_active=VALUES(is_active), 
                        updated_at=CURRENT_TIMESTAMP";
            Database::execute($sql, [
                $data['element_id'], 
                $data['page_context'], 
                $data['title'], 
                $data['content'], 
                $data['position'] ?? 'top', 
                $data['is_active'] ?? 1
            ]);
            Response::success(['message' => 'Tooltip upserted']);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
