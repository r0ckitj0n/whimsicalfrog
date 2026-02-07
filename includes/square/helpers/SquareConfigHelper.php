<?php
// includes/square/helpers/SquareConfigHelper.php

class SquareConfigHelper
{
    /**
     * Get Square settings from database with defaults and masking
     */
    public static function getSettings()
    {
        $defaults = [
            'square_enabled' => false,
            'square_environment' => 'sandbox',
            'square_sandbox_application_id' => '',
            'square_sandbox_access_token' => '',
            'square_sandbox_location_id' => '',
            'square_sandbox_webhook_signature_key' => '',
            'square_production_application_id' => '',
            'square_production_access_token' => '',
            'square_production_location_id' => '',
            'square_production_webhook_signature_key' => '',
            'auto_sync_enabled' => false,
            'sync_direction' => 'to_square',
            'sync_frequency' => 'manual',
            'sync_fields' => json_encode(['name', 'description', 'price', 'category', 'stock']),
            'price_sync_enabled' => true,
            'inventory_sync_enabled' => true,
            'category_mapping' => json_encode([]),
            'last_sync' => null,
            'sync_errors' => json_encode([]),
            'last_diag_status' => null,
            'last_diag_env' => null,
            'last_diag_timestamp' => null,
            'last_diag_message' => null,
        ];

        $rows = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'square'");
        $dbSettings = [];
        foreach ($rows as $r) {
            $dbSettings[$r['setting_key']] = $r['setting_value'];
        }

        $settings = array_merge($defaults, $dbSettings);

        // Parse JSON fields
        foreach (['sync_fields', 'category_mapping', 'sync_errors'] as $field) {
            if (isset($settings[$field]) && is_string($settings[$field])) {
                $settings[$field] = json_decode($settings[$field], true) ?: [];
            }
        }

        // Parse booleans
        foreach (['square_enabled', 'auto_sync_enabled', 'price_sync_enabled', 'inventory_sync_enabled'] as $field) {
            if (isset($settings[$field])) {
                $settings[$field] = in_array(strtolower($settings[$field]), ['true', '1', 1, true], true);
            }
        }

        // Mask secrets and report presence
        $secrets = [
            'square_sandbox_access_token', 'square_sandbox_webhook_signature_key',
            'square_production_access_token', 'square_production_webhook_signature_key',
            'square_access_token', 'square_webhook_signature_key'
        ];
        foreach ($secrets as $key) {
            $settings[$key . '_present'] = function_exists('secret_has') ? secret_has($key) : false;
            $settings[$key] = '';
        }

        return $settings;
    }

    /**
     * Save Square settings to database and secret store
     */
    public static function saveSettings($input)
    {
        if (!$input) throw new Exception('Invalid input data');

        $allowed = [
            'square_enabled', 'square_environment', 
            'square_sandbox_application_id', 'square_sandbox_access_token', 'square_sandbox_location_id', 'square_sandbox_webhook_signature_key',
            'square_production_application_id', 'square_production_access_token', 'square_production_location_id', 'square_production_webhook_signature_key',
            'square_application_id', 'square_access_token', 'square_location_id', 'square_webhook_signature_key',
            'auto_sync_enabled', 'sync_direction', 'sync_frequency', 'sync_fields',
            'price_sync_enabled', 'inventory_sync_enabled', 'category_mapping'
        ];

        $secretKeys = [
            'square_access_token', 'square_webhook_signature_key',
            'square_sandbox_access_token', 'square_sandbox_webhook_signature_key',
            'square_production_access_token', 'square_production_webhook_signature_key'
        ];

        Database::beginTransaction();
        try {
            $sql = "INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description) 
                    VALUES ('square', ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP";

            $savedCount = 0;
            foreach ($input as $key => $value) {
                if (!in_array($key, $allowed)) continue;

                if (in_array($key, $secretKeys, true)) {
                    if (is_string($value) && $value !== '') {
                        secret_set($key, $value);
                    }
                    $value = '';
                }

                if (is_array($value)) {
                    $value = json_encode($value);
                    $type = 'json';
                } elseif (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                    $type = 'boolean';
                } else {
                    $type = 'text';
                }

                $displayName = ucwords(str_replace('_', ' ', $key));
                $description = self::getSettingDescription($key);

                if (Database::execute($sql, [$key, $value, $type, $displayName, $description]) > 0) {
                    $savedCount++;
                }
            }
            Database::commit();
            return ['success' => true, 'message' => "Saved $savedCount Square settings successfully"];
        } catch (Exception $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Resolve actual credentials for current environment
     */
    public static function getResolvedCredentials()
    {
        $rows = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'square'");
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }

        $env = $settings['square_environment'] ?? 'sandbox';
        $prefix = ($env === 'production') ? 'square_production_' : 'square_sandbox_';

        $resolved = [
            'enabled' => in_array(strtolower($settings['square_enabled'] ?? ''), ['true', '1'], true),
            'environment' => $env,
            'application_id' => $settings[$prefix . 'application_id'] ?? $settings['square_application_id'] ?? '',
            'location_id' => $settings[$prefix . 'location_id'] ?? $settings['square_location_id'] ?? '',
            'access_token' => '',
            'webhook_signature_key' => '',
            'inventory_sync_enabled' => in_array(strtolower($settings['inventory_sync_enabled'] ?? ''), ['true', '1'], true),
            'price_sync_enabled' => in_array(strtolower($settings['price_sync_enabled'] ?? ''), ['true', '1'], true),
            'sync_fields' => json_decode($settings['sync_fields'] ?? '[]', true) ?: []
        ];

        // Resolve secrets (fallback to DB values for compatibility with older saves)
        $tokenKey = $prefix . 'access_token';
        $resolved['access_token'] = secret_get($tokenKey)
            ?: secret_get('square_access_token')
            ?: ($settings[$tokenKey] ?? $settings['square_access_token'] ?? '');
        
        $whKey = $prefix . 'webhook_signature_key';
        $resolved['webhook_signature_key'] = secret_get($whKey)
            ?: secret_get('square_webhook_signature_key')
            ?: ($settings[$whKey] ?? $settings['square_webhook_signature_key'] ?? '');

        return $resolved;
    }

    public static function getSettingDescription($key)
    {
        $descriptions = [
            'square_enabled' => 'Enable Square integration',
            'square_environment' => 'Square environment (sandbox or production)',
            'square_application_id' => 'Square Application ID',
            'square_access_token' => 'Square Access Token',
            'square_location_id' => 'Square Location ID',
            'square_webhook_signature_key' => 'Webhook signature key',
            'auto_sync_enabled' => 'Enable automatic synchronization',
            'sync_direction' => 'Synchronization direction',
            'sync_frequency' => 'Sync frequency',
            'sync_fields' => 'Fields to synchronize',
            'price_sync_enabled' => 'Enable price synchronization',
            'inventory_sync_enabled' => 'Enable inventory synchronization',
            'category_mapping' => 'Category mapping'
        ];
        return $descriptions[$key] ?? 'Square integration setting';
    }
}
