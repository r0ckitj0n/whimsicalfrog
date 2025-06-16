<?php
// Prevent any output before JSON
ob_start();
error_reporting(0); // Suppress PHP errors from being output
ini_set('display_errors', 0);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'test') {
    handleTestEmail();
} elseif ($action === 'save') {
    handleSaveConfig();
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function handleTestEmail() {
    $testEmail = $_POST['testEmail'] ?? '';
    if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid test email address']);
        return;
    }
    
    // Create test email configuration from form data
    $fromEmail = $_POST['fromEmail'] ?? 'orders@whimsicalfrog.us';
    $fromName = $_POST['fromName'] ?? 'WhimsicalFrog';
    $smtpEnabled = isset($_POST['smtpEnabled']);
    
    $subject = "Test Email from WhimsicalFrog";
    $html = createTestEmailHtml($fromEmail, $fromName, $smtpEnabled);
    
    $success = false;
    $errorMessage = '';
    
    try {
        if ($smtpEnabled) {
            // Use SMTP - check if PHPMailer is available
            $phpmailerPath = __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
            if (file_exists($phpmailerPath)) {
                require_once $phpmailerPath;
                require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
                require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
                
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                
                $mail->isSMTP();
                $mail->Host = $_POST['smtpHost'] ?? 'smtp.ionos.com';
                $mail->SMTPAuth = true;
                $mail->Username = $_POST['smtpUsername'] ?? $fromEmail;
                $mail->Password = $_POST['smtpPassword'] ?? '';
                $mail->SMTPSecure = $_POST['smtpEncryption'] ?? 'tls';
                $mail->Port = intval($_POST['smtpPort'] ?? 587);
                
                $mail->setFrom($fromEmail, $fromName);
                $mail->addAddress($testEmail);
                
                if (!empty($_POST['bccEmail'])) {
                    $mail->addBCC($_POST['bccEmail']);
                }
                
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $html;
                
                $mail->send();
                $success = true;
            } else {
                // Fallback to basic SMTP if PHPMailer not available
                $success = sendSmtpEmail($testEmail, $subject, $html, $fromEmail, $fromName, $_POST);
            }
        } else {
            // Use PHP mail()
            $headers = [
                'From: ' . $fromName . ' <' . $fromEmail . '>',
                'Reply-To: ' . $fromEmail,
                'X-Mailer: PHP/' . phpversion(),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8'
            ];
            
            if (!empty($_POST['bccEmail'])) {
                $headers[] = 'Bcc: ' . $_POST['bccEmail'];
            }
            
            $headerString = implode("\r\n", $headers);
            $success = mail($testEmail, $subject, $html, $headerString);
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        error_log("Test email error: " . $errorMessage);
    }
    
    // Log the test email
    try {
        require_once 'email_logger.php';
        session_start();
        $createdBy = $_SESSION['user']['userId'] ?? $_SESSION['user']['username'] ?? 'admin';
        
        if ($success) {
            logTestEmail($testEmail, $fromEmail, $subject, $html, 'sent', null, $createdBy);
        } else {
            $logError = $errorMessage ?: 'Email sending failed';
            logTestEmail($testEmail, $fromEmail, $subject, $html, 'failed', $logError, $createdBy);
        }
    } catch (Exception $e) {
        // Log the logging error but don't fail the response
        error_log("Email logging error: " . $e->getMessage());
    }
    
    // Clean any buffered output and send JSON response
    ob_clean();
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Test email sent successfully!']);
    } else {
        $logError = $errorMessage ?: 'Email sending failed';
        echo json_encode(['success' => false, 'error' => 'Failed to send test email: ' . $logError]);
    }
}

