<?php
/**
 * Brand Voice Options API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/helpers/BrandVoiceHelper.php';

AuthHelper::requireAdmin();

try {
    Database::getInstance();
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_all':
            Response::json(['success' => true, 'options' => Database::queryAll("SELECT * FROM brand_voice_options ORDER BY display_order, label")]);
            break;
        case 'get_active':
            Response::json(['success' => true, 'options' => Database::queryAll("SELECT * FROM brand_voice_options WHERE is_active = 1 ORDER BY display_order, label")]);
            break;
        case 'add':
            $input = json_decode(file_get_contents('php://input'), true);
            $v = trim($input['value'] ?? ''); $l = trim($input['label'] ?? '');
            if (!$v || !$l) Response::error('Value and label are required', null, 400);
            try {
                Database::execute("INSERT INTO brand_voice_options (value, label, description, display_order) VALUES (?, ?, ?, ?)", [$v, $l, trim($input['description'] ?? ''), (int)($input['display_order'] ?? 0)]);
                Response::json(['success' => true, 'message' => 'Added successfully']);
            } catch (PDOException $e) { if ($e->getCode() == 23000) Response::error('Already exists', null, 409); else throw $e; }
            break;
        case 'update':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0 || !($v = trim($input['value'] ?? '')) || !($l = trim($input['label'] ?? ''))) Response::error('Missing required fields', null, 400);
            $affected = Database::execute("UPDATE brand_voice_options SET value = ?, label = ?, description = ?, is_active = ?, display_order = ? WHERE id = ?", [$v, $l, trim($input['description'] ?? ''), isset($input['is_active']) ? (bool)$input['is_active'] : true, (int)($input['display_order'] ?? 0), $id]);
            if ($affected > 0) Response::json(['success' => true, 'message' => 'Updated']); else Response::error('Not found', null, 404);
            break;
        case 'delete':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) Response::error('ID required', null, 400);
            if (Database::execute("DELETE FROM brand_voice_options WHERE id = ?", [$id]) > 0) Response::json(['success' => true]); else Response::error('Not found', null, 404);
            break;
        case 'get_item_preferences':
            $sku = $_GET['sku'] ?? '';
            if (!$sku) Response::error('SKU required', null, 400);
            $prefs = Database::queryOne("SELECT * FROM item_marketing_preferences WHERE sku = ?", [$sku]);
            if (!$prefs) {
                $settings = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai' AND setting_key IN ('ai_brand_voice', 'ai_content_tone')");
                $dict = []; foreach($settings as $r) $dict[$r['setting_key']] = $r['setting_value'];
                $prefs = ['sku' => $sku, 'brand_voice' => $dict['ai_brand_voice'] ?? '', 'content_tone' => $dict['ai_content_tone'] ?? 'professional', 'is_default' => true];
            } else $prefs['is_default'] = false;
            Response::json(['success' => true, 'preferences' => $prefs]);
            break;
        case 'save_item_preferences':
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['sku'])) Response::error('SKU required', null, 400);
            if (!Database::queryOne("SELECT sku FROM items WHERE sku = ?", [$input['sku']])) Response::error('Item not found', null, 404);
            Database::execute("INSERT INTO item_marketing_preferences (sku, brand_voice, content_tone) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE brand_voice = VALUES(brand_voice), content_tone = VALUES(content_tone), updated_at = CURRENT_TIMESTAMP", [$input['sku'], trim($input['brand_voice'] ?? ''), trim($input['content_tone'] ?? '')]);
            Response::json(['success' => true]);
            break;
        case 'initialize_defaults':
            Response::json(array_merge(['success' => true], BrandVoiceHelper::initializeDefaults()));
            break;
        default:
            Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) { Response::serverError('Internal error'); }
