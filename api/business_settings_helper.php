<?php

// Business Settings Helper
// Provides easy access to business settings throughout the application

require_once __DIR__ . '/config.php';
require_once 'business_settings_traits.php';

class BusinessSettings
{
    use BusinessSettingsConvenience;

    private static $cache = [];
    private static function dbUp(): bool
    {
        try {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
            if ($isLocal) {
                $disable = getenv('WF_DB_DEV_DISABLE');
                if ($disable === '1' || strtolower((string) $disable) === 'true') {
                    return false;
                }
                return \Database::isAvailableQuick(0.6);
            }
            // Non-local: assume available
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }


    /**
     * Get a business setting value
     */
    public static function get($key, $default = null)
    {
        if (!self::dbUp()) {
            return $default;
        }
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $result = Database::queryOne(
                "SELECT setting_value, setting_type, category, updated_at
                 FROM business_settings
                 WHERE setting_key = ?
                 ORDER BY
                   (category = 'business_info') DESC,
                   (category = 'business') DESC,
                   (category = 'branding') DESC,
                   (category = 'ecommerce') DESC,
                   updated_at DESC",
                [$key]
            );

            if (!$result) {
                self::$cache[$key] = $default;
                return $default;
            }

            $value = self::convertValue($result['setting_value'], $result['setting_type']);
            self::$cache[$key] = $value;
            return $value;

        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Get multiple settings by category
     */
    public static function getByCategory($category)
    {
        if (!self::dbUp()) {
            return [];
        }
        try {
            // Order by key then updated_at ASC so the most recent row appears last per key
            // ensuring the final assigned value is the latest
            $results = Database::queryAll(
                "SELECT setting_key, setting_value, setting_type FROM business_settings WHERE category = ? ORDER BY setting_key ASC, updated_at ASC",
                [$category]
            );

            $settings = [];
            foreach ($results as $result) {
                $settings[$result['setting_key']] = self::convertValue($result['setting_value'], $result['setting_type']);
            }

            return $settings;

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get all settings grouped by category
     */
    public static function getAll()
    {
        if (!self::dbUp()) {
            return [];
        }
        try {
            $results = Database::queryAll("SELECT * FROM business_settings ORDER BY category, display_order");

            $grouped = [];
            foreach ($results as $result) {
                $value = self::convertValue($result['setting_value'], $result['setting_type']);
                $grouped[$result['category']][$result['setting_key']] = $value;
            }

            return $grouped;

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Convert setting value based on type
     */
    private static function convertValue($value, $type)
    {
        switch ($type) {
            case 'boolean':
                return in_array(strtolower($value), ['true', '1']);
            case 'number':
                return is_numeric($value) ? (float) $value : 0;
            case 'json':
                $decoded = json_decode($value, true);
                return $decoded !== null ? $decoded : [];
            default:
                return $value;
        }
    }

    /**
     * Clear cache (useful after updates)
     */
    public static function clearCache()
    {
        self::$cache = [];
    }
}

require_once 'business_settings_compat.php';
