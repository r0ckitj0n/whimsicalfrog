<?php
/**
 * AI Settings API for WhimsicalFrog
 * Manages AI provider configurations and settings
 */

require_once 'config.php';
require_once 'ai_providers.php';

header('Content-Type: application/json');

// Check if user is admin
session_start();

// Security Check: Ensure user is logged in and is an Admin
$isLoggedIn = isset($_SESSION['user']);
$isAdmin = false;

if ($isLoggedIn) {
    $userData = $_SESSION['user'];
    // Handle both string and array formats
    if (is_string($userData)) {
        $userData = json_decode($userData, true);
    }
    if (is_array($userData)) {
        $isAdmin = isset($userData['role']) && strtolower($userData['role']) === 'admin';
    }
}

if (!$isLoggedIn || !$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    switch ($action) {
        case 'get_settings':
            $settings = getAISettings();
            echo json_encode(['success' => true, 'settings' => $settings]);
            break;
            
        case 'get_providers':
            $providers = getAIProviders()->getAvailableProviders();
            echo json_encode(['success' => true, 'providers' => $providers]);
            break;
            
        case 'update_settings':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception('Invalid JSON input');
            }
            
            $result = updateAISettings($input, $pdo);
            echo json_encode(['success' => true, 'message' => 'AI settings updated successfully']);
            break;
            
        case 'test_provider':
            $provider = $_POST['provider'] ?? $_GET['provider'] ?? '';
            if (empty($provider)) {
                throw new Exception('Provider not specified');
            }
            
            $result = getAIProviders()->testProvider($provider);
            echo json_encode($result);
            break;
            
        case 'init_ai_settings':
            $result = initializeAISettings($pdo);
            echo json_encode(['success' => true, 'message' => 'AI settings initialized', 'inserted' => $result]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Get current AI settings
 */
function getAISettings() {
    global $dsn, $user, $pass, $options;
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    $defaults = [
        'ai_provider' => 'jons_ai',
        'openai_api_key' => '',
        'openai_model' => 'gpt-3.5-turbo',
        'anthropic_api_key' => '',
        'anthropic_model' => 'claude-3-haiku-20240307',
        'google_api_key' => '',
        'google_model' => 'gemini-pro',
        'ai_temperature' => 0.7,
        'ai_max_tokens' => 1000,
        'ai_timeout' => 30,
        'fallback_to_local' => true,
        'ai_brand_voice' => '',
        'ai_content_tone' => 'professional',
        // Advanced AI Temperature & Configuration Settings
        'ai_cost_temperature' => 0.7,
        'ai_price_temperature' => 0.7,
        'ai_cost_multiplier_base' => 1.0,
        'ai_price_multiplier_base' => 1.0,
        'ai_conservative_mode' => false,
        'ai_market_research_weight' => 0.3,
        'ai_cost_plus_weight' => 0.4,
        'ai_value_based_weight' => 0.3
    ];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai'");
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($results as $row) {
            $key = $row['setting_key'];
            $value = $row['setting_value'];
            
            // Convert string values to appropriate types
            if (in_array($key, ['ai_temperature', 'ai_cost_temperature', 'ai_price_temperature', 'ai_cost_multiplier_base', 'ai_price_multiplier_base', 'ai_market_research_weight', 'ai_cost_plus_weight', 'ai_value_based_weight'])) {
                $defaults[$key] = (float)$value;
            } elseif (in_array($key, ['ai_max_tokens', 'ai_timeout'])) {
                $defaults[$key] = (int)$value;
            } elseif (in_array($key, ['fallback_to_local', 'ai_conservative_mode'])) {
                $defaults[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $defaults[$key] = $value;
            }
        }
    } catch (Exception $e) {
        error_log("Error loading AI settings: " . $e->getMessage());
    }
    
    return $defaults;
}

/**
 * Update AI settings
 */
function updateAISettings($settings, $pdo) {
    $validSettings = [
        'ai_provider', 'openai_api_key', 'openai_model', 
        'anthropic_api_key', 'anthropic_model',
        'google_api_key', 'google_model',
        'ai_temperature', 'ai_max_tokens', 'ai_timeout',
        'fallback_to_local', 'ai_brand_voice', 'ai_content_tone',
        // Advanced AI Temperature & Configuration Settings
        'ai_cost_temperature', 'ai_price_temperature',
        'ai_cost_multiplier_base', 'ai_price_multiplier_base',
        'ai_conservative_mode', 'ai_market_research_weight',
        'ai_cost_plus_weight', 'ai_value_based_weight'
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO business_settings (category, setting_key, setting_value, description, setting_type, display_name) 
        VALUES ('ai', ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description), setting_type = VALUES(setting_type), display_name = VALUES(display_name)
    ");
    
    foreach ($settings as $key => $value) {
        if (!in_array($key, $validSettings)) {
            continue;
        }
        
        // Convert values to strings for storage
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
            $settingType = 'boolean';
        } elseif (is_numeric($value)) {
            $settingType = 'number';
        } else {
            $settingType = 'text';
        }
        
        // Set descriptions and display names
        $descriptions = [
            'ai_provider' => 'Selected AI provider (jons_ai, openai, anthropic, google)',
            'openai_api_key' => 'OpenAI API key for ChatGPT access',
            'openai_model' => 'OpenAI model to use (gpt-3.5-turbo, gpt-4, etc.)',
            'anthropic_api_key' => 'Anthropic API key for Claude access',
            'anthropic_model' => 'Anthropic model to use (claude-3-haiku, claude-3-sonnet, etc.)',
            'google_api_key' => 'Google AI API key for Gemini access',
            'google_model' => 'Google AI model to use (gemini-pro, etc.)',
            'ai_temperature' => 'AI creativity level (0.0-1.0, higher = more creative)',
            'ai_max_tokens' => 'Maximum tokens per AI response',
            'ai_timeout' => 'API timeout in seconds',
            'fallback_to_local' => "Fallback to Jon's AI if external API fails",
            'ai_brand_voice' => 'Default brand voice for AI content generation',
            'ai_content_tone' => 'Default content tone (professional, casual, friendly, etc.)',
            // Advanced AI Temperature & Configuration Settings
            'ai_cost_temperature' => 'Controls AI creativity for cost suggestions (0.1-1.0, lower = more consistent)',
            'ai_price_temperature' => 'Controls AI creativity for price suggestions (0.1-1.0, lower = more consistent)',
            'ai_cost_multiplier_base' => 'Base multiplier for all cost calculations (0.5-2.0)',
            'ai_price_multiplier_base' => 'Base multiplier for all price calculations (0.5-2.0)',
            'ai_conservative_mode' => 'When enabled, reduces variability and makes suggestions more conservative',
            'ai_market_research_weight' => 'Weight given to market research in pricing decisions (0.0-1.0)',
            'ai_cost_plus_weight' => 'Weight given to cost-plus pricing (0.0-1.0)',
            'ai_value_based_weight' => 'Weight given to value-based pricing (0.0-1.0)'
        ];
        
        $displayNames = [
            'ai_provider' => 'AI Provider',
            'openai_api_key' => 'OpenAI API Key',
            'openai_model' => 'OpenAI Model',
            'anthropic_api_key' => 'Anthropic API Key',
            'anthropic_model' => 'Anthropic Model',
            'google_api_key' => 'Google API Key',
            'google_model' => 'Google Model',
            'ai_temperature' => 'AI Temperature',
            'ai_max_tokens' => 'Max Tokens',
            'ai_timeout' => 'API Timeout',
            'fallback_to_local' => 'Fallback to Local',
            'ai_brand_voice' => 'Brand Voice',
            'ai_content_tone' => 'Content Tone',
            // Advanced AI Temperature & Configuration Settings
            'ai_cost_temperature' => 'Cost Temperature',
            'ai_price_temperature' => 'Price Temperature',
            'ai_cost_multiplier_base' => 'Cost Base Multiplier',
            'ai_price_multiplier_base' => 'Price Base Multiplier',
            'ai_conservative_mode' => 'Conservative Mode',
            'ai_market_research_weight' => 'Market Research Weight',
            'ai_cost_plus_weight' => 'Cost-Plus Weight',
            'ai_value_based_weight' => 'Value-Based Weight'
        ];
        
        $description = $descriptions[$key] ?? '';
        $displayName = $displayNames[$key] ?? ucwords(str_replace('_', ' ', $key));
        
        $stmt->execute([$key, $value, $description, $settingType, $displayName]);
    }
    
    return true;
}

/**
 * Initialize AI settings with defaults
 */
function initializeAISettings($pdo) {
    $defaultSettings = [
        'ai_provider' => ['jons_ai', 'Selected AI provider (jons_ai, openai, anthropic, google)', 'text', 'AI Provider'],
        'openai_api_key' => ['', 'OpenAI API key for ChatGPT access', 'text', 'OpenAI API Key'],
        'openai_model' => ['gpt-3.5-turbo', 'OpenAI model to use', 'text', 'OpenAI Model'],
        'anthropic_api_key' => ['', 'Anthropic API key for Claude access', 'text', 'Anthropic API Key'],
        'anthropic_model' => ['claude-3-haiku-20240307', 'Anthropic model to use', 'text', 'Anthropic Model'],
        'google_api_key' => ['', 'Google AI API key for Gemini access', 'text', 'Google API Key'],
        'google_model' => ['gemini-pro', 'Google AI model to use', 'text', 'Google Model'],
        'ai_temperature' => ['0.7', 'AI creativity level (0.0-1.0)', 'number', 'AI Temperature'],
        'ai_max_tokens' => ['1000', 'Maximum tokens per AI response', 'number', 'Max Tokens'],
        'ai_timeout' => ['30', 'API timeout in seconds', 'number', 'API Timeout'],
        'fallback_to_local' => ['1', "Fallback to Jon's AI if external API fails", 'boolean', "Fallback to Jon's AI"],
        'ai_brand_voice' => ['', 'Default brand voice for AI content generation', 'text', 'Brand Voice'],
        'ai_content_tone' => ['professional', 'Default content tone', 'text', 'Content Tone'],
        // Advanced AI Temperature & Configuration Settings
        'ai_cost_temperature' => ['0.7', 'Controls AI creativity for cost suggestions (0.1-1.0, lower = more consistent)', 'number', 'Cost Temperature'],
        'ai_price_temperature' => ['0.7', 'Controls AI creativity for price suggestions (0.1-1.0, lower = more consistent)', 'number', 'Price Temperature'],
        'ai_cost_multiplier_base' => ['1.0', 'Base multiplier for all cost calculations (0.5-2.0)', 'number', 'Cost Base Multiplier'],
        'ai_price_multiplier_base' => ['1.0', 'Base multiplier for all price calculations (0.5-2.0)', 'number', 'Price Base Multiplier'],
        'ai_conservative_mode' => ['0', 'When enabled, reduces variability and makes suggestions more conservative', 'boolean', 'Conservative Mode'],
        'ai_market_research_weight' => ['0.3', 'Weight given to market research in pricing decisions (0.0-1.0)', 'number', 'Market Research Weight'],
        'ai_cost_plus_weight' => ['0.4', 'Weight given to cost-plus pricing (0.0-1.0)', 'number', 'Cost-Plus Weight'],
        'ai_value_based_weight' => ['0.3', 'Weight given to value-based pricing (0.0-1.0)', 'number', 'Value-Based Weight']
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO business_settings (category, setting_key, setting_value, description, setting_type, display_name) 
        VALUES ('ai', ?, ?, ?, ?, ?)
    ");
    
    $inserted = 0;
    foreach ($defaultSettings as $key => $data) {
        $result = $stmt->execute([$key, $data[0], $data[1], $data[2], $data[3]]);
        if ($stmt->rowCount() > 0) {
            $inserted++;
        }
    }
    
    return $inserted;
}

?> 