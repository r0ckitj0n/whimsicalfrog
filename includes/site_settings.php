<?php
// Centralized site settings resolver: DB (BusinessSettings) only, no static/env fallbacks
// Callers must handle empty values by surfacing styled errors in the UI.

require_once __DIR__ . '/../api/config.php';

// Prefer BusinessSettings helper if available
@require_once __DIR__ . '/../api/business_settings_helper.php';

if (!function_exists('wf_setting')) {
    function wf_setting(string $dbKey, string $envKey, string $default = ''): string {
        try {
            if (class_exists('BusinessSettings')) {
                $val = BusinessSettings::get($dbKey, null);
                if (is_string($val) && $val !== '') return $val;
            }
        } catch (Throwable $e) {}
        return '';
    }
}

if (!function_exists('wf_site_name')) {
    function wf_site_name(): string {
        return wf_setting('business_name', 'SITE_NAME', '');
    }
}

if (!function_exists('wf_site_tagline')) {
    function wf_site_tagline(): string {
        return wf_setting('site_tagline', 'SITE_TAGLINE', '');
    }
}

if (!function_exists('wf_brand_logo_path')) {
    function wf_brand_logo_path(): string {
        $logo = wf_setting('business_logo_url', 'BRAND_LOGO_PATH', '');
        return (string)$logo;
    }
}

if (!function_exists('wf_app_url')) {
    function wf_app_url(): string {
        try {
            if (class_exists('BusinessSettings')) {
                $url = BusinessSettings::getSiteUrl();
                if (is_string($url) && $url !== '') return $url;
            }
        } catch (Throwable $e) {}
        return '';
    }
}

if (!function_exists('wf_business_email')) {
    function wf_business_email(): string {
        try {
            if (class_exists('BusinessSettings')) {
                $em = BusinessSettings::getBusinessEmail();
                if (is_string($em) && $em !== '') return $em;
            }
        } catch (Throwable $e) {}
        return '';
    }
}

if (!function_exists('wf_social_links')) {
    function wf_social_links(): array {
        $out = [
            'facebook' => '',
            'instagram' => '',
            'twitter' => '',
            'pinterest' => '',
        ];
        try {
            if (class_exists('BusinessSettings')) {
                $out['facebook'] = (string) BusinessSettings::get('business_facebook', '');
                $out['instagram'] = (string) BusinessSettings::get('business_instagram', '');
                $out['twitter'] = (string) BusinessSettings::get('business_twitter', '');
                $out['pinterest'] = (string) BusinessSettings::get('business_pinterest', '');
            }
        } catch (Throwable $e) {}
        return $out;
    }
}
