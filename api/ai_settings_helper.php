<?php
/**
 * AI Settings Helper for WhimsicalFrog
 * Extracted helper functions for AI provider configurations and model listing.
 */

require_once 'ai_providers.php';
require_once 'ai_model_utils.php';
require_once 'ai_settings_metadata.php';
require_once __DIR__ . '/../includes/secret_store.php';

/**
 * Helper: return last N characters of a secret key without exposing the full value
 */
function ai_get_key_suffix($secretKeyName, $length = 3)
{
    try {
        $val = secret_get($secretKeyName);
        if (!is_string($val) || $val === '') {
            return '';
        }
        if ($length <= 0) {
            return '';
        }
        if (strlen($val) <= $length) {
            return $val;
        }
        return substr($val, -$length);
    } catch (Exception $e) {
        // Never let suffix lookup break settings loading
        return '';
    }
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
        'meta_api_key' => '',
        'meta_model' => 'meta-llama-3.1-8b-instruct',
        'openai_last_success_at' => '',
        'openai_last_test_success_at' => '',
        'anthropic_last_success_at' => '',
        'anthropic_last_test_success_at' => '',
        'google_last_success_at' => '',
        'google_last_test_success_at' => '',
        'meta_last_success_at' => '',
        'meta_last_test_success_at' => '',
        'ai_temperature' => 0.7,
        'ai_max_tokens' => 1000,
        'ai_timeout' => 30,
        'fallback_to_local' => true,
        'ai_brand_voice' => '',
        'ai_content_tone' => 'professional',
        'ai_theme_words_enabled' => true,
        'ai_theme_words_enabled_name' => true,
        'ai_theme_words_enabled_description' => true,
        'ai_theme_words_enabled_keywords' => false,
        'ai_theme_words_enabled_selling_points' => false,
        'ai_theme_words_enabled_call_to_action' => false,
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
                $defaults[$key] = (float) $value;
            } elseif (in_array($key, ['ai_max_tokens', 'ai_timeout'])) {
                $defaults[$key] = (int) $value;
            } elseif (in_array($key, [
                'fallback_to_local',
                'ai_conservative_mode',
                'ai_theme_words_enabled',
                'ai_theme_words_enabled_name',
                'ai_theme_words_enabled_description',
                'ai_theme_words_enabled_keywords',
                'ai_theme_words_enabled_selling_points',
                'ai_theme_words_enabled_call_to_action'
            ], true)) {
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

    // Provide short, non-sensitive suffixes to help visually confirm which key is in use
    $defaults['openai_key_suffix'] = ai_get_key_suffix('openai_api_key');
    $defaults['anthropic_key_suffix'] = ai_get_key_suffix('anthropic_api_key');
    $defaults['google_key_suffix'] = ai_get_key_suffix('google_api_key');
    $defaults['meta_key_suffix'] = ai_get_key_suffix('meta_api_key');

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
    $metadata = getAiSettingsMetadata();
    $validSettings = $metadata['valid_settings'];
    $secretKeys = $metadata['secret_keys'];
    $descriptions = $metadata['descriptions'];
    $displayNames = $metadata['display_names'];

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

        $description = $descriptions[$key] ?? '';
        $displayName = $displayNames[$key] ?? ucwords(str_replace('_', ' ', $key));

        Database::execute("
            INSERT INTO business_settings (category, setting_key, setting_value, description, setting_type, display_name) 
            VALUES ('ai', ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description), setting_type = VALUES(setting_type), display_name = VALUES(display_name)
        ", [$key, $value, $description, $settingType, $displayName]);
    }

    return true;
}

/**
 * Initialize AI settings with defaults
 */
