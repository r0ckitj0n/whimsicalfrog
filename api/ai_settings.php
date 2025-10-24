<?php
/**
 * AI Settings API for WhimsicalFrog
 * Manages AI provider configurations and settings
 */

require_once 'config.php';
require_once __DIR__ . '/../includes/response.php';
require_once 'ai_providers.php';
require_once __DIR__ . '/../includes/secret_store.php';

// Check if user is admin


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
    Response::forbidden('Admin access required');
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    switch ($action) {
        case 'get_settings':
            $settings = getAISettings();
            Response::json(['success' => true, 'settings' => $settings]);
            break;

        case 'get_providers':
            $providers = getAIProviders()->getAvailableProviders();
            Response::json(['success' => true, 'providers' => $providers]);
            break;

        case 'update_settings':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                Response::error('Invalid JSON input', null, 400);
            }

            $result = updateAISettings($input, $pdo);
            Response::json(['success' => true, 'message' => 'AI settings updated successfully']);
            break;

        case 'test_provider':
            $provider = $_POST['provider'] ?? $_GET['provider'] ?? '';
            if (empty($provider)) {
                Response::error('Provider not specified', null, 400);
            }

            $result = getAIProviders()->testProvider($provider);
            Response::json($result);
            break;

        case 'init_ai_settings':
            $result = initializeAISettings($pdo);
            Response::json(['success' => true, 'message' => 'AI settings initialized', 'inserted' => $result]);
            break;

        default:
            Response::error('Invalid action', null, 400);
    }

} catch (Exception $e) {
    Response::serverError($e->getMessage());
}

/**
 * Get current AI settings
 */
function getAISettings()
{
    global $dsn, $user, $pass, $options;
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

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
        $results = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai'");

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

    // Never return actual API keys; report presence only
    $defaults['openai_key_present'] = secret_has('openai_api_key');
    $defaults['anthropic_key_present'] = secret_has('anthropic_api_key');
    $defaults['google_key_present'] = secret_has('google_api_key');
    $defaults['meta_key_present'] = secret_has('meta_api_key');

    $defaults['openai_api_key'] = '';
    $defaults['anthropic_api_key'] = '';
    $defaults['google_api_key'] = '';
    // Meta optional in defaults but ensure masked key field exists in response when used elsewhere
    if (!array_key_exists('meta_api_key', $defaults)) {
        $defaults['meta_api_key'] = '';
    } else {
        $defaults['meta_api_key'] = '';
    }

    return $defaults;
}

/**
 * Update AI settings
 */
function updateAISettings($settings, $pdo)
{
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

    $secretKeys = [
        'openai_api_key',
        'anthropic_api_key',
        'google_api_key',
        'meta_api_key',
    ];

    foreach ($settings as $key => $value) {
        if (!in_array($key, $validSettings)) {
            continue;
        }

        // Route secrets to secret store and mask DB value
        if (in_array($key, $secretKeys, true)) {
            if (is_string($value) && $value !== '') {
                // Store in secrets vault
                secret_set($key, $value);
            }
            // Do not store actual secret in business_settings
            $value = '';
        }

        // Convert values to strings for storage (non-secret handling and masked secrets)
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

        Database::execute("\n            INSERT INTO business_settings (category, setting_key, setting_value, description, setting_type, display_name) \n            VALUES ('ai', ?, ?, ?, ?, ?) \n            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description), setting_type = VALUES(setting_type), display_name = VALUES(display_name)\n        ", [$key, $value, $description, $settingType, $displayName]);
    }

    return true;
}

/**
 * Initialize AI settings with defaults
 */
function initializeAISettings($pdo)
{
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

    $inserted = 0;
    foreach ($defaultSettings as $key => $data) {
        $affected = Database::execute("\n            INSERT IGNORE INTO business_settings (category, setting_key, setting_value, description, setting_type, display_name) \n            VALUES ('ai', ?, ?, ?, ?, ?)\n        ", [$key, $data[0], $data[1], $data[2], $data[3]]);
        if ($affected > 0) {
            $inserted++;
        }
    }

    return $inserted;
}

?> 