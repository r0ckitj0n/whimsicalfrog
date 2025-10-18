<?php
// Centralized site settings resolver: DB (BusinessSettings) → env → default
// Provides stable values during bootstrap and in templates without hard DB coupling.

require_once __DIR__ . '/../api/config.php';

// Prefer BusinessSettings helper if available
@require_once __DIR__ . '/../api/business_settings_helper.php';

if (!function_exists('wf_setting')) {
    function wf_setting(string $dbKey, string $envKey, string $default = ''): string {
        // 1) DB via BusinessSettings
        try {
            if (class_exists('BusinessSettings')) {
                $val = BusinessSettings::get($dbKey, null);
                if (is_string($val) && $val !== '') return $val;
            }
        } catch (Throwable $e) {}
        // 2) Env fallback
        try {
            if (function_exists('wf_env')) {
                $env = wf_env($envKey, '');
                if (is_string($env) && $env !== '') return $env;
            }
        } catch (Throwable $e) {}
        // 3) Default
        return $default;
    }
}

if (!function_exists('wf_site_name')) {
    function wf_site_name(): string {
        // DB keys commonly used: business_name; env SITE_NAME
        return wf_setting('business_name', 'SITE_NAME', defined('SITE_NAME') ? SITE_NAME : 'Your Site');
    }
}

if (!function_exists('wf_site_tagline')) {
    function wf_site_tagline(): string {
        return wf_setting('site_tagline', 'SITE_TAGLINE', defined('SITE_TAGLINE') ? SITE_TAGLINE : '');
    }
}

if (!function_exists('wf_brand_logo_path')) {
    function wf_brand_logo_path(): string {
        // DB key: business_logo_url; env BRAND_LOGO_PATH
        $logo = wf_setting('business_logo_url', 'BRAND_LOGO_PATH', defined('BRAND_LOGO_PATH') ? BRAND_LOGO_PATH : '/images/logos/logo-whimsicalfrog.webp');
        return (string)$logo;
    }
}

if (!function_exists('wf_app_url')) {
    function wf_app_url(): string {
        // DB: business_domain (via BusinessSettings::getSiteUrl()), env APP_URL
        try {
            if (class_exists('BusinessSettings')) {
                $url = BusinessSettings::getSiteUrl();
                if (is_string($url) && $url !== '') return $url;
            }
        } catch (Throwable $e) {}
        try {
            if (defined('APP_URL') && APP_URL) return APP_URL;
            if (function_exists('wf_env')) {
                $env = wf_env('APP_URL', '');
                if ($env) return $env;
            }
        } catch (Throwable $e) {}
        return '';
    }
}

if (!function_exists('wf_business_email')) {
    function wf_business_email(): string {
        // DB key: business_email; env derived default hello@<host> or example
        try {
            if (class_exists('BusinessSettings')) {
                $em = BusinessSettings::getBusinessEmail();
                if (is_string($em) && $em !== '') return $em;
            }
        } catch (Throwable $e) {}
        $app = wf_app_url();
        $host = $app ? parse_url($app, PHP_URL_HOST) : null;
        return $host ? ('hello@' . $host) : 'hello@example.com';
    }
}

if (!function_exists('wf_social_links')) {
    function wf_social_links(): array {
        // Prefer DB keys the admin can edit; fallback to env SOCIAL_*
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
        // Env fallback per key if empty
        try {
            if (function_exists('wf_env')) {
                if ($out['facebook'] === '') $out['facebook'] = (string) wf_env('SOCIAL_FACEBOOK', '');
                if ($out['instagram'] === '') $out['instagram'] = (string) wf_env('SOCIAL_INSTAGRAM', '');
                if ($out['twitter'] === '') $out['twitter'] = (string) wf_env('SOCIAL_TWITTER', '');
                if ($out['pinterest'] === '') $out['pinterest'] = (string) wf_env('SOCIAL_PINTEREST', '');
            }
        } catch (Throwable $e) {}
        return $out;
    }
}