function initializeAISettings($pdo)
{
    $metadata = getAiSettingsMetadata();
    $descriptions = $metadata['descriptions'];
    $displayNames = $metadata['display_names'];

    $defaultSettings = [
        'ai_provider' => ['jons_ai', $descriptions['ai_provider'], 'text', $displayNames['ai_provider']],
        'openai_api_key' => ['', $descriptions['openai_api_key'], 'text', $displayNames['openai_api_key']],
        'openai_model' => ['gpt-3.5-turbo', $descriptions['openai_model'], 'text', $displayNames['openai_model']],
        'anthropic_api_key' => ['', $descriptions['anthropic_api_key'], 'text', $displayNames['anthropic_api_key']],
        'anthropic_model' => ['claude-3-haiku-20240307', $descriptions['anthropic_model'], 'text', $displayNames['anthropic_model']],
        'google_api_key' => ['', $descriptions['google_api_key'], 'text', $displayNames['google_api_key']],
        'google_model' => ['gemini-pro', $descriptions['google_model'], 'text', $displayNames['google_model']],
        'meta_api_key' => ['', $descriptions['meta_api_key'], 'text', $displayNames['meta_api_key']],
        'meta_model' => ['meta-llama-3.1-8b-instruct', $descriptions['meta_model'], 'text', $displayNames['meta_model']],
        'ai_temperature' => ['0.7', $descriptions['ai_temperature'], 'number', $displayNames['ai_temperature']],
        'ai_max_tokens' => ['1000', $descriptions['ai_max_tokens'], 'number', $displayNames['ai_max_tokens']],
        'ai_timeout' => ['30', $descriptions['ai_timeout'], 'number', $displayNames['ai_timeout']],
        'fallback_to_local' => ['1', $descriptions['fallback_to_local'], 'boolean', "Fallback to Jon's AI"],
        'ai_brand_voice' => ['', $descriptions['ai_brand_voice'], 'text', $displayNames['ai_brand_voice']],
        'ai_content_tone' => ['professional', $descriptions['ai_content_tone'], 'text', $displayNames['ai_content_tone']],
        'ai_theme_words_enabled' => ['1', $descriptions['ai_theme_words_enabled'], 'boolean', $displayNames['ai_theme_words_enabled']],
        'ai_theme_words_enabled_name' => ['1', $descriptions['ai_theme_words_enabled_name'], 'boolean', $displayNames['ai_theme_words_enabled_name']],
        'ai_theme_words_enabled_description' => ['1', $descriptions['ai_theme_words_enabled_description'], 'boolean', $displayNames['ai_theme_words_enabled_description']],
        'ai_theme_words_enabled_keywords' => ['0', $descriptions['ai_theme_words_enabled_keywords'], 'boolean', $displayNames['ai_theme_words_enabled_keywords']],
        'ai_theme_words_enabled_selling_points' => ['0', $descriptions['ai_theme_words_enabled_selling_points'], 'boolean', $displayNames['ai_theme_words_enabled_selling_points']],
        'ai_theme_words_enabled_call_to_action' => ['0', $descriptions['ai_theme_words_enabled_call_to_action'], 'boolean', $displayNames['ai_theme_words_enabled_call_to_action']],
        'ai_cost_temperature' => ['0.7', $descriptions['ai_cost_temperature'], 'number', $displayNames['ai_cost_temperature']],
        'ai_price_temperature' => ['0.7', $descriptions['ai_price_temperature'], 'number', $displayNames['ai_price_temperature']],
        'ai_cost_multiplier_base' => ['1.0', $descriptions['ai_cost_multiplier_base'], 'number', $displayNames['ai_cost_multiplier_base']],
        'ai_price_multiplier_base' => ['1.0', $descriptions['ai_price_multiplier_base'], 'number', $displayNames['ai_price_multiplier_base']],
        'ai_conservative_mode' => ['0', $descriptions['ai_conservative_mode'], 'boolean', $displayNames['ai_conservative_mode']],
        'ai_market_research_weight' => ['0.3', $descriptions['ai_market_research_weight'], 'number', $displayNames['ai_market_research_weight']],
        'ai_cost_plus_weight' => ['0.4', $descriptions['ai_cost_plus_weight'], 'number', $displayNames['ai_cost_plus_weight']],
        'ai_value_based_weight' => ['0.3', $descriptions['ai_value_based_weight'], 'number', $displayNames['ai_value_based_weight']]
    ];

    $inserted = 0;
    foreach ($defaultSettings as $key => $data) {
        $affected = Database::execute("
            INSERT IGNORE INTO business_settings (category, setting_key, setting_value, description, setting_type, display_name) 
            VALUES ('ai', ?, ?, ?, ?, ?)
        ", [$key, $data[0], $data[1], $data[2], $data[3]]);
        if ($affected > 0) {
            $inserted++;
        }
    }

    return $inserted;
}
