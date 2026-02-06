<?php

trait BusinessSettingsConvenience
{
    /**
     * Convenience methods for common settings
     */
    public static function getBusinessName()
    {
        return (string) self::get('business_name', '');
    }

    public static function getBusinessDomain()
    {
        return (string) self::get('business_domain', '');
    }

    public static function getBusinessEmail()
    {
        return (string) self::get('business_email', '');
    }

    /**
     * Canonical business address components and composed block
     */
    public static function getBusinessAddressLine1()
    {
        return trim((string) self::get('business_address', ''));
    }

    public static function getBusinessAddressLine2()
    {
        return trim((string) self::get('business_address2', ''));
    }

    public static function getBusinessCity()
    {
        return trim((string) self::get('business_city', ''));
    }

    public static function getBusinessState()
    {
        return trim((string) self::get('business_state', ''));
    }

    public static function getBusinessPostal()
    {
        // Canonical postal/ZIP; legacy business_zip is deprecated and not used
        return trim((string) self::get('business_postal', ''));
    }

    public static function getBusinessAddressBlock()
    {
        // Compose address for display: line1, optional line2, and City ST ZIP on one line
        $l1 = self::getBusinessAddressLine1();
        $l2 = self::getBusinessAddressLine2();
        $city = self::getBusinessCity();
        $state = self::getBusinessState();
        $zip = self::getBusinessPostal();

        $parts = [];
        if ($l1 !== '') {
            $parts[] = $l1;
        }
        if ($l2 !== '') {
            $parts[] = $l2;
        }
        $cityLine = trim($city . ($city !== '' && $state !== '' ? ', ' : '')) . $state;
        $cityZip = trim($cityLine . ($zip !== '' ? ' ' . $zip : ''));
        if ($cityZip !== '') {
            $parts[] = $cityZip;
        }

        // If components are largely missing, fall back to legacy multi-line blob
        if (empty($parts)) {
            $blob = trim((string) self::get('business_address', ''));
            return $blob;
        }
        return implode("\n", $parts);
    }

    public static function getAdminEmail()
    {
        return (string) self::get('admin_email', '');
    }

    public static function getPrimaryColor()
    {
        $explicit = self::get('business_brand_primary', null);
        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }
        $legacy = self::get('primary_color', null);
        if (is_string($legacy) && trim($legacy) !== '') {
            return trim($legacy);
        }
        return '';
    }

    public static function getSecondaryColor()
    {
        $explicit = self::get('business_brand_secondary', null);
        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }
        $legacy = self::get('secondary_color', null);
        if (is_string($legacy) && trim($legacy) !== '') {
            return trim($legacy);
        }
        return '';
    }

    public static function getPaymentMethods()
    {
        $val = self::get('payment_methods', []);
        return is_array($val) ? $val : [];
    }

    public static function getShippingMethods()
    {
        $val = self::get('shipping_methods', []);
        return is_array($val) ? $val : [];
    }

    public static function getPaymentStatuses()
    {
        $val = self::get('payment_statuses', []);
        return is_array($val) ? $val : [];
    }

    public static function getOrderStatuses()
    {
        $val = self::get('order_statuses', []);
        return is_array($val) ? $val : [];
    }

    public static function getTaxRate()
    {
        return self::get('tax_rate', null);
    }

    public static function isTaxEnabled()
    {
        $raw = self::get('tax_enabled', null);
        return ($raw === null || $raw === '') ? null : self::getBooleanSetting('tax_enabled', false);
    }

    public static function isMaintenanceMode()
    {
        return self::get('maintenance_mode', false);
    }

    /**
     * Robust boolean getter that accepts common truthy strings
     */
    public static function getBooleanSetting($key, $default = false)
    {
        $val = self::get($key, null);
        if ($val === null) {
            return (bool) $default;
        }
        if (is_bool($val)) {
            return $val;
        }
        $str = strtolower(trim((string) $val));
        return in_array($str, ['1', 'true', 'yes', 'on', 'y'], true);
    }

    /**
     * Retrieve shipping configuration.
     * When $strict is true, throws InvalidArgumentException on missing/invalid keys.
     * When false, applies sane defaults and reports which keys defaulted in 'usedDefaults'.
     */
    public static function getShippingConfig($strict = false)
    {
        $keys = [
            'free_shipping_threshold' => 50.00,
            'local_delivery_fee' => 5.00,
            'shipping_rate_usps' => 8.99,
            'shipping_rate_fedex' => 12.99,
            'shipping_rate_ups' => 12.99,
        ];

        $out = [
            'free_shipping_threshold' => null,
            'local_delivery_fee' => null,
            'shipping_rate_usps' => null,
            'shipping_rate_fedex' => null,
            'shipping_rate_ups' => null,
            'usedDefaults' => [],
        ];

        foreach ($keys as $k => $def) {
            $val = self::get($k, null);
            if ($val === null || $val === '') {
                if ($strict) {
                    throw new InvalidArgumentException("Missing required setting: {$k}");
                }
                $out[$k] = (float) $def;
                $out['usedDefaults'][] = $k;
                continue;
            }
            if (!is_numeric($val)) {
                if ($strict) {
                    throw new InvalidArgumentException("Invalid numeric setting: {$k}");
                }
                $out[$k] = (float) $def;
                $out['usedDefaults'][] = $k;
                continue;
            }
            $out[$k] = (float) $val;
        }

        return $out;
    }

    /**
     * Retrieve tax configuration.
     * - enabled: bool via isTaxEnabled()
     * - rate: float via getTaxRate()
     * - taxShipping: robust boolean for 'tax_shipping'
     * Strict mode enforces: if enabled and (rate <= 0) or tax_shipping missing -> throw.
     */
    public static function getTaxConfig($strict = false)
    {
        $enabled = (bool) self::isTaxEnabled();
        $rate = (float) self::getTaxRate();

        // Detect presence of tax_shipping key distinctly from its value
        $taxShippingRaw = self::get('tax_shipping', null);
        $hasTaxShippingKey = ($taxShippingRaw !== null && $taxShippingRaw !== '');
        $taxShipping = self::getBooleanSetting('tax_shipping', false);

        if ($strict) {
            if ($enabled && ($rate <= 0)) {
                throw new InvalidArgumentException('Missing or invalid setting: tax_rate');
            }
            // Require explicit tax_shipping only when tax is enabled; otherwise default to false
            if ($enabled && !$hasTaxShippingKey) {
                throw new InvalidArgumentException('Missing required setting: tax_shipping');
            }
        }

        return [
            'enabled' => $enabled,
            'rate' => $rate,
            'taxShipping' => $taxShipping,
            'hasTaxShippingKey' => $hasTaxShippingKey,
        ];
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
        if ($domain === '') {
            throw new RuntimeException('Business domain is not configured.');
        }
        $protocol = (strpos($domain, 'localhost') !== false || strpos($domain, '127.0.0.1') !== false) ? 'http://' : 'https://';
        $url = $protocol . $domain;
        if ($path && $path[0] !== '/') {
            $url .= '/';
        }
        $url .= $path;

        return $url;
    }
}
