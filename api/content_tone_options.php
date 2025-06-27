<?php
/**
 * Content Tone Options API
 * Manages content tone options for AI settings and marketing manager
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/config.php';

// Authentication is handled by requireAdmin() above
$userData = getCurrentUser();

header('Content-Type: application/json');

// Use centralized authentication
requireAdmin();

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
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
    
    $pdo->exec($createTableSQL);
    
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
    
    $pdo->exec($createItemPrefsSQL);
    
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
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action specified']);
    }
    
} catch (Exception $e) {
    error_log("Error in content_tone_options.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function getAllContentToneOptions($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM content_tone_options ORDER BY display_order, label");
    $stmt->execute();
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'options' => $options]);
}

function getActiveContentToneOptions($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM content_tone_options WHERE is_active = 1 ORDER BY display_order, label");
    $stmt->execute();
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'options' => $options]);
}

function addContentToneOption($pdo) {
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
        $stmt = $pdo->prepare("INSERT INTO content_tone_options (value, label, description, display_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$value, $label, $description, $displayOrder]);
        
        echo json_encode(['success' => true, 'message' => 'Content tone option added successfully']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            http_response_code(409);
            echo json_encode(['error' => 'Content tone option with this value already exists']);
        } else {
            throw $e;
        }
    }
}

function updateContentToneOption($pdo) {
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
    
    $stmt = $pdo->prepare("UPDATE content_tone_options SET value = ?, label = ?, description = ?, is_active = ?, display_order = ? WHERE id = ?");
    $stmt->execute([$value, $label, $description, $isActive, $displayOrder, $id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Content tone option updated successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Content tone option not found']);
    }
}

function deleteContentToneOption($pdo) {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM content_tone_options WHERE id = ?");
    $stmt->execute([$id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Content tone option deleted successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Content tone option not found']);
    }
}

function reorderContentToneOptions($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $orders = $input['orders'] ?? [];
    
    if (empty($orders)) {
        http_response_code(400);
        echo json_encode(['error' => 'Orders array is required']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE content_tone_options SET display_order = ? WHERE id = ?");
    
    foreach ($orders as $order) {
        $id = (int)($order['id'] ?? 0);
        $displayOrder = (int)($order['display_order'] ?? 0);
        
        if ($id > 0) {
            $stmt->execute([$displayOrder, $id]);
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Content tone options reordered successfully']);
}

function getItemMarketingPreferences($pdo) {
    $sku = $_GET['sku'] ?? '';
    
    if (empty($sku)) {
        http_response_code(400);
        echo json_encode(['error' => 'SKU is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM item_marketing_preferences WHERE sku = ?");
    $stmt->execute([$sku]);
    $preferences = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no preferences exist, get global defaults
    if (!$preferences) {
        $stmt = $pdo->prepare("SELECT setting_value FROM business_settings WHERE category = 'ai' AND setting_key IN ('ai_brand_voice', 'ai_content_tone')");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
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

function saveItemMarketingPreferences($pdo) {
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
    $stmt = $pdo->prepare("SELECT sku FROM items WHERE sku = ?");
    $stmt->execute([$sku]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Item not found']);
        return;
    }
    
    // Insert or update preferences
    $stmt = $pdo->prepare("
        INSERT INTO item_marketing_preferences (sku, brand_voice, content_tone) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        brand_voice = VALUES(brand_voice), 
        content_tone = VALUES(content_tone),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([$sku, $brandVoice, $contentTone]);
    
    echo json_encode(['success' => true, 'message' => 'Marketing preferences saved successfully']);
}

function initializeDefaultOptions($pdo) {
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
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO content_tone_options (value, label, description, display_order) VALUES (?, ?, ?, ?)");
    
    $inserted = 0;
    foreach ($defaultOptions as $option) {
        $result = $stmt->execute($option);
        if ($stmt->rowCount() > 0) {
            $inserted++;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Initialized {$inserted} default content tone options",
        'total_options' => count($defaultOptions)
    ]);
}

?> 