function sendSmtpEmail($to, $subject, $html, $fromEmail, $fromName, $config) {
    $host = $config['smtpHost'] ?? 'smtp.ionos.com';
    $port = intval($config['smtpPort'] ?? 587);
    $username = $config['smtpUsername'] ?? $fromEmail;
    $password = $config['smtpPassword'] ?? '';
    $encryption = $config['smtpEncryption'] ?? 'tls';
    
    // Create socket connection
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    $socket = stream_socket_client(
        ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port,
        $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context
    );
    
    if (!$socket) {
        throw new Exception("Failed to connect to SMTP server: $errstr ($errno)");
    }
    
    // SMTP conversation
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') {
        throw new Exception("SMTP connection failed: $response");
    }
    
    // EHLO
    fputs($socket, "EHLO localhost\r\n");
    $response = fgets($socket, 515);
    
    // STARTTLS if needed
    if ($encryption === 'tls') {
        fputs($socket, "STARTTLS\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            throw new Exception("STARTTLS failed: $response");
        }
        
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        // EHLO again after STARTTLS
        fputs($socket, "EHLO localhost\r\n");
        $response = fgets($socket, 515);
    }
    
    // AUTH LOGIN
    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '334') {
        throw new Exception("AUTH LOGIN failed: $response");
    }
    
    fputs($socket, base64_encode($username) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '334') {
        throw new Exception("Username authentication failed: $response");
    }
    
    fputs($socket, base64_encode($password) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '235') {
        throw new Exception("Password authentication failed: $response");
    }
    
    // MAIL FROM
    fputs($socket, "MAIL FROM: <$fromEmail>\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        throw new Exception("MAIL FROM failed: $response");
    }
    
    // RCPT TO
    fputs($socket, "RCPT TO: <$to>\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        throw new Exception("RCPT TO failed: $response");
    }
    
    // DATA
    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '354') {
        throw new Exception("DATA command failed: $response");
    }
    
    // Email headers and body
    $email = "From: $fromName <$fromEmail>\r\n";
    $email .= "To: <$to>\r\n";
    $email .= "Subject: $subject\r\n";
    $email .= "MIME-Version: 1.0\r\n";
    $email .= "Content-Type: text/html; charset=UTF-8\r\n";
    $email .= "\r\n";
    $email .= $html . "\r\n";
    $email .= ".\r\n";
    
    fputs($socket, $email);
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        throw new Exception("Email sending failed: $response");
    }
    
    // QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    return true;
}

