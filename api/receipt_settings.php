<?php
/**
 * Receipt Settings API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/receipt/settings_manager.php';
require_once __DIR__ . '/ai_providers.php';

$isAdmin = (isset($_SESSION['user']['role']) && strtolower($_SESSION['user']['role']) === WF_Constants::ROLE_ADMIN) ||
    (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false);

if (!$isAdmin) {
    Response::forbidden('Admin access required');
}

try {
    Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? WF_Constants::ACTION_GET_SETTINGS);

    // Map backend setting_type to frontend type
    $typeMap = [
        'shipping_method' => 'shipping',
        'item_count' => 'items',
        'item_category' => 'categories',
        'default' => 'default'
    ];
    $reverseTypeMap = array_flip($typeMap);

    switch ($action) {
        case 'get_settings':
            Response::json(['success' => true, 'settings' => getReceiptSettings()]);
            break;

        case 'list':
            // Flatten all grouped messages into a single array with frontend-compatible field names
            $grouped = getReceiptSettings();
            $messages = [];
            foreach ($grouped as $settingType => $items) {
                foreach ($items as $item) {
                    $messages[] = [
                        'id' => (int) $item['id'],
                        'type' => $typeMap[$settingType] ?? 'default',
                        'title' => $item['message_title'] ?? '',
                        'content' => $item['message_content'] ?? '',
                        'condition_value' => $item['condition_value'] ?? '',
                        'is_active' => true // Mark all existing messages as active
                    ];
                }
            }
            Response::json(['success' => true, 'messages' => $messages]);
            break;

        case 'create':
        case 'update':
            // Map frontend type back to backend setting_type
            $frontendType = $input['type'] ?? 'default';
            $settingType = $reverseTypeMap[$frontendType] ?? 'default';

            if ($action === 'update' && isset($input['id']) && $input['id'] > 0) {
                Database::execute("
                    UPDATE receipt_settings 
                    SET setting_type = ?, condition_key = 'manual', condition_value = ?, message_title = ?, message_content = ?
                    WHERE id = ?
                ", [
                    $settingType,
                    $input['condition_value'] ?? '',
                    $input['title'] ?? '',
                    $input['content'] ?? '',
                    (int) $input['id']
                ]);
            } else {
                Database::execute("
                    INSERT INTO receipt_settings (setting_type, condition_key, condition_value, message_title, message_content)
                    VALUES (?, 'manual', ?, ?, ?)
                ", [
                    $settingType,
                    $input['condition_value'] ?? '',
                    $input['title'] ?? '',
                    $input['content'] ?? ''
                ]);
            }
            Response::success(['message' => 'Message saved']);
            break;

        case WF_Constants::ACTION_UPDATE_SETTINGS:
            updateReceiptSettings($input);
            Response::success(['message' => 'Settings updated']);
            break;

        case 'generate_ai_message':
            if (!isset($input['context']))
                throw new Exception('Context required');
            Response::json(generateAIReceiptMessage($input['context']));
            break;

        case 'init_defaults':
            initializeDefaultReceiptSettings();
            Response::success(['message' => 'Defaults initialized']);
            break;

        case WF_Constants::ACTION_DELETE:
            $id = (int) ($input['id'] ?? ($_POST['id'] ?? $_GET['id'] ?? 0));
            if ($id <= 0)
                throw new Exception('Invalid ID');
            Database::execute("DELETE FROM receipt_settings WHERE id = ?", [$id]);
            Response::success(['message' => 'Deleted']);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::error($e->getMessage(), null, 400);
}
