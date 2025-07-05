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
            <tr class="receipt-item-row">
                <td class="receipt-item-cell">
                    <div class="receipt-item-name">' . htmlspecialchars($item['name']) . '</div>
                    <div class="receipt-item-meta">SKU: ' . htmlspecialchars($item['sku']) . '</div>
                    <div class="receipt-item-meta">' . $item['quantity'] . ' × $' . number_format($item['price'], 2) . '</div>
                </td>
                <td class="receipt-item-cell receipt-item-price">
                    $' . number_format($item['quantity'] * $item['price'], 2) . '
                </td>
            </tr>';
    }
    
    $paymentSection = '';
    if (isset($orderData['cashReceived']) && $orderData['cashReceived'] > 0) {
        $paymentSection = '
            <div class="receipt-payment-section">
                <h3 class="receipt-payment-title">Payment Details</h3>
                <div class="receipt-order-row">
                    <span class="receipt-order-label">Cash Received:</span>
                    <span class="receipt-order-value">$' . number_format($orderData['cashReceived'] ?? 0, 2) . '</span>
                </div>
                <div class="receipt-order-row">
                    <span class="receipt-order-label">Change Given:</span>
                    <span class="receipt-order-value">$' . number_format($orderData['changeAmount'] ?? 0, 2) . '</span>
                </div>
            </div>';
    }

    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Receipt - Order #' . htmlspecialchars($orderData['orderId']) . '</title>
    </head>
    <body class="receipt-email-body">
        <div class="receipt-email-container">
            
            <div class="receipt-email-header">
                <h1 class="receipt-email-title">WHIMSICALFROG</h1>
                <p class="receipt-email-subtitle">Receipt for Your Purchase</p>
            </div>
            
            <div class="receipt-order-info">
                <div class="receipt-order-row">
                    <span class="receipt-order-label">Order ID:</span>
                    <span class="receipt-order-value receipt-order-id">' . htmlspecialchars($orderData['orderId']) . '</span>
                </div>
                <div class="receipt-order-row">
                    <span class="receipt-order-label">Date:</span>
                    <span class="receipt-order-value">' . $timestamp . '</span>
                </div>
                <div class="receipt-order-row">
                    <span class="receipt-order-label">Payment Method:</span>
                    <span class="receipt-order-value">' . htmlspecialchars($orderData['paymentMethod'] ?? 'Not specified') . '</span>
                </div>
            </div>
            
            <div class="receipt-items-section">
                <h2 class="receipt-items-title">Items Purchased</h2>
                <table class="receipt-items-table">
                    ' . $itemsHTML . '
                </table>
            </div>
            
            <div class="receipt-totals-section">
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Subtotal:</span>
                    <span class="receipt-total-value">$' . number_format($orderData['subtotal'], 2) . '</span>
                </div>
                <div class="receipt-total-row">
                    <span class="receipt-total-label">Sales Tax (' . number_format(($orderData['taxRate'] ?? 0) * 100, 2) . '%):</span>
                    <span class="receipt-total-value">$' . number_format($orderData['taxAmount'] ?? 0, 2) . '</span>
                </div>
                <div class="receipt-grand-total">
                    <span class="receipt-grand-total-label">TOTAL:</span>
                    <span class="receipt-grand-total-value">$' . number_format($orderData['total'], 2) . '</span>
                </div>
            </div>
            
            ' . $paymentSection . '
            
            <div class="receipt-footer">
                <p class="receipt-footer-title">Thank you for your business!</p>
                <p class="receipt-footer-text">Visit us online at WhimsicalFrog.com</p>
            </div>
            
        </div>
        
        <div class="receipt-disclaimer">
            <p>This is an automated receipt. Please keep for your records.</p>
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