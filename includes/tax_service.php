<?php

// Tax Service: Free ZIP-based tax using zippopotam.us (no API key) + state base rate table
// Note: This computes state-level base sales tax; local rates are not included.

require_once __DIR__ . '/../api/business_settings_helper.php';

class TaxService
{
    // Basic state base sales tax table (approximate). Update as needed.
    // Rates are decimals (e.g., 0.06 = 6%) and may change; verify periodically.
    private static $STATE_BASE_RATES = [
        'AL' => 0.04, 'AK' => 0.00, 'AZ' => 0.056, 'AR' => 0.065, 'CA' => 0.0725,
        'CO' => 0.029, 'CT' => 0.0635, 'DE' => 0.00, 'FL' => 0.06, 'GA' => 0.04,
        'HI' => 0.04, 'ID' => 0.06, 'IL' => 0.0625, 'IN' => 0.07, 'IA' => 0.06,
        'KS' => 0.065, 'KY' => 0.06, 'LA' => 0.0445, 'ME' => 0.055, 'MD' => 0.06,
        'MA' => 0.0625, 'MI' => 0.06, 'MN' => 0.06875, 'MS' => 0.07, 'MO' => 0.04225,
        'MT' => 0.00, 'NE' => 0.055, 'NV' => 0.0685, 'NH' => 0.00, 'NJ' => 0.06625,
        'NM' => 0.05125, 'NY' => 0.04, 'NC' => 0.0475, 'ND' => 0.05, 'OH' => 0.0575,
        'OK' => 0.045, 'OR' => 0.00, 'PA' => 0.06, 'RI' => 0.07, 'SC' => 0.06,
        'SD' => 0.045, 'TN' => 0.07, 'TX' => 0.0625, 'UT' => 0.061, 'VT' => 0.06,
        'VA' => 0.043, 'WA' => 0.065, 'WV' => 0.06, 'WI' => 0.05, 'WY' => 0.04,
        'DC' => 0.06,
    ];

    private static $zipStateCache = [];

    public static function getTaxRateForZip($zip)
    {
        $zip = trim((string)$zip);
        if ($zip === '') {
            return (float) BusinessSettings::get('tax_rate', 0.00);
        }
        $state = self::lookupStateByZip($zip);
        if ($state && isset(self::$STATE_BASE_RATES[$state])) {
            return (float) self::$STATE_BASE_RATES[$state];
        }
        // Fallback to business setting or sensible default if provided
        $fallback = (float) BusinessSettings::get('tax_default_fallback_rate', BusinessSettings::get('tax_rate', 0.00));
        return $fallback;
    }

    public static function lookupStateByZip($zip)
    {
        $zip = substr(preg_replace('/[^0-9]/', '', (string)$zip), 0, 5);
        if ($zip === '') {
            return null;
        }

        if (isset(self::$zipStateCache[$zip])) {
            return self::$zipStateCache[$zip];
        }

        $url = 'https://api.zippopotam.us/us/' . $zip;
        $resp = null;
        $http = 0;
        $err = '';
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
            ]);
            $resp = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
        }

        // Fallback to file_get_contents if cURL missing or failed
        if ($resp === false || $resp === null || $http < 200 || $http >= 300) {
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $resp = @file_get_contents($url, false, $ctx);
            $http = $resp !== false ? 200 : ($http ?: 0);
        }

        if ($resp === false || $resp === null || $http < 200 || $http >= 300) {
            Logger::error('TaxService ZIP lookup failed', [
                'zip' => $zip,
                'http_code' => $http,
                'error' => $err,
                'context' => 'tax_service'
            ]);
            self::$zipStateCache[$zip] = null;
            return null;
        }

        $data = json_decode($resp, true);
        $state = null;
        if (isset($data['places'][0]['state abbreviation'])) {
            $state = strtoupper($data['places'][0]['state abbreviation']);
        } elseif (isset($data['places'][0]['state'])) {
            // As a fallback, attempt to map full state name to abbreviation if needed (not implemented here)
            $state = strtoupper(substr($data['places'][0]['state'], 0, 2));
        }
        self::$zipStateCache[$zip] = $state;
        return $state;
    }
}
