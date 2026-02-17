<?php
/**
 * Attributes Management API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/attributes/manager.php';

AuthHelper::requireAdmin();

try {
    $db = Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? 'list');
    $validTypes = ['gender', 'size', 'color'];

    switch ($action) {
        case 'list':
            Response::json(['success' => true, 'attributes' => listAttributes($db)]);
            break;

        case 'add':
            $type = strtolower(trim((string)($input['type'] ?? '')));
            $value = trim((string)($input['value'] ?? ''));
            if (!in_array($type, $validTypes)) Response::error('Invalid type', null, 400);
            if ($value === '') Response::error('Value required', null, 400);

            // Logic remains in manager.php for complex table detection
            // For now, simple insert to attribute_values if legacy tables not handled
            ensure_attributes_table($db);
            $row = Database::queryOne('SELECT COALESCE(MAX(sort_order), -1) + 1 AS next FROM attribute_values WHERE type = ?', [$type]);
            Database::execute('INSERT INTO attribute_values (type, value, sort_order) VALUES (?, ?, ?)', [$type, $value, (int)$row['next']]);
            Response::success(['message' => 'Added']);
            break;

        case 'delete':
            $type = strtolower(trim((string)($input['type'] ?? '')));
            $value = trim((string)($input['value'] ?? ''));
            Database::execute('DELETE FROM attribute_values WHERE type = ? AND value = ?', [$type, $value]);
            Response::success(['message' => 'Deleted']);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
