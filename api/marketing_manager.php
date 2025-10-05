<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';


// Use centralized authentication
// Admin authentication with token fallback for API access
$isAdmin = false;

// Check admin authentication using centralized helper
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
            Response::error('Invalid action specified.', null, 400);
    }

} catch (Exception $e) {
    error_log("Error in marketing_manager.php: " . $e->getMessage());
    Response::serverError('Internal server error occurred.');
}
// getMarketingData function moved to data_manager.php for centralization

function updateMarketingField($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $sku = $input['sku'] ?? '';
    $field = $input['field'] ?? '';
    $value = $input['value'] ?? '';

    if (empty($sku) || empty($field)) {
        Response::json(['success' => false, 'error' => 'SKU and field are required.']);
    }

    // Validate field name
    $allowedFields = [
        'suggested_title', 'suggested_description', 'target_audience', 'psychographic_profile',
        'demographic_targeting', 'market_positioning', 'brand_voice', 'content_tone',
        'seasonal_relevance', 'pricing_psychology', 'search_intent'
    ];

    if (!in_array($field, $allowedFields)) {
        Response::json(['success' => false, 'error' => 'Invalid field name.']);
    }

    // Check if record exists
    $exists = Database::queryOne("SELECT id FROM marketing_suggestions WHERE sku = ?", [$sku]);

    if ($exists) {
        // Update existing record
        Database::execute("UPDATE marketing_suggestions SET {$field} = ?, updated_at = CURRENT_TIMESTAMP WHERE sku = ?", [$value, $sku]);
    } else {
        // Create new record
        Database::execute("INSERT INTO marketing_suggestions (sku, {$field}) VALUES (?, ?)", [$sku, $value]);
    }

    Response::json(['success' => true, 'message' => 'Field updated successfully.']);
}

function addListItem($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $sku = $input['sku'] ?? '';
    $field = $input['field'] ?? '';
    $item = $input['item'] ?? '';

    if (empty($sku) || empty($field) || empty($item)) {
        Response::json(['success' => false, 'error' => 'SKU, field, and item are required.']);
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
        Response::json(['success' => false, 'error' => 'Invalid field name.']);
    }

    // Get current data
    $result = Database::queryOne("SELECT {$field} FROM marketing_suggestions WHERE sku = ?", [$sku]);

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
        Database::execute("UPDATE marketing_suggestions SET {$field} = ?, updated_at = CURRENT_TIMESTAMP WHERE sku = ?", [json_encode($currentList), $sku]);
    } else {
        Database::execute("INSERT INTO marketing_suggestions (sku, {$field}) VALUES (?, ?)", [$sku, json_encode($currentList)]);
    }

    Response::json(['success' => true, 'message' => 'Item added successfully.', 'list' => $currentList]);
}

function removeListItem($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $sku = $input['sku'] ?? '';
    $field = $input['field'] ?? '';
    $item = $input['item'] ?? '';

    if (empty($sku) || empty($field) || empty($item)) {
        echo json_encode(['success' => false, 'error' => 'SKU, field, and item are required.']);
        return;
    }

    // Get current data
    $result = Database::queryOne("SELECT {$field} FROM marketing_suggestions WHERE sku = ?", [$sku]);

    if (!$result) {
        Response::json(['success' => false, 'error' => 'Marketing data not found.']);
    }

    $currentList = json_decode($result[$field], true) ?? [];

    // Remove item
    $currentList = array_values(array_filter($currentList, function ($listItem) use ($item) {
        return $listItem !== $item;
    }));

    // Update database
    Database::execute("UPDATE marketing_suggestions SET {$field} = ?, updated_at = CURRENT_TIMESTAMP WHERE sku = ?", [json_encode($currentList), $sku]);

    Response::json(['success' => true, 'message' => 'Item removed successfully.', 'list' => $currentList]);
}

function getSEOData($pdo)
{
    $page = $_GET['page'] ?? 'home';

    // Get global SEO settings
    $seoData = Database::queryAll("SELECT * FROM seo_settings WHERE page_type = ? OR page_type = 'global' ORDER BY page_type DESC", [$page]);

    $result = [];
    foreach ($seoData as $row) {
        $result[$row['setting_name']] = $row['setting_value'];
    }

    Response::json(['success' => true, 'data' => $result]);
}

function updateSEO($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $page = $input['page'] ?? 'home';
    $settings = $input['settings'] ?? [];

    foreach ($settings as $settingName => $settingValue) {
        Database::execute("\n            INSERT INTO seo_settings (page_type, setting_name, setting_value) \n            VALUES (?, ?, ?) \n            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP\n        ", [$page, $settingName, $settingValue]);
    }

    Response::json(['success' => true, 'message' => 'SEO settings updated successfully.']);
}
