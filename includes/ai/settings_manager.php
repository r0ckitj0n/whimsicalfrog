<?php
require_once __DIR__ . '/../secret_store.php';

class AISettingsManager
{

    public static function getAISettings()
    {
        $defaults = [
            'ai_provider' => 'jons_ai',
            'openai_model' => 'gpt-4o',
            'anthropic_model' => 'claude-3-5-sonnet-20241022',
            'google_model' => 'gemini-1.5-pro',
            'meta_model' => 'meta-llama/llama-3.1-405b-instruct',
            'ai_temperature' => 0.7,
            'ai_max_tokens' => 1000,
            'ai_timeout' => 30,
            'fallback_to_local' => true,
            'ai_brand_voice' => '',
            'ai_content_tone' => 'professional'
        ];

        try {
            $results = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'ai'");
            foreach ($results as $row) {
                $key = $row['setting_key'];
                $value = $row['setting_value'];
                if (in_array($key, ['ai_temperature']))
                    $defaults[$key] = (float) $value;
                elseif (in_array($key, ['ai_max_tokens', 'ai_timeout']))
                    $defaults[$key] = (int) $value;
                elseif (in_array($key, ['fallback_to_local']))
                    $defaults[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                else
                    $defaults[$key] = $value;
            }
        } catch (Exception $e) {
            error_log("Error loading AI settings: " . $e->getMessage());
        }

        $defaults['openai_key_present'] = secret_has('openai_api_key');
        $defaults['anthropic_key_present'] = secret_has('anthropic_api_key');
        $defaults['google_key_present'] = secret_has('google_api_key');
        $defaults['meta_key_present'] = secret_has('meta_api_key');

        return $defaults;
    }

    public static function updateAISettings($settings)
    {
        $valid = ['ai_provider', 'openai_model', 'anthropic_model', 'google_model', 'meta_model', 'ai_temperature', 'ai_max_tokens', 'ai_timeout', 'fallback_to_local', 'ai_brand_voice', 'ai_content_tone'];
        $secrets = ['openai_api_key', 'anthropic_api_key', 'google_api_key', 'meta_api_key'];

        foreach ($settings as $key => $value) {
            if (in_array($key, $secrets)) {
                if (is_string($value) && $value !== '')
                    secret_set($key, $value);
                continue;
            }
            if (!in_array($key, $valid))
                continue;

            $type = is_bool($value) ? 'boolean' : (is_numeric($value) ? 'number' : 'text');
            $val = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

            Database::execute(
                "INSERT INTO business_settings (category, setting_key, setting_value, setting_type) 
                 VALUES ('ai', ?, ?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$key, $val, $type]
            );
        }
        return true;
    }
}
