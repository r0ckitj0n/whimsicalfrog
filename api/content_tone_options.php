<?php
/**
 * Content Tone Options API
 * Manages content tone options for AI settings and marketing manager
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';

// Centralized admin check
AuthHelper::requireAdmin();

// Authentication is handled by requireAdmin() above
$userData = getCurrentUser();

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Create content_tone_options table if it doesn't exist
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS content_tone_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        value VARCHAR(50) NOT NULL UNIQUE,
        label VARCHAR(100) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    Database::execute($createTableSQL);

    // Create item_marketing_preferences table for per-item settings
    $createItemPrefsSQL = "
    CREATE TABLE IF NOT EXISTS item_marketing_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sku VARCHAR(50) NOT NULL UNIQUE,
        brand_voice VARCHAR(50),
        content_tone VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (sku) REFERENCES items(sku) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    Database::execute($createItemPrefsSQL);

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_all':
            getAllContentToneOptions($pdo);
            break;
        case 'get_active':
            getActiveContentToneOptions($pdo);
            break;
        case 'add':
            addContentToneOption($pdo);
            break;
        case 'update':
            updateContentToneOption($pdo);
            break;
        case 'delete':
            deleteContentToneOption($pdo);
            break;
        case 'reorder':
            reorderContentToneOptions($pdo);
            break;
        case 'get_item_preferences':
            getItemMarketingPreferences($pdo);
            break;
        case 'save_item_preferences':
            saveItemMarketingPreferences($pdo);
            break;
        case 'initialize_defaults':
            initializeDefaultOptions($pdo);
            break;
        default:
            Response::error('Invalid action specified', null, 400);
    }

} catch (Exception $e) {
    error_log("Error in content_tone_options.php: " . $e->getMessage());
    Response::serverError('Internal server error');
}

function getAllContentToneOptions($pdo)
{
    $options = Database::queryAll("SELECT * FROM content_tone_options ORDER BY display_order, label");

    Response::json(['success' => true, 'options' => $options]);
}

function getActiveContentToneOptions($pdo)
{
    $options = Database::queryAll("SELECT * FROM content_tone_options WHERE is_active = 1 ORDER BY display_order, label");

    Response::json(['success' => true, 'options' => $options]);
}

function addContentToneOption($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $value = trim($input['value'] ?? '');
    $label = trim($input['label'] ?? '');
    $description = trim($input['description'] ?? '');
    $displayOrder = (int)($input['display_order'] ?? 0);

    if (empty($value) || empty($label)) {
        Response::error('Value and label are required', null, 400);
    }

    try {
        Database::execute("INSERT INTO content_tone_options (value, label, description, display_order) VALUES (?, ?, ?, ?)", [$value, $label, $description, $displayOrder]);

        Response::json(['success' => true, 'message' => 'Content tone option added successfully']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            Response::error('Content tone option with this value already exists', null, 409);
        } else {
            throw $e;
        }
    }
}

function updateContentToneOption($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $id = (int)($input['id'] ?? 0);
    $value = trim($input['value'] ?? '');
    $label = trim($input['label'] ?? '');
    $description = trim($input['description'] ?? '');
    $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;
    $displayOrder = (int)($input['display_order'] ?? 0);

    if ($id <= 0 || empty($value) || empty($label)) {
        Response::error('ID, value and label are required', null, 400);
    }

    $affected = Database::execute("UPDATE content_tone_options SET value = ?, label = ?, description = ?, is_active = ?, display_order = ? WHERE id = ?", [$value, $label, $description, $isActive, $displayOrder, $id]);

    if ($affected > 0) {
        Response::json(['success' => true, 'message' => 'Content tone option updated successfully']);
    } else {
        Response::error('Content tone option not found', null, 404);
    }
}

function deleteContentToneOption($pdo)
{
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        Response::error('Valid ID is required', null, 400);
    }

    $affected = Database::execute("DELETE FROM content_tone_options WHERE id = ?", [$id]);

    if ($affected > 0) {
        Response::json(['success' => true, 'message' => 'Content tone option deleted successfully']);
    } else {
        Response::error('Content tone option not found', null, 404);
    }
}

function reorderContentToneOptions($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $orders = $input['orders'] ?? [];

    if (empty($orders)) {
        Response::error('Orders array is required', null, 400);
    }

    foreach ($orders as $order) {
        $id = (int)($order['id'] ?? 0);
        $displayOrder = (int)($order['display_order'] ?? 0);

        if ($id > 0) {
            Database::execute("UPDATE content_tone_options SET display_order = ? WHERE id = ?", [$displayOrder, $id]);
        }
    }

    Response::json(['success' => true, 'message' => 'Content tone options reordered successfully']);
}

function getItemMarketingPreferences($pdo)
{
    $sku = $_GET['sku'] ?? '';

    if (empty($sku)) {
        Response::error('SKU is required', null, 400);
    }

    $preferences = Database::queryOne("SELECT * FROM item_marketing_preferences WHERE sku = ?", [$sku]);

    // If no preferences exist, get global defaults
    if (!$preferences) {
        $rows = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai' AND setting_key IN ('ai_brand_voice', 'ai_content_tone')");
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }

        $preferences = [
            'sku' => $sku,
            'brand_voice' => $settings['ai_brand_voice'] ?? '',
            'content_tone' => $settings['ai_content_tone'] ?? 'professional',
            'is_default' => true
        ];
    } else {
        $preferences['is_default'] = false;
    }

    Response::json(['success' => true, 'preferences' => $preferences]);
}

function saveItemMarketingPreferences($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $sku = trim($input['sku'] ?? '');
    $brandVoice = trim($input['brand_voice'] ?? '');
    $contentTone = trim($input['content_tone'] ?? '');

    if (empty($sku)) {
        Response::error('SKU is required', null, 400);
    }

    // Check if item exists
    $exists = Database::queryOne("SELECT sku FROM items WHERE sku = ?", [$sku]);
    if (!$exists) {
        Response::error('Item not found', null, 404);
    }

    // Insert or update preferences
    Database::execute("INSERT INTO item_marketing_preferences (sku, brand_voice, content_tone) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE brand_voice = VALUES(brand_voice), content_tone = VALUES(content_tone), updated_at = CURRENT_TIMESTAMP", [$sku, $brandVoice, $contentTone]);

    Response::json(['success' => true, 'message' => 'Marketing preferences saved successfully']);
}

function initializeDefaultOptions($pdo)
{
    $defaultOptions = [
        ['professional', 'Professional', 'Clear, authoritative, and business-focused tone', 1],
        ['friendly', 'Friendly', 'Warm, approachable, and conversational tone', 2],
        ['casual', 'Casual', 'Relaxed, informal, and easy-going tone', 3],
        ['energetic', 'Energetic', 'Dynamic, enthusiastic, and exciting tone', 4],
        ['sophisticated', 'Sophisticated', 'Elegant, refined, and polished tone', 5],
        ['playful', 'Playful', 'Fun, lighthearted, and entertaining tone', 6],
        ['urgent', 'Urgent', 'Time-sensitive, compelling, and action-oriented tone', 7],
        ['informative', 'Informative', 'Educational, detailed, and fact-focused tone', 8],
        ['persuasive', 'Persuasive', 'Convincing, compelling, and sales-oriented tone', 9],
        ['emotional', 'Emotional', 'Heartfelt, touching, and sentiment-driven tone', 10],
        ['conversational', 'Conversational', 'Natural, dialogue-like, and personal tone', 11],
        ['authoritative', 'Authoritative', 'Expert, confident, and commanding tone', 12],
        ['inspiring', 'Inspiring', 'Motivational, uplifting, and encouraging tone', 13],
        ['humorous', 'Humorous', 'Witty, amusing, and light-hearted tone', 14],
        ['minimalist', 'Minimalist', 'Simple, concise, and straightforward tone', 15],
        ['luxurious', 'Luxurious', 'Premium, exclusive, and high-end tone', 16],
        ['technical', 'Technical', 'Precise, detailed, and specification-focused tone', 17],
        ['storytelling', 'Storytelling', 'Narrative-driven, engaging, and descriptive tone', 18]
    ];

    $inserted = 0;
    foreach ($defaultOptions as $option) {
        $result = Database::execute("INSERT IGNORE INTO content_tone_options (value, label, description, display_order) VALUES (?, ?, ?, ?)", $option);
        if ($result > 0) {
            $inserted++;
        }
    }

    Response::json([
        'success' => true,
        'message' => "Initialized {$inserted} default content tone options",
        'total_options' => count($defaultOptions)
    ]);
}

?> 