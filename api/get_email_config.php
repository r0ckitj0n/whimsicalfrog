<?php
header('Content-Type: application/json');

// Include the current email configuration
require_once __DIR__ . '/email_config.php';

try {
    // Check if constants are defined
    $config = [
        'fromEmail' => defined('FROM_EMAIL') ? FROM_EMAIL : '',
        'fromName' => defined('FROM_NAME') ? FROM_NAME : '',
        'adminEmail' => defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '',
        'bccEmail' => defined('BCC_EMAIL') ? BCC_EMAIL : '',
        'smtpEnabled' => defined('SMTP_ENABLED') ? SMTP_ENABLED : false,
        'smtpHost' => defined('SMTP_HOST') ? SMTP_HOST : '',
        'smtpPort' => defined('SMTP_PORT') ? SMTP_PORT : '587',
        'smtpUsername' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
        'smtpPassword' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',
        'smtpEncryption' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls'
    ];
    
    echo json_encode([
        'success' => true,
        'config' => $config
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load email configuration: ' . $e->getMessage()
    ]);
}
?> 