<?php
/**
 * Email Config Manager Logic
 */

class EmailConfigManager
{
    public static function handleTestEmail()
    {
        $testEmail = $_POST['testEmail'] ?? '';
        if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid test email', null, 400);
        }

        $pdo = Database::getInstance();
        EmailHelper::createFromBusinessSettings($pdo);

        $settings = BusinessSettings::getByCategory('email');
        $fromEmail = (string) BusinessSettings::get('business_email', '');
        $fromName  = (string) BusinessSettings::get('business_name', '');
        
        $smtpEnabledVal = $settings['smtp_enabled'] ?? false;
        $smtpEnabled = is_bool($smtpEnabledVal) ? $smtpEnabledVal : in_array(strtolower((string)$smtpEnabledVal), ['1','true','yes','on'], true);
        $bcc = (string)($settings['bcc_email'] ?? '');

        $subject = 'Test Email from WhimsicalFrog';
        $html = "<h1>Test Email</h1><p>Sent from $fromName ($fromEmail)</p>";

        try {
            EmailHelper::send($testEmail, $subject, $html, [
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'reply_to' => $fromEmail,
                'is_html' => true,
                'bcc' => $bcc ?: []
            ]);
            Response::success(['message' => 'Test email sent']);
        } catch (Exception $e) {
            Response::error('Failed to send test email: ' . $e->getMessage());
        }
    }

    public static function handleSaveConfig($post, $existing)
    {
        $smtpUsername = trim($post['smtpUsername'] ?? '');
        $smtpPassword = trim($post['smtpPassword'] ?? '');
        
        if (isset($post['clear_smtpUsername']) && $post['clear_smtpUsername'] === '1') @secret_delete('smtp_username');
        if (isset($post['clear_smtpPassword']) && $post['clear_smtpPassword'] === '1') @secret_delete('smtp_password');
        if ($smtpUsername !== '') secret_set('smtp_username', $smtpUsername);
        if ($smtpPassword !== '') secret_set('smtp_password', $smtpPassword);

        $isFlagOn = fn($k) => isset($post[$k]) && (string) $post[$k] === '1';
        $asBool = function ($value, $default = false) {
            if ($value === null) {
                return (bool) $default;
            }
            if (is_bool($value)) {
                return $value;
            }
            $normalized = strtolower(trim((string) $value));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        };
        $prefer = fn($pk, $ek) => !empty($post[$pk]) ? (string)$post[$pk] : ($existing[$ek] ?? '');

        $settingsToPersist = [
            ['category' => 'email', 'key' => 'admin_email', 'value' => trim((string) ($post['adminEmail'] ?? '')), 'type' => 'text'],
            ['category' => 'email', 'key' => 'bcc_email', 'value' => $isFlagOn('clear_bccEmail') ? '' : $prefer('bccEmail', 'bcc_email'), 'type' => 'text'],
            ['category' => 'email', 'key' => 'reply_to', 'value' => $isFlagOn('clear_replyTo') ? '' : $prefer('replyTo', 'reply_to'), 'type' => 'text'],
            ['category' => 'email', 'key' => 'smtp_enabled', 'value' => $asBool($post['smtpEnabled'] ?? null, false) ? 'true' : 'false', 'type' => 'boolean'],
            ['category' => 'email', 'key' => 'smtp_host', 'value' => $isFlagOn('clear_smtpHost') ? '' : $prefer('smtpHost', 'smtp_host'), 'type' => 'text'],
            ['category' => 'email', 'key' => 'smtp_port', 'value' => $isFlagOn('clear_smtpPort') ? '' : $prefer('smtpPort', 'smtp_port'), 'type' => 'number'],
            ['category' => 'email', 'key' => 'smtp_username', 'value' => $isFlagOn('clear_smtpUsername') ? '' : $prefer('smtpUsername', 'smtp_username'), 'type' => 'text'],
            ['category' => 'email', 'key' => 'smtp_encryption', 'value' => $isFlagOn('clear_smtpEncryption') ? '' : $prefer('smtpEncryption', 'smtp_encryption'), 'type' => 'text'],
            ['category' => 'business_info', 'key' => 'business_email', 'value' => trim((string) ($post['fromEmail'] ?? '')), 'type' => 'text'],
            ['category' => 'business_info', 'key' => 'business_name', 'value' => trim((string) ($post['fromName'] ?? '')), 'type' => 'text'],
            ['category' => 'business_info', 'key' => 'business_support_email', 'value' => trim((string) ($post['supportEmail'] ?? '')), 'type' => 'text'],
        ];

        foreach ($settingsToPersist as $setting) {
            $params = [
                ':category' => (string) $setting['category'],
                ':key' => (string) $setting['key'],
                ':value_insert' => (string) $setting['value'],
                ':value_update' => (string) $setting['value'],
                ':type' => (string) $setting['type'],
                ':display_name' => ucwords(str_replace('_', ' ', (string) $setting['key'])),
                ':description' => 'Email setting ' . (string) $setting['key'],
            ];
            Database::execute("INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description)
                VALUES (:category, :key, :value, :type, :display_name, :description)
                ON DUPLICATE KEY UPDATE setting_value = :value_update, updated_at = CURRENT_TIMESTAMP", [
                    ':category' => $params[':category'],
                    ':key' => $params[':key'],
                    ':value' => $params[':value_insert'],
                    ':type' => $params[':type'],
                    ':display_name' => $params[':display_name'],
                    ':description' => $params[':description'],
                    ':value_update' => $params[':value_update'],
                ]);
        }

        if (class_exists('BusinessSettings')) BusinessSettings::clearCache();
        Response::success(['message' => 'Config saved']);
    }
}
