<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Use centralized authentication
requireAdmin();

// Authentication is handled by requireAdmin() above
$userData = getCurrentUser();

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Handle different actions
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_marketing_data':
            getMarketingData($pdo);
            break;
        case 'update_field':
            updateMarketingField($pdo);
            break;
        case 'add_list_item':
            addListItem($pdo);
            break;
        case 'remove_list_item':
            removeListItem($pdo);
            break;
        case 'get_seo_data':
            getSEOData($pdo);
            break;
        case 'update_seo':
            updateSEO($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);
    }
    
} catch (Exception $e) {
    error_log("Error in marketing_manager.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred.']);
}

function getMarketingData($pdo) {
    $sku = $_GET['sku'] ?? '';
    if (empty($sku)) {
        echo json_encode(['success' => false, 'error' => 'SKU is required.']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM marketing_suggestions WHERE sku = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$sku]);
    $data = $stmt->fetch();
    
    if ($data) {
        // Decode JSON fields
        $jsonFields = [
            'keywords', 'emotional_triggers', 'selling_points', 'competitive_advantages',
            'unique_selling_points', 'value_propositions', 'marketing_channels',
            'urgency_factors', 'social_proof_elements', 'call_to_action_suggestions',
            'conversion_triggers', 'objection_handlers', 'seo_keywords', 'content_themes',
            'customer_benefits', 'pain_points_addressed', 'lifestyle_alignment'
        ];
        
        foreach ($jsonFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = json_decode($data[$field], true) ?? [];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => true, 'data' => null]);
    }
}

function updateMarketingField($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $sku = $input['sku'] ?? '';
    $field = $input['field'] ?? '';
    $value = $input['value'] ?? '';
    
    if (empty($sku) || empty($field)) {
        echo json_encode(['success' => false, 'error' => 'SKU and field are required.']);
        return;
    }
    
    // Validate field name
    $allowedFields = [
        'suggested_title', 'suggested_description', 'target_audience', 'psychographic_profile',
        'demographic_targeting', 'market_positioning', 'brand_voice', 'content_tone',
        'seasonal_relevance', 'pricing_psychology', 'search_intent'
    ];
    
    if (!in_array($field, $allowedFields)) {
        echo json_encode(['success' => false, 'error' => 'Invalid field name.']);
        return;
    }
    
    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM marketing_suggestions WHERE sku = ?");
    $stmt->execute([$sku]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing record
        $stmt = $pdo->prepare("UPDATE marketing_suggestions SET {$field} = ?, updated_at = CURRENT_TIMESTAMP WHERE sku = ?");
        $stmt->execute([$value, $sku]);
    } else {
        // Create new record
        $stmt = $pdo->prepare("INSERT INTO marketing_suggestions (sku, {$field}) VALUES (?, ?)");
        $stmt->execute([$sku, $value]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Field updated successfully.']);
}

function addListItem($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $sku = $input['sku'] ?? '';
    $field = $input['field'] ?? '';
    $item = $input['item'] ?? '';
    
    if (empty($sku) || empty($field) || empty($item)) {
        echo json_encode(['success' => false, 'error' => 'SKU, field, and item are required.']);
        return;
    }
    
    // Validate field name
    $allowedListFields = [
        'keywords', 'emotional_triggers', 'selling_points', 'competitive_advantages',
        'unique_selling_points', 'value_propositions', 'marketing_channels',
        'urgency_factors', 'social_proof_elements', 'call_to_action_suggestions',
        'conversion_triggers', 'objection_handlers', 'seo_keywords', 'content_themes',
        'customer_benefits', 'pain_points_addressed', 'lifestyle_alignment'
    ];
    
    if (!in_array($field, $allowedListFields)) {
        echo json_encode(['success' => false, 'error' => 'Invalid field name.']);
        return;
    }
    
    // Get current data
    $stmt = $pdo->prepare("SELECT {$field} FROM marketing_suggestions WHERE sku = ?");
    $stmt->execute([$sku]);
    $result = $stmt->fetch();
    
    $currentList = [];
    if ($result && !empty($result[$field])) {
        $currentList = json_decode($result[$field], true) ?? [];
    }
    
    // Add new item if not already exists
    if (!in_array($item, $currentList)) {
        $currentList[] = $item;
    }
    
    // Update database
    if ($result) {
        $stmt = $pdo->prepare("UPDATE marketing_suggestions SET {$field} = ?, updated_at = CURRENT_TIMESTAMP WHERE sku = ?");
        $stmt->execute([json_encode($currentList), $sku]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO marketing_suggestions (sku, {$field}) VALUES (?, ?)");
        $stmt->execute([$sku, json_encode($currentList)]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Item added successfully.', 'list' => $currentList]);
}

function removeListItem($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $sku = $input['sku'] ?? '';
    $field = $input['field'] ?? '';
    $item = $input['item'] ?? '';
    
    if (empty($sku) || empty($field) || empty($item)) {
        echo json_encode(['success' => false, 'error' => 'SKU, field, and item are required.']);
        return;
    }
    
    // Get current data
    $stmt = $pdo->prepare("SELECT {$field} FROM marketing_suggestions WHERE sku = ?");
    $stmt->execute([$sku]);
    $result = $stmt->fetch();
    
    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Marketing data not found.']);
        return;
    }
    
    $currentList = json_decode($result[$field], true) ?? [];
    
    // Remove item
    $currentList = array_values(array_filter($currentList, function($listItem) use ($item) {
        return $listItem !== $item;
    }));
    
    // Update database
    $stmt = $pdo->prepare("UPDATE marketing_suggestions SET {$field} = ?, updated_at = CURRENT_TIMESTAMP WHERE sku = ?");
    $stmt->execute([json_encode($currentList), $sku]);
    
    echo json_encode(['success' => true, 'message' => 'Item removed successfully.', 'list' => $currentList]);
}

function getSEOData($pdo) {
    $page = $_GET['page'] ?? 'home';
    
    // Get global SEO settings
    $stmt = $pdo->prepare("SELECT * FROM seo_settings WHERE page_type = ? OR page_type = 'global' ORDER BY page_type DESC");
    $stmt->execute([$page]);
    $seoData = $stmt->fetchAll();
    
    $result = [];
    foreach ($seoData as $row) {
        $result[$row['setting_name']] = $row['setting_value'];
    }
    
    echo json_encode(['success' => true, 'data' => $result]);
}

function updateSEO($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $page = $input['page'] ?? 'home';
    $settings = $input['settings'] ?? [];
    
    foreach ($settings as $settingName => $settingValue) {
        $stmt = $pdo->prepare("
            INSERT INTO seo_settings (page_type, setting_name, setting_value) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$page, $settingName, $settingValue]);
    }
    
    echo json_encode(['success' => true, 'message' => 'SEO settings updated successfully.']);
}
?> 