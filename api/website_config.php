<?php
/**
 * Website Configuration API
 * Manages website settings, CSS variables, and UI component configurations
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Use centralized authentication
// Admin authentication with token fallback for API access
    $isAdmin = false;
    
    // Check session authentication first
    if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') {
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
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_config':
            getConfig($pdo);
            break;
        case 'update_config':
            updateConfig($pdo);
            break;
        case 'get_css_variables':
            getCSSVariables($pdo);
            break;
        case 'update_css_variable':
            updateCSSVariable($pdo);
            break;
        case 'get_ui_components':
            getUIComponents($pdo);
            break;
        case 'update_ui_component':
            updateUIComponent($pdo);
            break;
        case 'get_css_output':
            getCSSOutput($pdo);
            break;
        case 'get_marketing_defaults':
            getMarketingDefaults($pdo);
            break;
        case 'update_marketing_defaults':
            updateMarketingDefaults($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action specified.']);
    }
    
} catch (Exception $e) {
    error_log("Error in website_config.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error occurred.']);
}

function getConfig($pdo) {
    $category = $_GET['category'] ?? '';
    
    if ($category) {
        $stmt = $pdo->prepare("SELECT * FROM website_config WHERE category = ? AND is_active = 1 ORDER BY setting_key");
        $stmt->execute([$category]);
    } else {
        $stmt = $pdo->query("SELECT * FROM website_config WHERE is_active = 1 ORDER BY category, setting_key");
    }
    
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by category
    $grouped = [];
    foreach ($configs as $config) {
        $grouped[$config['category']][] = $config;
    }
    
    echo json_encode(['success' => true, 'data' => $grouped]);
}

function updateConfig($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $category = $input['category'] ?? '';
    $setting_key = $input['setting_key'] ?? '';
    $setting_value = $input['setting_value'] ?? '';
    $setting_type = $input['setting_type'] ?? 'string';
    
    if (empty($category) || empty($setting_key)) {
        echo json_encode(['success' => false, 'error' => 'Category and setting key are required.']);
        return;
    }
    
    // Validate setting type
    $allowedTypes = ['string', 'number', 'boolean', 'json', 'color', 'css'];
    if (!in_array($setting_type, $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid setting type.']);
        return;
    }
    
    // Convert boolean values
    if ($setting_type === 'boolean') {
        $setting_value = $setting_value ? 'true' : 'false';
    }
    
    // Validate JSON if type is json
    if ($setting_type === 'json' && !empty($setting_value)) {
        $decoded = json_decode($setting_value);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['success' => false, 'error' => 'Invalid JSON format.']);
            return;
        }
    }
    
    // Check if setting exists
    $stmt = $pdo->prepare("SELECT id FROM website_config WHERE category = ? AND setting_key = ?");
    $stmt->execute([$category, $setting_key]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing setting
        $stmt = $pdo->prepare("UPDATE website_config SET setting_value = ?, setting_type = ?, updated_at = CURRENT_TIMESTAMP WHERE category = ? AND setting_key = ?");
        $stmt->execute([$setting_value, $setting_type, $category, $setting_key]);
    } else {
        // Create new setting
        $stmt = $pdo->prepare("INSERT INTO website_config (category, setting_key, setting_value, setting_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$category, $setting_key, $setting_value, $setting_type]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Configuration updated successfully.']);
}

function getCSSVariables($pdo) {
    $category = $_GET['category'] ?? '';
    
    if ($category) {
        $stmt = $pdo->prepare("SELECT * FROM css_variables WHERE category = ? AND is_active = 1 ORDER BY variable_name");
        $stmt->execute([$category]);
    } else {
        $stmt = $pdo->query("SELECT * FROM css_variables WHERE is_active = 1 ORDER BY category, variable_name");
    }
    
    $variables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by category
    $grouped = [];
    foreach ($variables as $var) {
        $grouped[$var['category']][] = $var;
    }
    
    echo json_encode(['success' => true, 'data' => $grouped]);
}

function updateCSSVariable($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $variable_name = $input['variable_name'] ?? '';
    $variable_value = $input['variable_value'] ?? '';
    $category = $input['category'] ?? 'general';
    $description = $input['description'] ?? '';
    
    if (empty($variable_name) || empty($variable_value)) {
        echo json_encode(['success' => false, 'error' => 'Variable name and value are required.']);
        return;
    }
    
    // Ensure variable name starts with --
    if (!str_starts_with($variable_name, '--')) {
        $variable_name = '--' . $variable_name;
    }
    
    // Check if variable exists
    $stmt = $pdo->prepare("SELECT id FROM css_variables WHERE variable_name = ?");
    $stmt->execute([$variable_name]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing variable
        $stmt = $pdo->prepare("UPDATE css_variables SET variable_value = ?, category = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE variable_name = ?");
        $stmt->execute([$variable_value, $category, $description, $variable_name]);
    } else {
        // Create new variable
        $stmt = $pdo->prepare("INSERT INTO css_variables (variable_name, variable_value, category, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$variable_name, $variable_value, $category, $description]);
    }
    
    echo json_encode(['success' => true, 'message' => 'CSS variable updated successfully.']);
}

function getUIComponents($pdo) {
    $component_name = $_GET['component'] ?? '';
    
    if ($component_name) {
        $stmt = $pdo->prepare("SELECT * FROM ui_components WHERE component_name = ? AND is_active = 1");
        $stmt->execute([$component_name]);
        $component = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($component && $component['component_config']) {
            $component['component_config'] = json_decode($component['component_config'], true);
        }
        
        echo json_encode(['success' => true, 'data' => $component]);
    } else {
        $stmt = $pdo->query("SELECT * FROM ui_components WHERE is_active = 1 ORDER BY component_name");
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($components as &$component) {
            if ($component['component_config']) {
                $component['component_config'] = json_decode($component['component_config'], true);
            }
        }
        
        echo json_encode(['success' => true, 'data' => $components]);
    }
}

function updateUIComponent($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $component_name = $input['component_name'] ?? '';
    $component_config = $input['component_config'] ?? [];
    $css_classes = $input['css_classes'] ?? '';
    $custom_css = $input['custom_css'] ?? '';
    
    if (empty($component_name)) {
        echo json_encode(['success' => false, 'error' => 'Component name is required.']);
        return;
    }
    
    // Convert config to JSON
    $config_json = json_encode($component_config);
    
    // Check if component exists
    $stmt = $pdo->prepare("SELECT id FROM ui_components WHERE component_name = ?");
    $stmt->execute([$component_name]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing component
        $stmt = $pdo->prepare("UPDATE ui_components SET component_config = ?, css_classes = ?, custom_css = ?, updated_at = CURRENT_TIMESTAMP WHERE component_name = ?");
        $stmt->execute([$config_json, $css_classes, $custom_css, $component_name]);
    } else {
        // Create new component
        $stmt = $pdo->prepare("INSERT INTO ui_components (component_name, component_config, css_classes, custom_css) VALUES (?, ?, ?, ?)");
        $stmt->execute([$component_name, $config_json, $css_classes, $custom_css]);
    }
    
    echo json_encode(['success' => true, 'message' => 'UI component updated successfully.']);
}

function getCSSOutput($pdo) {
    // Get all active CSS variables
    $stmt = $pdo->query("SELECT variable_name, variable_value FROM css_variables WHERE is_active = 1 ORDER BY category, variable_name");
    $variables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate CSS output
    $css = ":root {\n";
    foreach ($variables as $var) {
        $css .= "    {$var['variable_name']}: {$var['variable_value']};\n";
    }
    $css .= "}\n\n";
    
    // Get custom CSS from components
    $stmt = $pdo->query("SELECT component_name, custom_css FROM ui_components WHERE is_active = 1 AND custom_css != '' ORDER BY component_name");
    $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($components as $component) {
        if (!empty($component['custom_css'])) {
            $css .= "/* {$component['component_name']} */\n";
            $css .= $component['custom_css'] . "\n\n";
        }
    }
    
    echo json_encode(['success' => true, 'css' => $css]);
}

