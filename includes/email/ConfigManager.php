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

        $isFlagOn = fn($k) => isset($post[$k]) && (string)$post[$k] === '1';
        $prefer = fn($pk, $ek) => !empty($post[$pk]) ? (string)$post[$pk] : ($existing[$ek] ?? '');

        $map = [
            'bcc_email'       => $isFlagOn('clear_bccEmail') ? '' : $prefer('bccEmail', 'bcc_email'),
            'reply_to'        => $isFlagOn('clear_replyTo') ? '' : $prefer('replyTo', 'reply_to'),
            'smtp_enabled'    => isset($post['smtpEnabled']) ? 'true' : 'false',
            'smtp_host'       => $isFlagOn('clear_smtpHost') ? '' : $prefer('smtpHost', 'smtp_host'),
            'smtp_port'       => $isFlagOn('clear_smtpPort') ? '' : $prefer('smtpPort', 'smtp_port'),
            'smtp_username'   => $isFlagOn('clear_smtpUsername') ? '' : $prefer('smtpUsername', 'smtp_username'),
            'smtp_encryption' => $isFlagOn('clear_smtpEncryption') ? '' : $prefer('smtpEncryption', 'smtp_encryption'),
        ];

        foreach ($map as $key => $val) {
            $type = ($key === 'smtp_enabled') ? 'boolean' : (($key === 'smtp_port') ? 'number' : 'text');
            $params = [
                ':category' => 'email',
                ':key' => $key,
                ':value' => (string)$val,
                ':type' => $type,
                ':display_name' => ucwords(str_replace('_', ' ', $key)),
                ':description' => 'Email setting ' . $key,
            ];
            Database::execute("INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description)
                VALUES (:category, :key, :value, :type, :display_name, :description)
                ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = CURRENT_TIMESTAMP", $params);
        }

        if (class_exists('BusinessSettings')) BusinessSettings::clearCache();
        Response::success(['message' => 'Config saved']);
    }
}
