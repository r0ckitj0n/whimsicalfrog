<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/secret_store.php';
require_once __DIR__ . '/business_settings_helper.php';

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
    $businessName = BusinessSettings::getBusinessName();
    $subject = 'Receipt for Order #' . $orderData['orderId'] . ' - ' . $businessName;

    // Configure EmailHelper using constants with secret store fallbacks
    $secUser = secret_get('smtp_username');
    $secPass = secret_get('smtp_password');
    EmailHelper::configure([
        'smtp_enabled'    => defined('SMTP_ENABLED') ? (bool)SMTP_ENABLED : false,
        'smtp_host'       => defined('SMTP_HOST') ? SMTP_HOST : '',
        'smtp_port'       => defined('SMTP_PORT') ? (int)SMTP_PORT : 587,
        'smtp_username'   => (!empty($secUser)) ? $secUser : (defined('SMTP_USERNAME') ? SMTP_USERNAME : ''),
        'smtp_password'   => (!empty($secPass)) ? $secPass : (defined('SMTP_PASSWORD') ? SMTP_PASSWORD : ''),
        'smtp_encryption' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls',
        'from_email'      => defined('FROM_EMAIL') ? FROM_EMAIL : '',
        'from_name'       => defined('FROM_NAME') ? FROM_NAME : 'WhimsicalFrog',
        'reply_to'        => defined('FROM_EMAIL') ? FROM_EMAIL : '',
    ]);

    $options = [
        'is_html' => true,
        'from_email' => defined('FROM_EMAIL') ? FROM_EMAIL : '',
        'from_name' => defined('FROM_NAME') ? FROM_NAME : 'WhimsicalFrog',
        'reply_to' => defined('FROM_EMAIL') ? FROM_EMAIL : '',
    ];
    if (defined('BCC_EMAIL') && BCC_EMAIL) {
        $options['bcc'] = [BCC_EMAIL];
    }

    $success = false;
    $errorMessage = '';
    try {
        $success = EmailHelper::send($input['customerEmail'], $subject, $receiptHTML, $options);
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
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

function generateReceiptEmailContent($orderData)
{
    $timestamp = date('F j, Y \a\t g:i A T', strtotime($orderData['timestamp']));
    // Business info for template
    $businessName   = BusinessSettings::getBusinessName();
    $businessDomain = BusinessSettings::getBusinessDomain();
    $businessUrl    = BusinessSettings::getSiteUrl('');

    $itemsHTML = '';
    foreach ($orderData['items'] as $item) {
        $itemTotal = $item['price'] * $item['quantity'];
        $itemsHTML .= '
            <tr class="email-table-row">
                <td class="email-table-cell">
                    <div class="email-item-name">' . htmlspecialchars($item['name']) . '</div>
                    <div class="email-item-sku">SKU: ' . htmlspecialchars($item['sku']) . '</div>
                    <div class="email-item-quantity">' . $item['quantity'] . ' × $' . number_format($item['price'], 2) . '</div>
                </td>
                <td class="email-table-cell-right">
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
    <body class="email-body">
        <div class="email-container">
            
            <!- Header ->
            <div class="email-header">
                <h1 class="email-header-title">' . htmlspecialchars($businessName) . '</h1>
                <p class="email-header-subtitle">Receipt for Your Purchase</p>
            </div>
            
            <!- Order Info ->
            <div class="email-order-info">
                                  <div class="email-info-row">
                      <span class="email-info-label">Order ID:</span>
                      <span class="email-info-value-mono">' . htmlspecialchars($orderData['orderId']) . '</span>
                  </div>
                  <div class="email-info-row">
                      <span class="email-info-label">Date:</span>
                      <span class="email-info-value">' . $timestamp . '</span>
                  </div>
                <div class="email-info-row">
                    <span class="email-info-label">Payment Method:</span>
                    <span class="email-info-value">' . htmlspecialchars($orderData['paymentMethod'] ?? 'Not specified') . '</span>
                </div>
            </div>
            
            <!- Items ->
            <div class="email-content">
                <h2 class="email-content-title">Items Purchased</h2>
                <table class="email-table">
                    ' . $itemsHTML . '
                </table>
            </div>
            
            <!- Totals ->
            <div class="email-totals">
                <div class="email-totals-row">
                    <span class="email-totals-label">Subtotal:</span>
                    <span class="email-totals-value">$' . number_format($orderData['subtotal'], 2) . '</span>
                </div>
                <div class="email-totals-row">
                    <span class="email-totals-label">Sales Tax (' . number_format(($orderData['taxRate'] ?? 0) * 100, 2) . '%):</span>
                    <span class="email-totals-value">$' . number_format($orderData['taxAmount'] ?? 0, 2) . '</span>
                </div>
                <div class="email-totals-final">
                    <span class="email-totals-final-label">TOTAL:</span>
                    <span class="email-totals-final-value">$' . number_format($orderData['total'], 2) . '</span>
                </div>
            </div>
            
            ' . (($orderData['paymentMethod'] ?? '') === 'Cash' ? '
            <!- Cash Payment Details ->
            <div class="email-payment-details">
                <h3 class="email-payment-title">Payment Details</h3>
                <div class="email-payment-row">
                    <span class="email-payment-label">Cash Received:</span>
                    <span class="email-payment-value">$' . number_format($orderData['cashReceived'] ?? 0, 2) . '</span>
                </div>
                <div class="email-payment-row">
                    <span class="email-payment-label">Change Given:</span>
                    <span class="email-payment-value">$' . number_format($orderData['changeAmount'] ?? 0, 2) . '</span>
                </div>
            </div>
            ' : '') . '
            
            <!- Footer ->
            <div class="email-footer">
                <p class="email-footer-title">Thank you for your business!</p>
                <p class="email-footer-text">Visit us online at <a href="' . htmlspecialchars($businessUrl) . '" target="_blank" rel="noopener">' . htmlspecialchars($businessDomain ?: $businessUrl) . '</a></p>
            </div>
            
        </div>
        
        <!- Footer text ->
        <div class="email-footer-disclaimer">
            <p>This email was sent from the ' . htmlspecialchars($businessName) . ' Point of Sale system.</p>
        </div>
    </body>
    </html>';
}

// Legacy custom SMTP sender removed; unified on EmailHelper::send()
?> 