<?php
// Business Settings Helper
// Provides easy access to business settings throughout the application

require_once __DIR__ . '/config.php';

class BusinessSettings
{
    private static $cache = [];
    private static $pdo = null;

    private static function getPDO()
    {
        if (self::$pdo === null) {
            require_once __DIR__ . '/config.php';

            // Check if variables are properly set
            if (!isset($dsn) || $dsn === null) {
                throw new Exception('Database configuration not available');
            }

            try {
                self::$pdo = Database::getInstance();
            } catch (Exception $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw $e;
            }
        }
        return self::$pdo;
    }

    /**
     * Get a business setting value
     */
    public static function get($key, $default = null)
    {
        // Check cache first
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $pdo = self::getPDO();
            // Prefer settings from the 'ecommerce' category when duplicates exist,
            // then fall back to the most recently updated row.
            $stmt = $pdo->prepare(
                "SELECT setting_value, setting_type, category, updated_at
                 FROM business_settings
                 WHERE setting_key = ?
                 ORDER BY (category = 'ecommerce') DESC, updated_at DESC"
            );
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

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
        try {
            $pdo = self::getPDO();
            $stmt = $pdo->prepare("SELECT setting_key, setting_value, setting_type FROM business_settings WHERE category = ? ORDER BY display_order");
            $stmt->execute([$category]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        try {
            $pdo = self::getPDO();
            $stmt = $pdo->query("SELECT * FROM business_settings ORDER BY category, display_order");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                return is_numeric($value) ? (float)$value : 0;
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

    /**
     * Convenience methods for common settings
     */
    public static function getBusinessName()
    {
        return self::get('business_name', 'WhimsicalFrog');
    }

    public static function getBusinessDomain()
    {
        return self::get('business_domain', 'whimsicalfrog.us');
    }

    public static function getBusinessEmail()
    {
        return self::get('business_email', 'orders@whimsicalfrog.us');
    }

    public static function getAdminEmail()
    {
        return self::get('admin_email', 'admin@whimsicalfrog.us');
    }

    public static function getPrimaryColor()
    {
        return self::get('primary_color', '#87ac3a');
    }

    public static function getSecondaryColor()
    {
        return self::get('secondary_color', '#6b8e23');
    }

    public static function getPaymentMethods()
    {
        return self::get('payment_methods', ['Credit Card', 'PayPal', 'Check', 'Cash']);
    }

    public static function getShippingMethods()
    {
        return self::get('shipping_methods', ['Customer Pickup', 'Local Delivery', 'USPS', 'FedEx', 'UPS']);
    }

    public static function getPaymentStatuses()
    {
        return self::get('payment_statuses', ['Pending', 'Processing', 'Received', 'Refunded', 'Failed']);
    }

    public static function getOrderStatuses()
    {
        return self::get('order_statuses', ['Pending', 'Processing', 'Completed', 'Cancelled', 'Refunded']);
    }

    public static function getTaxRate()
    {
        return self::get('tax_rate', 0.00);
    }

    public static function isTaxEnabled()
    {
        return self::get('tax_enabled', false);
    }

    public static function isMaintenanceMode()
    {
        return self::get('maintenance_mode', false);
    }

    /**
     * Generate CSS variables for brand colors
     */
    public static function getCSSVariables()
    {
        $colors = self::getByCategory('branding');
        $css = ":root {\n";

        foreach ($colors as $key => $value) {
            if (strpos($key, '_color') !== false) {
                $cssVar = '-' . str_replace('_', '-', $key);
                $css .= "    {$cssVar}: {$value};\n";
            }
        }

        $css .= "}\n";
        return $css;
    }

    /**
     * Get site URL with protocol
     */
    public static function getSiteUrl($path = '')
    {
        $domain = self::getBusinessDomain();
        $protocol = 'https://';

        if (strpos($domain, 'localhost') !== false || strpos($domain, '127.0.0.1') !== false) {
            $protocol = 'http://';
        }

        $url = $protocol . $domain;
        if ($path && $path[0] !== '/') {
            $url .= '/';
        }
        $url .= $path;

        return $url;
    }
}

// Global helper functions for backward compatibility
function getBusinessSetting($key, $default = null)
{
    return BusinessSettings::get($key, $default);
}

function getBusinessName()
{
    return BusinessSettings::getBusinessName();
}

function getBusinessEmail()
{
    return BusinessSettings::getBusinessEmail();
}

function getPrimaryColor()
{
    return BusinessSettings::getPrimaryColor();
}

function getPaymentMethods()
{
    return BusinessSettings::getPaymentMethods();
}

function getShippingMethods()
{
    return BusinessSettings::getShippingMethods();
}

/**
 * Get a random cart button text from the configured variations
 * @return string Random cart button text
 */
function getRandomCartButtonText()
{
    try {
        $cartTexts = getBusinessSetting('cart_button_texts', '["Add to Cart"]');

        // Parse JSON if it's a string
        if (is_string($cartTexts)) {
            $cartTexts = json_decode($cartTexts, true);
        }

        // Ensure we have an array with at least one option
        if (!is_array($cartTexts) || empty($cartTexts)) {
            return 'Add to Cart';
        }

        // Return a random cart button text
        return $cartTexts[array_rand($cartTexts)];

    } catch (Exception $e) {
        // Fallback to default if there's any error
        return 'Add to Cart';
    }
}