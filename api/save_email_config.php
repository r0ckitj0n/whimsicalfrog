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

function handleTestEmail()
{
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
            // Always use our custom SMTP implementation for better error handling
            $success = sendSmtpEmail($testEmail, $subject, $html, $fromEmail, $fromName, $_POST);
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

function sendSmtpEmail($to, $subject, $html, $fromEmail, $fromName, $config)
{
    $host = $config['smtpHost'] ?? 'smtp.ionos.com';
    $port = intval($config['smtpPort'] ?? 587);
    $username = $config['smtpUsername'] ?? $fromEmail;
    $password = $config['smtpPassword'] ?? '';
    $encryption = $config['smtpEncryption'] ?? 'tls';

    // For IONOS, we need to handle TLS differently
    // Connect without encryption first, then upgrade to TLS
    $socket = stream_socket_client(
        $host . ':' . $port,
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new Exception("Failed to connect to SMTP server $host:$port - $errstr ($errno)");
    }

    // Function to read SMTP response
    $readResponse = function () use ($socket) {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] == ' ') {
                break;
            } // End of multi-line response
        }
        return trim($response);
    };

    // Initial server greeting
    $response = $readResponse();
    if (substr($response, 0, 3) != '220') {
        fclose($socket);
        throw new Exception("SMTP server not ready: $response");
    }

    // EHLO to identify client
    fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
    $response = $readResponse();
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        throw new Exception("EHLO failed: $response");
    }

    // Start TLS if requested
    if ($encryption === 'tls') {
        fputs($socket, "STARTTLS\r\n");
        $response = $readResponse();
        $responseCode = substr($response, 0, 3);

        if ($responseCode != '220') {
            fclose($socket);
            throw new Exception("STARTTLS not supported or failed: $response");
        }

        // Create TLS context
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT
            ]
        ]);

        // Enable TLS encryption
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            throw new Exception("Failed to enable TLS encryption");
        }

        // EHLO again after TLS
        fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        $response = $readResponse();
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new Exception("EHLO after TLS failed: $response");
        }
    }

    // Authentication
    fputs($socket, "AUTH LOGIN\r\n");
    $response = $readResponse();
    if (substr($response, 0, 3) != '334') {
        fclose($socket);
        throw new Exception("AUTH LOGIN not supported: $response");
    }

    // Send username
    fputs($socket, base64_encode($username) . "\r\n");
    $response = $readResponse();
    if (substr($response, 0, 3) != '334') {
        fclose($socket);
        throw new Exception("Username rejected: $response");
    }

    // Send password
    fputs($socket, base64_encode($password) . "\r\n");
    $response = $readResponse();
    if (substr($response, 0, 3) != '235') {
        fclose($socket);
        throw new Exception("Authentication failed - check username/password: $response");
    }

    // MAIL FROM
    fputs($socket, "MAIL FROM: <$fromEmail>\r\n");
    $response = $readResponse();
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        throw new Exception("MAIL FROM rejected: $response");
    }

    // RCPT TO
    fputs($socket, "RCPT TO: <$to>\r\n");
    $response = $readResponse();
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        throw new Exception("RCPT TO rejected: $response");
    }

    // DATA
    fputs($socket, "DATA\r\n");
    $response = $readResponse();
    if (substr($response, 0, 3) != '354') {
        fclose($socket);
        throw new Exception("DATA command rejected: $response");
    }

    // Send email headers and body
    $email = "From: $fromName <$fromEmail>\r\n";
    $email .= "To: <$to>\r\n";
    $email .= "Subject: $subject\r\n";
    $email .= "MIME-Version: 1.0\r\n";
    $email .= "Content-Type: text/html; charset=UTF-8\r\n";
    $email .= "\r\n";
    $email .= $html . "\r\n";
    $email .= ".\r\n"; // End data marker

    fputs($socket, $email);
    $response = $readResponse();
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        throw new Exception("Email delivery failed: $response");
    }

    // Close connection gracefully
    fputs($socket, "QUIT\r\n");
    $readResponse(); // Read the response but don't check it
    fclose($socket);

    return true;
}

function handleSaveConfig()
{
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

function createTestEmailHtml($fromEmail, $fromName, $smtpEnabled)
{
    require_once __DIR__ . '/../includes/business_settings_helper.php';
    $brandPrimary = BusinessSettings::getPrimaryColor();
    $brandSecondary = BusinessSettings::getSecondaryColor();
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Test Email - WhimsicalFrog</title>
        <link rel='stylesheet' href='https://whimsicalfrog.us/css/email-styles.css'>
    </head>
    <body class='email-body' style='-brand-primary: {$brandPrimary}; -brand-secondary: {$brandSecondary};'>
        <div class='email-header'>
            <h1 class='m-0'>WhimsicalFrog</h1>
            <p class='u-margin-top-10px'>Email Configuration Test</p>
        </div>
        
        <div class='email-wrapper'>
            <h2 class='u-color-87ac3a m-0'>Configuration Test Successful! ✅</h2>
            
            <p>If you're reading this email, your email configuration is working correctly.</p>
            
            <div class='email-section'>
                <h3 class='u-color-333 m-0'>Configuration Details:</h3>
                <ul class='u-color-666 u-line-height-1-6'>
                    <li><strong>From Email:</strong> " . htmlspecialchars($fromEmail) . "</li>
                    <li><strong>From Name:</strong> " . htmlspecialchars($fromName) . "</li>
                    <li><strong>SMTP Enabled:</strong> " . ($smtpEnabled ? 'Yes' : 'No') . "</li>
                </ul>
            </div>
            
            <p class='u-color-666 u-font-size-14px u-margin-top-20px'>
                This test email was sent on " . date('F j, Y \a\t g:i A T') . "
            </p>
        </div>
    </body>
    </html>
    ";
}

function generateConfigContent()
{
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