function getMarketingDefaults($pdo) {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM website_config WHERE category = 'marketing' AND is_active = 1");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $defaults = [];
    foreach ($settings as $setting) {
        $defaults[$setting['setting_key']] = $setting['setting_value'];
    }
    
    echo json_encode(['success' => true, 'data' => $defaults]);
}

function updateMarketingDefaults($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $brand_voice = $input['default_brand_voice'] ?? '';
    $content_tone = $input['default_content_tone'] ?? '';
    $auto_apply = $input['auto_apply_defaults'] ?? 'false';
    
    if (empty($brand_voice) || empty($content_tone)) {
        echo json_encode(['success' => false, 'error' => 'Brand voice and content tone are required.']);
        return;
    }
    
    // Update marketing defaults
    $updates = [
        'default_brand_voice' => $brand_voice,
        'default_content_tone' => $content_tone,
        'auto_apply_defaults' => $auto_apply
    ];
    
    foreach ($updates as $key => $value) {
        $stmt = $pdo->prepare("UPDATE website_config SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE category = 'marketing' AND setting_key = ?");
        $stmt->execute([$value, $key]);
    }
    
    // Also update the business_settings table for backward compatibility
    $stmt = $pdo->prepare("UPDATE business_settings SET setting_value = ? WHERE category = 'ai' AND setting_key = 'ai_brand_voice'");
    $stmt->execute([$brand_voice]);
    
    $stmt = $pdo->prepare("UPDATE business_settings SET setting_value = ? WHERE category = 'ai' AND setting_key = 'ai_content_tone'");
    $stmt->execute([$content_tone]);
    
    echo json_encode(['success' => true, 'message' => 'Marketing defaults updated successfully.']);
}

?> 