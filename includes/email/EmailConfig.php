<?php

/**
 * Email Configuration and Initialization
 */
class EmailConfig
{
    private static $config = [
        'smtp_enabled' => false,
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_auth' => true,
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'smtp_timeout' => 30,
        'smtp_debug' => 0,
        'from_email' => '',
        'from_name' => '',
        'reply_to' => '',
        'charset' => 'UTF-8',
        'return_path' => '',
        'dkim_domain' => '',
        'dkim_selector' => '',
        'dkim_identity' => '',
        'dkim_private' => '',
        'smtp_allow_self_signed' => false
    ];

    public static function getConfig()
    {
        return self::$config;
    }

    public static function configure($config)
    {
        if (!is_array($config)) {
            return;
        }
        self::$config = array_merge(self::$config, $config);
    }

    public static function createFromBusinessSettings($pdo)
    {
        try {
            $rows = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'email'");
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            $be = class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_email', '') : (string)($settings['from_email'] ?? '');
            $bn = class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_name', 'WhimsicalFrog') : (string)($settings['from_name'] ?? 'WhimsicalFrog');
            $rt = isset($settings['reply_to']) && $settings['reply_to'] !== ''
                ? (string)$settings['reply_to']
                : (class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_support_email', $be) : (string)$be);

            $config = [
                'smtp_enabled' => ($settings['smtp_enabled'] ?? 'false') === 'true',
                'smtp_host' => $settings['smtp_host'] ?? '',
                'smtp_port' => (int)($settings['smtp_port'] ?? 587),
                'smtp_username' => $settings['smtp_username'] ?? '',
                'smtp_password' => '',
                'smtp_encryption' => $settings['smtp_encryption'] ?? 'tls',
                'from_email' => $be,
                'from_name' => $bn,
                'reply_to' => $rt,
                'return_path' => $settings['return_path'] ?? '',
                'dkim_domain' => $settings['dkim_domain'] ?? '',
                'dkim_selector' => $settings['dkim_selector'] ?? '',
                'dkim_identity' => $settings['dkim_identity'] ?? '',
                'smtp_allow_self_signed' => in_array(strtolower((string)($settings['smtp_allow_self_signed'] ?? 'false')), ['1','true','yes','on'], true)
            ];

            $smtpAuthRaw = $settings['smtp_auth'] ?? 'true';
            $config['smtp_auth'] = in_array(strtolower((string)$smtpAuthRaw), ['1','true','yes','on'], true);

            $smtpTimeoutRaw = $settings['smtp_timeout'] ?? 30;
            $config['smtp_timeout'] = (int)$smtpTimeoutRaw ?: 30;

            $smtpDebugRaw = $settings['smtp_debug'] ?? 'false';
            $config['smtp_debug'] = in_array(strtolower((string)$smtpDebugRaw), ['1','true','yes','on'], true) ? 2 : 0;

            $secPass = secret_get('smtp_password');
            if ($secPass !== null && $secPass !== '') {
                $config['smtp_password'] = $secPass;
            }
            $secUser = secret_get('smtp_username');
            if ($secUser !== null && $secUser !== '') {
                $config['smtp_username'] = $secUser;
            }
            $dkimKey = secret_get('dkim_private_key');
            if ($dkimKey !== null && $dkimKey !== '') {
                $config['dkim_private'] = $dkimKey;
            }

            self::configure($config);
            return true;
        } catch (Exception $e) {
            if (class_exists('Logger')) {
                Logger::error('Failed to load email settings from database', ['error' => $e->getMessage()]);
            }
            return false;
        }
    }
}