function handleSaveConfig() {
    try {
        // Validate required fields
        $requiredFields = ['fromEmail', 'fromName', 'adminEmail'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("$field is required");
            }
        }
        
        // Validate email addresses
        $emailFields = ['fromEmail', 'adminEmail'];
        if (!empty($_POST['bccEmail'])) {
            $emailFields[] = 'bccEmail';
        }
        
        foreach ($emailFields as $field) {
            if (!filter_var($_POST[$field], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address for $field");
            }
        }
        
        // Create new configuration content
        $configContent = generateConfigContent();
        
        // Write to file
        $configFile = __DIR__ . '/email_config.php';
        $backupFile = __DIR__ . '/email_config_backup_' . date('Y-m-d_H-i-s') . '.php';
        
        // Create backup of existing file
        if (file_exists($configFile)) {
            copy($configFile, $backupFile);
        }
        
        // Write new configuration
        if (file_put_contents($configFile, $configContent) === false) {
            throw new Exception('Failed to write configuration file');
        }
        
        echo json_encode(['success' => true, 'message' => 'Email configuration saved successfully!']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function createTestEmailHtml($fromEmail, $fromName, $smtpEnabled) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Test Email - WhimsicalFrog</title>
    </head>
    <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background-color: #87ac3a; color: white; padding: 20px; text-align: center; border-radius: 8px;'>
            <h1 style='margin: 0;'>WhimsicalFrog</h1>
            <p style='margin: 10px 0 0 0;'>Email Configuration Test</p>
        </div>
        
        <div style='background-color: #f9f9f9; padding: 20px; margin-top: 20px; border-radius: 8px;'>
            <h2 style='color: #87ac3a; margin-top: 0;'>Configuration Test Successful! ✅</h2>
            
            <p>If you're reading this email, your email configuration is working correctly.</p>
            
            <div style='background-color: white; padding: 15px; border-radius: 6px; margin: 15px 0;'>
                <h3 style='color: #333; margin-top: 0;'>Configuration Details:</h3>
                <ul style='color: #666; line-height: 1.6;'>
                    <li><strong>From Email:</strong> " . htmlspecialchars($fromEmail) . "</li>
                    <li><strong>From Name:</strong> " . htmlspecialchars($fromName) . "</li>
                    <li><strong>SMTP Enabled:</strong> " . ($smtpEnabled ? 'Yes' : 'No') . "</li>
                </ul>
            </div>
            
            <p style='color: #666; font-size: 14px; margin-top: 20px;'>
                This test email was sent on " . date('F j, Y \a\t g:i A T') . "
            </p>
        </div>
    </body>
    </html>
    ";
}

function generateConfigContent() {
    $smtpEnabled = isset($_POST['smtpEnabled']) ? 'true' : 'false';
    $fromEmail = addslashes($_POST['fromEmail']);
    $fromName = addslashes($_POST['fromName']);
    $adminEmail = addslashes($_POST['adminEmail']);
    $bccEmail = addslashes($_POST['bccEmail'] ?? '');
    $smtpHost = addslashes($_POST['smtpHost'] ?? 'smtp.ionos.com');
    $smtpPort = (int)($_POST['smtpPort'] ?? 587);
    $smtpUsername = addslashes($_POST['smtpUsername'] ?? '');
    $smtpPassword = addslashes($_POST['smtpPassword'] ?? '');
    $smtpEncryption = addslashes($_POST['smtpEncryption'] ?? 'tls');
    
    $configFile = __DIR__ . '/email_config.php';
    
    // Read existing content
    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        
        // Update the define statements
        $patterns = [
            "/define\('SMTP_ENABLED',\s*[^)]+\);/" => "define('SMTP_ENABLED', $smtpEnabled);",
            "/define\('FROM_EMAIL',\s*[^)]+\);/" => "define('FROM_EMAIL', '$fromEmail');",
            "/define\('FROM_NAME',\s*[^)]+\);/" => "define('FROM_NAME', '$fromName');",
            "/define\('ADMIN_EMAIL',\s*[^)]+\);/" => "define('ADMIN_EMAIL', '$adminEmail');",
            "/define\('BCC_EMAIL',\s*[^)]+\);/" => "define('BCC_EMAIL', '$bccEmail');",
            "/define\('SMTP_HOST',\s*[^)]+\);/" => "define('SMTP_HOST', '$smtpHost');",
            "/define\('SMTP_PORT',\s*[^)]+\);/" => "define('SMTP_PORT', $smtpPort);",
            "/define\('SMTP_USERNAME',\s*[^)]+\);/" => "define('SMTP_USERNAME', '$smtpUsername');",
            "/define\('SMTP_PASSWORD',\s*[^)]+\);/" => "define('SMTP_PASSWORD', '$smtpPassword');",
            "/define\('SMTP_ENCRYPTION',\s*[^)]+\);/" => "define('SMTP_ENCRYPTION', '$smtpEncryption');"
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        return $content;
    }
    
    // If file doesn't exist, create a basic one
    return "<?php
// Email Configuration for WhimsicalFrog
// This file handles email sending functionality for order confirmations and notifications

// Email settings
define('SMTP_ENABLED', $smtpEnabled);
define('FROM_EMAIL', '$fromEmail');
define('FROM_NAME', '$fromName');
define('ADMIN_EMAIL', '$adminEmail');
define('BCC_EMAIL', '$bccEmail');

// SMTP Settings (if SMTP_ENABLED is true)
define('SMTP_HOST', '$smtpHost');
define('SMTP_PORT', $smtpPort);
define('SMTP_USERNAME', '$smtpUsername');
define('SMTP_PASSWORD', '$smtpPassword');
define('SMTP_ENCRYPTION', '$smtpEncryption');

/**
 * Send email using PHP mail() function
 */
function sendEmail(\$to, \$subject, \$htmlBody, \$plainTextBody = '') {
    \$headers = [
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion(),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    if (defined('BCC_EMAIL') && BCC_EMAIL) {
        \$headers[] = 'Bcc: ' . BCC_EMAIL;
    }
    
    \$headerString = implode(\"\\r\\n\", \$headers);
    return mail(\$to, \$subject, \$htmlBody, \$headerString);
}
?>";
}
?> 