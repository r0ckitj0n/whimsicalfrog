<?php
/**
 * Brand Voice Options API
 * Manages brand voice options for AI settings and marketing manager
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Use centralized authentication
// Admin authentication with token fallback for API access
$isAdmin = false;

// Check session authentication first
require_once __DIR__ . '/../includes/auth.php';
if (isAdminWithToken()) {
    $isAdmin = true;
}

// Admin token fallback for API access
if (!$isAdmin && isset($_GET['admin_token']) && $_GET['admin_token'] === 'whimsical_admin_2024') {
    $isAdmin = true;
}

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

// Authentication is handled by requireAdmin() above
$userData = getCurrentUser();

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Create brand_voice_options table if it doesn't exist
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS brand_voice_options (
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

    // Update item_marketing_preferences table to include brand_voice if not exists
    $alterTableSQL = "
    ALTER TABLE item_marketing_preferences 
    ADD COLUMN IF NOT EXISTS brand_voice VARCHAR(50) AFTER sku";

    try {
        Database::execute($alterTableSQL);
    } catch (PDOException $e) {
        // Column might already exist, ignore error
    }

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_all':
            getAllBrandVoiceOptions($pdo);
            break;
        case 'get_active':
            getActiveBrandVoiceOptions($pdo);
            break;
        case 'add':
            addBrandVoiceOption($pdo);
            break;
        case 'update':
            updateBrandVoiceOption($pdo);
            break;
        case 'delete':
            deleteBrandVoiceOption($pdo);
            break;
        case 'reorder':
            reorderBrandVoiceOptions($pdo);
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
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action specified']);
    }

} catch (Exception $e) {
    error_log("Error in brand_voice_options.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function getAllBrandVoiceOptions($pdo)
{
    $options = Database::queryAll("SELECT * FROM brand_voice_options ORDER BY display_order, label");

    echo json_encode(['success' => true, 'options' => $options]);
}

function getActiveBrandVoiceOptions($pdo)
{
    $options = Database::queryAll("SELECT * FROM brand_voice_options WHERE is_active = 1 ORDER BY display_order, label");

    echo json_encode(['success' => true, 'options' => $options]);
}

function addBrandVoiceOption($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $value = trim($input['value'] ?? '');
    $label = trim($input['label'] ?? '');
    $description = trim($input['description'] ?? '');
    $displayOrder = (int)($input['display_order'] ?? 0);

    if (empty($value) || empty($label)) {
        http_response_code(400);
        echo json_encode(['error' => 'Value and label are required']);
        return;
    }

    try {
        Database::execute("INSERT INTO brand_voice_options (value, label, description, display_order) VALUES (?, ?, ?, ?)", [$value, $label, $description, $displayOrder]);

        echo json_encode(['success' => true, 'message' => 'Brand voice option added successfully']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            http_response_code(409);
            echo json_encode(['error' => 'Brand voice option with this value already exists']);
        } else {
            throw $e;
        }
    }
}

function updateBrandVoiceOption($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $id = (int)($input['id'] ?? 0);
    $value = trim($input['value'] ?? '');
    $label = trim($input['label'] ?? '');
    $description = trim($input['description'] ?? '');
    $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;
    $displayOrder = (int)($input['display_order'] ?? 0);

    if ($id <= 0 || empty($value) || empty($label)) {
        http_response_code(400);
        echo json_encode(['error' => 'ID, value and label are required']);
        return;
    }

    $affected = Database::execute("UPDATE brand_voice_options SET value = ?, label = ?, description = ?, is_active = ?, display_order = ? WHERE id = ?", [$value, $label, $description, $isActive, $displayOrder, $id]);

    if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Brand voice option updated successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Brand voice option not found']);
    }
}

function deleteBrandVoiceOption($pdo)
{
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid ID is required']);
        return;
    }

    $affected = Database::execute("DELETE FROM brand_voice_options WHERE id = ?", [$id]);

    if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Brand voice option deleted successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Brand voice option not found']);
    }
}

function reorderBrandVoiceOptions($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $orders = $input['orders'] ?? [];

    if (empty($orders)) {
        http_response_code(400);
        echo json_encode(['error' => 'Orders array is required']);
        return;
    }

    foreach ($orders as $order) {
        $id = (int)($order['id'] ?? 0);
        $displayOrder = (int)($order['display_order'] ?? 0);

        if ($id > 0) {
            Database::execute("UPDATE brand_voice_options SET display_order = ? WHERE id = ?", [$displayOrder, $id]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Brand voice options reordered successfully']);
}

function getItemMarketingPreferences($pdo)
{
    $sku = $_GET['sku'] ?? '';

    if (empty($sku)) {
        http_response_code(400);
        echo json_encode(['error' => 'SKU is required']);
        return;
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

    echo json_encode(['success' => true, 'preferences' => $preferences]);
}

function saveItemMarketingPreferences($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    $sku = trim($input['sku'] ?? '');
    $brandVoice = trim($input['brand_voice'] ?? '');
    $contentTone = trim($input['content_tone'] ?? '');

    if (empty($sku)) {
        http_response_code(400);
        echo json_encode(['error' => 'SKU is required']);
        return;
    }

    // Check if item exists
    $exists = Database::queryOne("SELECT sku FROM items WHERE sku = ?", [$sku]);
    if (!$exists) {
        http_response_code(404);
        echo json_encode(['error' => 'Item not found']);
        return;
    }

    // Insert or update preferences
    Database::execute("
        INSERT INTO item_marketing_preferences (sku, brand_voice, content_tone) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        brand_voice = VALUES(brand_voice), 
        content_tone = VALUES(content_tone),
        updated_at = CURRENT_TIMESTAMP
    ", [$sku, $brandVoice, $contentTone]);

    echo json_encode(['success' => true, 'message' => 'Marketing preferences saved successfully']);
}

function initializeDefaultOptions($pdo)
{
    $defaultOptions = [
        ['friendly_approachable', 'Friendly & Approachable', 'Warm, welcoming, and easy to connect with', 1],
        ['professional_trustworthy', 'Professional & Trustworthy', 'Business-focused, reliable, and credible', 2],
        ['playful_fun', 'Playful & Fun', 'Lighthearted, entertaining, and engaging', 3],
        ['luxurious_premium', 'Luxurious & Premium', 'High-end, sophisticated, and exclusive', 4],
        ['casual_relaxed', 'Casual & Relaxed', 'Laid-back, informal, and comfortable', 5],
        ['authoritative_expert', 'Authoritative & Expert', 'Knowledgeable, confident, and commanding', 6],
        ['warm_personal', 'Warm & Personal', 'Intimate, caring, and heartfelt', 7],
        ['innovative_forward_thinking', 'Innovative & Forward-Thinking', 'Creative, cutting-edge, and progressive', 8],
        ['energetic_dynamic', 'Energetic & Dynamic', 'Enthusiastic, vibrant, and exciting', 9],
        ['sophisticated_elegant', 'Sophisticated & Elegant', 'Refined, polished, and tasteful', 10],
        ['conversational_natural', 'Conversational & Natural', 'Dialogue-like, personal, and engaging', 11],
        ['inspiring_motivational', 'Inspiring & Motivational', 'Uplifting, encouraging, and empowering', 12],
        ['minimalist_clean', 'Minimalist & Clean', 'Simple, straightforward, and uncluttered', 13],
        ['storytelling_narrative', 'Storytelling & Narrative', 'Story-driven, descriptive, and engaging', 14],
        ['humorous_witty', 'Humorous & Witty', 'Amusing, clever, and light-hearted', 15],
        ['sincere_authentic', 'Sincere & Authentic', 'Genuine, honest, and transparent', 16],
        ['bold_confident', 'Bold & Confident', 'Strong, assertive, and fearless', 17],
        ['nurturing_supportive', 'Nurturing & Supportive', 'Caring, helpful, and encouraging', 18]
    ];

    $inserted = 0;
    foreach ($defaultOptions as $option) {
        $result = Database::execute("INSERT IGNORE INTO brand_voice_options (value, label, description, display_order) VALUES (?, ?, ?, ?)", $option);
        if ($result > 0) {
            $inserted++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Initialized {$inserted} default brand voice options",
        'total_options' => count($defaultOptions)
    ]);
}

?> 