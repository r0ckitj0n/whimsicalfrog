<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/email_config.php';

// Enable CORS and JSON response
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

try {
    // Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    // Validate required fields
    if (empty($input['orderId'])) {
        throw new Exception('Order ID is required');
    }

    if (empty($input['customerEmail'])) {
        throw new Exception('Customer email is required');
    }

    if (!filter_var($input['customerEmail'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid customer email address');
    }

    // Get order data (from the POS system data passed in)
    $orderData = $input['orderData'] ?? null;
    if (!$orderData) {
        throw new Exception('Order data is required');
    }

    // Generate receipt HTML content
    $receiptHTML = generateReceiptEmailContent($orderData);

    // Send email using existing email configuration
    $subject = 'Receipt for Order #' . $orderData['orderId'] . ' - WhimsicalFrog';
    
    // Use the existing email configuration
    $headers = [
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion(),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    // Add BCC if configured
    if (defined('BCC_EMAIL') && BCC_EMAIL) {
        $headers[] = 'Bcc: ' . BCC_EMAIL;
    }
    
    $headerString = implode("\r\n", $headers);
    
    $success = false;
    $errorMessage = '';
    
    if (SMTP_ENABLED) {
        // Use SMTP if enabled
        try {
            $success = sendEmailSMTP($input['customerEmail'], $subject, $receiptHTML);
        } catch (Exception $e) {
            $errorMessage = 'SMTP failed: ' . $e->getMessage();
            // Fall back to PHP mail()
            $success = mail($input['customerEmail'], $subject, $receiptHTML, $headerString);
            if (!$success) {
                $errorMessage .= ' (PHP mail() also failed)';
            }
        }
    } else {
        // Use PHP mail()
        $success = mail($input['customerEmail'], $subject, $receiptHTML, $headerString);
        if (!$success) {
            $errorMessage = 'PHP mail() function failed';
        }
    }

    if ($success) {
        // Log the email
        try {
            require_once 'email_logger.php';
            logEmail(
                $input['customerEmail'],
                FROM_EMAIL,
                $subject,
                $receiptHTML,
                'order_confirmation',
                'sent',
                null,
                $orderData['orderId'],
                'POS System'
            );
        } catch (Exception $e) {
            // Don't fail if logging fails
            error_log('Receipt email logging failed: ' . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'message' => 'Receipt email sent successfully to ' . $input['customerEmail']
        ]);
    } else {
        throw new Exception($errorMessage ?: 'Failed to send email');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function generateReceiptEmailContent($orderData) {
    $timestamp = date('F j, Y \a\t g:i A T', strtotime($orderData['timestamp']));
    
    $itemsHTML = '';
    foreach ($orderData['items'] as $item) {
        $itemTotal = $item['price'] * $item['quantity'];
        $itemsHTML .= '
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 12px 0; vertical-align: top;">
                    <div style="font-weight: 600; color: #374151; margin-bottom: 4px;">' . htmlspecialchars($item['name']) . '</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">SKU: ' . htmlspecialchars($item['sku']) . '</div>
                    <div style="font-size: 0.875rem; color: #6b7280;">' . $item['quantity'] . ' × $' . number_format($item['price'], 2) . '</div>
                </td>
                <td style="padding: 12px 0; text-align: right; vertical-align: top; font-weight: 600; color: #374151;">
                    $' . number_format($itemTotal, 2) . '
                </td>
            </tr>';
    }
    
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Receipt - Order #' . htmlspecialchars($orderData['orderId']) . '</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f9fafb;">
        <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
            
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #87ac3a 0%, #6b8e23 100%); color: white; padding: 32px 24px; text-align: center;">
                <h1 style="margin: 0; font-size: 2rem; font-weight: 700; letter-spacing: -0.025em;">WHIMSICALFROG</h1>
                <p style="margin: 8px 0 0 0; font-size: 1.125rem; opacity: 0.9;">Receipt for Your Purchase</p>
            </div>
            
            <!-- Order Info -->
            <div style="padding: 24px; border-bottom: 1px solid #e5e7eb; background-color: #f9fafb;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                    <span style="font-weight: 600; color: #374151;">Order ID:</span>
                    <span style="color: #6b7280; font-family: \'Courier New\', monospace;">' . htmlspecialchars($orderData['orderId']) . '</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                    <span style="font-weight: 600; color: #374151;">Date:</span>
                    <span style="color: #6b7280;">' . $timestamp . '</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="font-weight: 600; color: #374151;">Payment Method:</span>
                    <span style="color: #6b7280;">' . htmlspecialchars($orderData['paymentMethod'] ?? 'Not specified') . '</span>
                </div>
            </div>
            
            <!-- Items -->
            <div style="padding: 24px;">
                <h2 style="margin: 0 0 20px 0; font-size: 1.25rem; font-weight: 600; color: #374151;">Items Purchased</h2>
                <table style="width: 100%; border-collapse: collapse;">
                    ' . $itemsHTML . '
                </table>
            </div>
            
            <!-- Totals -->
            <div style="padding: 24px; background-color: #f9fafb; border-top: 1px solid #e5e7eb;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                    <span style="color: #6b7280;">Subtotal:</span>
                    <span style="color: #374151; font-weight: 500;">$' . number_format($orderData['subtotal'], 2) . '</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 16px;">
                    <span style="color: #6b7280;">Sales Tax (' . number_format(($orderData['taxRate'] ?? 0) * 100, 2) . '%):</span>
                    <span style="color: #374151; font-weight: 500;">$' . number_format($orderData['taxAmount'] ?? 0, 2) . '</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding-top: 16px; border-top: 2px solid #87ac3a;">
                    <span style="font-size: 1.125rem; font-weight: 700; color: #374151;">TOTAL:</span>
                    <span style="font-size: 1.125rem; font-weight: 700; color: #87ac3a;">$' . number_format($orderData['total'], 2) . '</span>
                </div>
            </div>
            
            ' . (($orderData['paymentMethod'] ?? '') === 'Cash' ? '
            <!-- Cash Payment Details -->
            <div style="padding: 24px; border-top: 1px solid #e5e7eb;">
                <h3 style="margin: 0 0 16px 0; font-size: 1rem; font-weight: 600; color: #374151;">Payment Details</h3>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #6b7280;">Cash Received:</span>
                    <span style="color: #374151; font-weight: 500;">$' . number_format($orderData['cashReceived'] ?? 0, 2) . '</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #6b7280;">Change Given:</span>
                    <span style="color: #374151; font-weight: 500;">$' . number_format($orderData['changeAmount'] ?? 0, 2) . '</span>
                </div>
            </div>
            ' : '') . '
            
            <!-- Footer -->
            <div style="padding: 24px; text-align: center; background-color: #87ac3a; color: white;">
                <p style="margin: 0 0 8px 0; font-size: 1.125rem; font-weight: 600;">Thank you for your business!</p>
                <p style="margin: 0; opacity: 0.9;">Visit us online at WhimsicalFrog.com</p>
            </div>
            
        </div>
        
        <!-- Footer text -->
        <div style="text-align: center; padding: 20px; color: #6b7280; font-size: 0.875rem;">
            <p>This email was sent from the WhimsicalFrog Point of Sale system.</p>
        </div>
    </body>
    </html>';
}

function sendEmailSMTP($to, $subject, $htmlBody) {
    // Use the same SMTP implementation as the save_email_config.php
    $host = SMTP_HOST;
    $port = SMTP_PORT;
    $username = SMTP_USERNAME;
    $password = SMTP_PASSWORD;
    $encryption = SMTP_ENCRYPTION;
    $fromEmail = FROM_EMAIL;
    $fromName = FROM_NAME;
    
    // Connect to SMTP server
    $socket = stream_socket_client(
        $host . ':' . $port,
        $errno, $errstr, 30, STREAM_CLIENT_CONNECT
    );
    
    if (!$socket) {
        throw new Exception("Failed to connect to SMTP server $host:$port - $errstr ($errno)");
    }
    
    // Function to read SMTP response
    $readResponse = function() use ($socket) {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] == ' ') break;
        }
        return trim($response);
    };
    
    // Initial server greeting
    $response = $readResponse();
    if (substr($response, 0, 3) != '220') {
        fclose($socket);
        throw new Exception("SMTP server not ready: $response");
    }
    
    // EHLO
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
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            throw new Exception("STARTTLS failed: $response");
        }
        
        // Enable TLS encryption
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            throw new Exception("Failed to enable TLS encryption");
        }
        
        // Send EHLO again after TLS
        fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
        $response = $readResponse();
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            throw new Exception("EHLO after TLS failed: $response");
        }
    }
    
    // Authenticate
    if ($username && $password) {
        fputs($socket, "AUTH LOGIN\r\n");
        $response = $readResponse();
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            throw new Exception("AUTH LOGIN failed: $response");
        }
        
        fputs($socket, base64_encode($username) . "\r\n");
        $response = $readResponse();
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            throw new Exception("Username authentication failed: $response");
        }
        
        fputs($socket, base64_encode($password) . "\r\n");
        $response = $readResponse();
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            throw new Exception("Password authentication failed: $response");
        }
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
    $email .= $htmlBody . "\r\n";
    $email .= ".\r\n";
    
    fputs($socket, $email);
    $response = $readResponse();
    if (substr($response, 0, 3) != '250') {
        fclose($socket);
        throw new Exception("Email delivery failed: $response");
    }
    
    // QUIT
    fputs($socket, "QUIT\r\n");
    $readResponse();
    fclose($socket);
    
    return true;
}
?> 