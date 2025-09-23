<?php

// Email Configuration for WhimsicalFrog
// This file handles email sending functionality for order confirmations and notifications

// Include business settings helper for brand colors and email config
require_once __DIR__ . '/business_settings_helper.php';
// Use centralized email helper so SMTP and headers are consistent everywhere
require_once __DIR__ . '/../includes/email_helper.php';
// Ensure secret_get() is available for pulling SMTP creds securely
require_once __DIR__ . '/../includes/secret_store.php';
// If Composer autoloader exists, include it so PHPMailer and other vendor libs are available
$__wf_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($__wf_autoload)) {
    require_once $__wf_autoload;
}

// Load dynamic email settings from DB ('email' category) and enforce required presence
$__wf_email_cfg = BusinessSettings::getByCategory('email');
if (!is_array($__wf_email_cfg)) {
    throw new RuntimeException('Email configuration missing: category "email" not found in BusinessSettings');
}

// Required fields (no masking fallbacks): derive from canonical business info when possible
$__wf_FROM_EMAIL = (string)(BusinessSettings::getBusinessEmail());
$__wf_FROM_NAME  = (string)(BusinessSettings::getBusinessName());
$__wf_ADMIN_EMAIL = (string)(BusinessSettings::getBusinessEmail());
$__wf_BCC_EMAIL  = (string)($__wf_email_cfg['bcc_email'] ?? ''); // optional
$__wf_SMTP_HOST  = (string)($__wf_email_cfg['smtp_host'] ?? '');
$__wf_SMTP_PORT  = $__wf_email_cfg['smtp_port'] ?? null;
$__wf_SMTP_ENC   = (string)($__wf_email_cfg['smtp_encryption'] ?? '');
$__wf_SMTP_ENABLED_VAL = $__wf_email_cfg['smtp_enabled'] ?? null;
// New optional fields
$__wf_REPLY_TO_RAW = isset($__wf_email_cfg['reply_to']) ? (string)$__wf_email_cfg['reply_to'] : '';
$__wf_REPLY_TO   = trim($__wf_REPLY_TO_RAW) !== '' ? (string) $__wf_REPLY_TO_RAW : (string) $__wf_FROM_EMAIL;
$__wf_TEST_RECIPIENT = (string)($__wf_email_cfg['test_recipient'] ?? $__wf_ADMIN_EMAIL);
$__wf_SMTP_AUTH_VAL  = $__wf_email_cfg['smtp_auth'] ?? true;
$__wf_SMTP_TIMEOUT_VAL = $__wf_email_cfg['smtp_timeout'] ?? null;
$__wf_SMTP_DEBUG_VAL = $__wf_email_cfg['smtp_debug'] ?? null;

// Validate presence
if ($__wf_FROM_EMAIL === '' || $__wf_FROM_NAME === '' || $__wf_ADMIN_EMAIL === '') {
    throw new RuntimeException('Email configuration missing: from_email, from_name, and admin_email are required');
}
if ($__wf_SMTP_HOST === '' || $__wf_SMTP_PORT === null || $__wf_SMTP_ENC === '' || $__wf_SMTP_ENABLED_VAL === null) {
    throw new RuntimeException('Email configuration missing: smtp_host, smtp_port, smtp_encryption, and smtp_enabled are required');
}

// Normalize types
$__wf_SMTP_PORT = is_numeric($__wf_SMTP_PORT) ? (int)$__wf_SMTP_PORT : (int) $__wf_SMTP_PORT; // will fail if non-numeric
$__wf_smtpEnabled = is_bool($__wf_SMTP_ENABLED_VAL)
    ? $__wf_SMTP_ENABLED_VAL
    : in_array(strtolower((string)$__wf_SMTP_ENABLED_VAL), ['true','1','yes'], true);

// Normalize optional SMTP controls
$__wf_smtpAuth = is_bool($__wf_SMTP_AUTH_VAL)
    ? $__wf_SMTP_AUTH_VAL
    : in_array(strtolower((string)$__wf_SMTP_AUTH_VAL), ['true','1','yes'], true);
$__wf_smtpTimeout = null;
if ($__wf_SMTP_TIMEOUT_VAL !== null && $__wf_SMTP_TIMEOUT_VAL !== '') {
    $__wf_smtpTimeout = is_numeric($__wf_SMTP_TIMEOUT_VAL) ? (int)$__wf_SMTP_TIMEOUT_VAL : null;
}
if ($__wf_smtpTimeout === null) {
    $__wf_smtpTimeout = 30;
}
$__wf_smtpDebug = 0;
if ($__wf_SMTP_DEBUG_VAL !== null && $__wf_SMTP_DEBUG_VAL !== '') {
    if (is_numeric($__wf_SMTP_DEBUG_VAL)) {
        $__wf_smtpDebug = (int)$__wf_SMTP_DEBUG_VAL;
    } elseif (in_array(strtolower((string)$__wf_SMTP_DEBUG_VAL), ['true','1','yes'], true)) {
        $__wf_smtpDebug = 1;
    }
}

// Credentials: prefer secret store; require username/password if SMTP is enabled
$__wf_secret_user = function_exists('secret_get') ? secret_get('smtp_username') : null;
$__wf_secret_pass = function_exists('secret_get') ? secret_get('smtp_password') : null;
$__wf_SMTP_USER   = (string)($__wf_email_cfg['smtp_username'] ?? '');
$__wf_effective_user = !empty($__wf_secret_user) ? (string)$__wf_secret_user : (string)$__wf_SMTP_USER;
$__wf_effective_pass = !empty($__wf_secret_pass) ? (string)$__wf_secret_pass : '';
if ($__wf_smtpEnabled) {
    if ($__wf_effective_user === '' || $__wf_effective_pass === '') {
        throw new RuntimeException('SMTP is enabled but smtp_username/password are not configured in secret store or settings');
    }
}

// Define constants strictly from config (no code fallbacks)
define('SMTP_ENABLED', (bool)$__wf_smtpEnabled);
define('FROM_EMAIL', $__wf_FROM_EMAIL);
define('FROM_NAME', $__wf_FROM_NAME);
define('ADMIN_EMAIL', $__wf_ADMIN_EMAIL);
define('BCC_EMAIL', $__wf_BCC_EMAIL);
define('SMTP_HOST', $__wf_SMTP_HOST);
define('SMTP_PORT', $__wf_SMTP_PORT);
define('SMTP_USERNAME', $__wf_effective_user);
// SMTP password is stored in the secret store; do not expose from DB beyond this runtime constant
define('SMTP_PASSWORD', $__wf_effective_pass);
define('SMTP_ENCRYPTION', $__wf_SMTP_ENC); // 'tls' or 'ssl'
define('SMTP_AUTH', (bool)$__wf_smtpAuth);
define('SMTP_TIMEOUT', (int)$__wf_smtpTimeout);
define('SMTP_DEBUG', (int)$__wf_smtpDebug);
define('REPLY_TO_EMAIL', $__wf_REPLY_TO);
define('TEST_RECIPIENT', $__wf_TEST_RECIPIENT);

/**
 * Build a URL by appending query parameters, preserving existing query and anchors
 */
function wf_url_with_params(string $base, array $params): string
{
    if (empty($params)) {
        return $base;
    }
    $parts = parse_url($base);
    $existing = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $existing);
    }
    $query = array_merge($existing, $params);
    $scheme   = $parts['scheme'] ?? null;
    $host     = $parts['host'] ?? null;
    $port     = isset($parts['port']) ? ":{$parts['port']}" : '';
    $user     = $parts['user'] ?? null;
    $pass     = isset($parts['pass']) ? ":{$parts['pass']}@" : ($user ? '@' : '');
    $auth     = $user ? $user . $pass : '';
    $path     = $parts['path'] ?? '';
    $frag     = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    $queryStr = http_build_query($query);
    if ($scheme && $host) {
        return sprintf('%s://%s%s%s%s?%s%s', $scheme, $auth, $host, $port, $path, $queryStr, $frag);
    }
    $delim = (strpos($base, '?') === false) ? '?' : '&';
    return $base . $delim . $queryStr;
}

/**
 * Resolve admin base URL from BusinessSettings, fallback to site base + /?page=admin
 */
function wf_get_admin_base(): string
{
    $cfg = (string) BusinessSettings::get('admin_base_url', '');
    if ($cfg !== '') {
        return $cfg;
    }
    $siteBase = (string) BusinessSettings::getSiteUrl('');
    if ($siteBase === '') {
        throw new RuntimeException('Site base URL missing for admin link composition');
    }
    return rtrim($siteBase, '/') . '/?page=admin';
}

/**
 * Send email routed through EmailHelper (honors SMTP settings and provides consistent headers)
 */
function sendEmail($to, $subject, $htmlBody, $plainTextBody = '')
{
    // Configure central helper from constants
    // Prefer secrets if present, fallback to constants
    $secUser = function_exists('secret_get') ? secret_get('smtp_username') : null;
    $secPass = function_exists('secret_get') ? secret_get('smtp_password') : null;

    EmailHelper::configure([
        'smtp_enabled'   => (bool)SMTP_ENABLED,
        'smtp_host'      => (string)SMTP_HOST,
        'smtp_port'      => (int)SMTP_PORT,
        'smtp_auth'      => (bool)SMTP_AUTH,
        'smtp_username'  => (!empty($secUser)) ? $secUser : (string)SMTP_USERNAME,
        'smtp_password'  => (!empty($secPass)) ? $secPass : (string)SMTP_PASSWORD,
        'smtp_encryption' => (string)SMTP_ENCRYPTION,
        'smtp_timeout'   => (int)SMTP_TIMEOUT,
        'smtp_debug'     => (int)SMTP_DEBUG,
        'from_email'     => (string)FROM_EMAIL,
        'from_name'      => (string)FROM_NAME,
        'reply_to'       => (string)REPLY_TO_EMAIL,
    ]);

    $options = [
        'is_html'   => true,
        'from_email' => (string)FROM_EMAIL,
        'from_name' => (string)FROM_NAME,
        'reply_to'  => (string)REPLY_TO_EMAIL,
    ];

    // Add BCC if configured
    if (defined('BCC_EMAIL') && BCC_EMAIL) {
        $options['bcc'] = [BCC_EMAIL];
    }

    return EmailHelper::send($to, $subject, $htmlBody, $options);
}

/**
 * Generate customer order confirmation email HTML
 */
function generateCustomerConfirmationEmail($orderData, $customerData, $orderItems)
{
    // Get brand colors from business settings
    $brandPrimary = BusinessSettings::getPrimaryColor();
    $brandSecondary = BusinessSettings::getSecondaryColor();

    $orderId = htmlspecialchars($orderData['id']);
    $customerName = htmlspecialchars(trim(($customerData['first_name'] ?? '') . ' ' . ($customerData['last_name'] ?? '')));
    if (empty(trim($customerName))) {
        $customerName = htmlspecialchars($customerData['username'] ?? 'Valued Customer');
    }

    $orderDate = date('F j, Y', strtotime($orderData['date'] ?? 'now'));
    $orderTotal = number_format((float)$orderData['total'], 2);
    $paymentMethod = htmlspecialchars($orderData['paymentMethod'] ?? 'Not specified');
    $shippingMethod = htmlspecialchars($orderData['shippingMethod'] ?? 'Not specified');
    $orderStatus = htmlspecialchars($orderData['order_status'] ?? 'Processing');
    $paymentStatus = htmlspecialchars($orderData['paymentStatus'] ?? 'Pending');

    // Build items list
    $itemsHtml = '';
    foreach ($orderItems as $item) {
        $itemName = htmlspecialchars($item['name'] ?? 'Item');
        $itemQuantity = (int)($item['quantity'] ?? 1);
        $itemPrice = number_format((float)($item['price'] ?? 0), 2);
        $itemTotal = number_format($itemQuantity * (float)($item['price'] ?? 0), 2);

        $itemsHtml .= "
            <tr>
                <td class='email-table-cell'>{$itemName}</td>
                <td class='email-table-cell email-table-cell-center'>{$itemQuantity}</td>
                <td class='email-table-cell email-table-cell-right'>\${$itemPrice}</td>
                <td class='email-table-cell email-table-cell-right'>\${$itemTotal}</td>
            </tr>";
    }

    // Shipping address
    $shippingAddress = '';
    if (!empty($orderData['shippingAddress'])) {
        $shippingAddress = '<strong>Shipping Address:</strong><br>' . nl2br(htmlspecialchars($orderData['shippingAddress']));
    } elseif (!empty($customerData['addressLine1'])) {
        $address = htmlspecialchars($customerData['addressLine1'] ?? '');
        if (!empty($customerData['addressLine2'])) {
            $address .= '<br>' . htmlspecialchars($customerData['addressLine2']);
        }
        if (!empty($customerData['city']) || !empty($customerData['state']) || !empty($customerData['zipCode'])) {
            $cityStateZip = trim(
                htmlspecialchars($customerData['city'] ?? '') . ', ' .
                htmlspecialchars($customerData['state'] ?? '') . ' ' .
                htmlspecialchars($customerData['zipCode'] ?? '')
            );
            $address .= '<br>' . ltrim($cityStateZip, ', ');
        }
        $shippingAddress = '<strong>Shipping Address:</strong><br>' . $address;
    }

    // Resolve site base URL from canonical setting (required)
    $siteBase = (string) BusinessSettings::getSiteUrl('');
    if ($siteBase === '') {
        throw new RuntimeException('Site base URL missing: BusinessSettings::getSiteUrl() returned empty');
    }
    // Precompute receipt URL using safe builder
    $receiptUrl = wf_url_with_params(rtrim($siteBase, '/') . '/', ['page' => 'receipt', 'orderId' => $orderId]);

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Order Confirmation - WhimsicalFrog</title>
        <style>
        /* Basic email styles (class-based, no inline attributes) */
        body.email-body { margin:0; padding:0; background:#ffffff; color:#333; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; line-height:1.5; }
        .email-wrapper { max-width:600px; margin:0 auto; padding:16px; }
        .email-header { background: {$brandPrimary}; color:#fff; padding:16px; text-align:center; }
        .email-title { margin:0; font-size:20px; }
        .email-subtitle { margin:8px 0 0; font-size:14px; color:#eef; }
        .email-section { margin:16px 0; }
        .email-section-heading { font-size:16px; margin:0 0 8px; color: {$brandSecondary}; }
        .email-summary-table, .email-order-table, .email-table { width:100%; border-collapse:collapse; }
        .email-summary-table td, .email-order-table td, .email-order-table th, .email-table td, .email-table th { padding:8px; border-bottom:1px solid #eee; text-align:left; }
        .email-table-cell-center { text-align:center; }
        .email-table-cell-right { text-align:right; }
        .email-table-header-cell { background:#f6f6f6; font-weight:bold; }
        .email-table-row-alt { background:#f9f9f9; }
        .email-cta-button { display:inline-block; background: {$brandPrimary}; color:#fff !important; text-decoration:none; padding:10px 14px; border-radius:4px; }
        .email-secondary-cta { display:inline-block; color: {$brandPrimary}; text-decoration:none; padding:10px 14px; }
        .email-footer { margin-top:24px; font-size:12px; color:#666; text-align:center; }
        .email-footer-primary { margin:0 0 4px; }
        .email-footer-secondary { margin:0; }
        .email-badge-warning { background:#fff3cd; color:#856404; padding:2px 6px; border-radius:4px; }
        .email-status-received { color:#2e7d32; font-weight:bold; }
        .email-status-pending { color:#b26a00; font-weight:bold; }
        /* Utilities */
        .m-0 { margin:0; }
        .u-margin-right-10px { margin-right:10px; }
        .u-padding-4px-0 { padding:4px 0; }
        .u-align-top { vertical-align:top; }
        .u-color-333 { color:#333; }
        .u-color-666 { color:#666; }
        .u-line-height-1-6 { line-height:1.6; }
        .u-font-weight-bold { font-weight:bold; }
        .u-font-size-14px { font-size:14px; }
        .u-margin-top-10px { margin-top:10px; }
        .u-margin-top-20px { margin-top:20px; }
        .email-admin-header, .email-header { background: {$brandPrimary}; color:#fff; }
        .email-admin-summary-label { text-align:right; padding:8px; font-weight:bold; }
        .email-admin-summary-value { text-align:right; padding:8px; }
        .email-admin-notice { background:#fff8e1; border:1px solid #ffe082; padding:10px; border-radius:4px; margin:12px 0; }
        .email-shipping-box { border:1px solid #eee; border-radius:4px; padding:12px; margin:12px 0; background:#fafafa; }
        .email-admin-grid { display:block; }
        @media screen and (min-width: 480px) {
          .email-admin-grid { display:flex; gap:16px; }
          .email-admin-grid .email-section { flex:1; }
        }
        blockquote { margin:12px 0; padding-left:12px; border-left:3px solid #eee; color:#555; }
        </style>
    </head>
    <body class='email-body'>
        <div class='email-header'>
            <h1 class='email-title'>WhimsicalFrog</h1>
            <p class='email-subtitle'>Order Confirmation</p>
        </div>
        
        <div class='email-wrapper'>
            <h2 class='email-section-heading'>Thank you for your order, {$customerName}!</h2>
            
            <p>We've received your order and we're getting it ready for you. Here are the details:</p>
            
            <div class='email-section'>
                <h3 class='email-section-heading'>Order Information</h3>
                <table class='email-summary-table'>
                    <tr>
                        <td class='email-summary-label'>Order Number:</td>
                        <td class='email-summary-value'>{$orderId}</td>
                    </tr>
                    <tr>
                        <td class='email-summary-label'>Order Date:</td>
                        <td class='email-summary-value'>{$orderDate}</td>
                    </tr>
                    <tr>
                        <td class='email-summary-label'>Order Status:</td>
                        <td class='email-summary-value'>{$orderStatus}</td>
                    </tr>
                    <tr>
                        <td class='email-summary-label'>Payment Method:</td>
                        <td class='email-summary-value'>{$paymentMethod}</td>
                    </tr>
                    <tr>
                        <td class='email-summary-label'>Payment Status:</td>
                        <td class='email-summary-value'>{$paymentStatus}</td>
                    </tr>
                    <tr>
                        <td class='email-summary-label'>Shipping Method:</td>
                        <td class='email-summary-value'>{$shippingMethod}</td>
                    </tr>
                </table>
            </div>
            
            <div class='email-section'>
                <h3 class='email-section-heading'>Order Items</h3>
                <table class='email-order-table'>
                    <thead>
                        <tr class='email-table-row-alt'>
                            <th class='email-table-header-cell'>Item</th>
                            <th class='email-table-header-cell email-table-cell-center'>Qty</th>
                            <th class='email-table-header-cell email-table-cell-right'>Price</th>
                            <th class='email-table-header-cell email-table-cell-right'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' class='email-summary-label'>
                                Order Total:
                            </td>
                            <td class='email-summary-value'>
                                \${$orderTotal}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            " . (!empty($shippingAddress) ? "
            <div class='email-section'>
                <h3 class='email-section-heading'>Shipping Information</h3>
                <p class='m-0'>{$shippingAddress}</p>
            </div>
            " : "") . "
            
            <div class='email-next-steps'>
                <h3 class='email-section-heading'>What's Next?</h3>
                <p class='email-next-step'>• We'll send you another email when your order ships</p>
                <p class='email-next-step'>• You can track your order status anytime by logging into your account</p>
                <p class='email-next-step'>• Questions? Contact us at " . FROM_EMAIL . "</p>
            </div>
            
            <div class='email-next-steps'>
                <a href='" . wf_url_with_params(rtrim($siteBase, '/') . '/', ['page' => 'receipt', 'orderId' => $orderId]) . "' class='email-cta-button'>
                    View Order Details
                </a>
            </div>
            
            <div class='email-footer'>
{{ ... }}
                <p class='email-footer-primary'>Thank you for shopping with WhimsicalFrog!</p>
                <p class='email-footer-secondary'>© " . date('Y') . " WhimsicalFrog. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>";

    return $html;
}

/**
 * Generate admin order notification email HTML
 */
function generateAdminNotificationEmail($orderData, $customerData, $orderItems)
{
    // Get brand colors from business settings
    $brandPrimary = BusinessSettings::getPrimaryColor();
    $brandSecondary = BusinessSettings::getSecondaryColor();

    $orderId = htmlspecialchars($orderData['id']);
    $customerName = htmlspecialchars(trim(($customerData['first_name'] ?? '') . ' ' . ($customerData['last_name'] ?? '')));
    if (empty(trim($customerName))) {
        $customerName = htmlspecialchars($customerData['username'] ?? 'Unknown Customer');
    }

    $customerEmail = htmlspecialchars($customerData['email'] ?? 'No email');
    $customerPhone = htmlspecialchars($customerData['phoneNumber'] ?? 'No phone');

    $orderDate = date('F j, Y g:i A', strtotime($orderData['date'] ?? 'now'));
    $orderTotal = number_format((float)$orderData['total'], 2);
    $paymentMethod = htmlspecialchars($orderData['paymentMethod'] ?? 'Not specified');
    $shippingMethod = htmlspecialchars($orderData['shippingMethod'] ?? 'Not specified');
    $orderStatus = htmlspecialchars($orderData['order_status'] ?? 'Processing');
    $paymentStatus = htmlspecialchars($orderData['paymentStatus'] ?? 'Pending');

    // Build items list
    $itemsHtml = '';
    $totalQuantity = 0;
    foreach ($orderItems as $item) {
        $itemName = htmlspecialchars($item['name'] ?? 'Item');
        $itemQuantity = (int)($item['quantity'] ?? 1);
        $itemPrice = number_format((float)($item['price'] ?? 0), 2);
        $itemTotal = number_format($itemQuantity * (float)($item['price'] ?? 0), 2);
        $totalQuantity += $itemQuantity;

        $itemsHtml .= "
            <tr>
                <td class='email-table-cell'>{$itemName}</td>
                <td class='email-table-cell email-table-cell-center'>{$itemQuantity}</td>
                <td class='email-table-cell email-table-cell-right'>\${$itemPrice}</td>
                <td class='email-table-cell email-table-cell-right'>\${$itemTotal}</td>
            </tr>";
    }

    // Customer address
    $customerAddress = 'Not provided';
    if (!empty($customerData['addressLine1'])) {
        $address = htmlspecialchars($customerData['addressLine1'] ?? '');
        if (!empty($customerData['addressLine2'])) {
            $address .= '<br>' . htmlspecialchars($customerData['addressLine2']);
        }
        if (!empty($customerData['city']) || !empty($customerData['state']) || !empty($customerData['zipCode'])) {
            $cityStateZip = trim(
                htmlspecialchars($customerData['city'] ?? '') . ', ' .
                htmlspecialchars($customerData['state'] ?? '') . ' ' .
                htmlspecialchars($customerData['zipCode'] ?? '')
            );
            $address .= '<br>' . ltrim($cityStateZip, ', ');
        }
        $customerAddress = $address;
    }

    // Shipping address (if different)
    $shippingAddress = '';
    if (!empty($orderData['shippingAddress']) && $orderData['shippingAddress'] !== $customerAddress) {
        $shippingAddress = nl2br(htmlspecialchars($orderData['shippingAddress']));
    }

    // Ensure admin base URL is defined for links below
    $adminBase = wf_get_admin_base();

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>New Order Alert - WhimsicalFrog Admin</title>
        <style>
        /* Basic email styles (class-based, no inline attributes) */
        body.email-body { margin:0; padding:0; background:#ffffff; color:#333; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; line-height:1.5; }
        .email-wrapper { max-width:600px; margin:0 auto; padding:16px; }
        .email-header { background: {$brandPrimary}; color:#fff; padding:16px; text-align:center; }
        .email-title { margin:0; font-size:20px; }
        .email-subtitle { margin:8px 0 0; font-size:14px; color:#eef; }
        .email-section { margin:16px 0; }
        .email-section-heading { font-size:16px; margin:0 0 8px; color: {$brandSecondary}; }
        .email-summary-table, .email-order-table, .email-table { width:100%; border-collapse:collapse; }
        .email-summary-table td, .email-order-table td, .email-order-table th, .email-table td, .email-table th { padding:8px; border-bottom:1px solid #eee; text-align:left; }
        .email-table-cell-center { text-align:center; }
        .email-table-cell-right { text-align:right; }
        .email-table-header-cell { background:#f6f6f6; font-weight:bold; }
        .email-table-row-alt { background:#f9f9f9; }
        .email-cta-button { display:inline-block; background: {$brandPrimary}; color:#fff !important; text-decoration:none; padding:10px 14px; border-radius:4px; }
        .email-secondary-cta { display:inline-block; color: {$brandPrimary}; text-decoration:none; padding:10px 14px; }
        .email-footer { margin-top:24px; font-size:12px; color:#666; text-align:center; }
        .email-footer-primary { margin:0 0 4px; }
        .email-footer-secondary { margin:0; }
        .email-badge-warning { background:#fff3cd; color:#856404; padding:2px 6px; border-radius:4px; }
        .email-status-received { color:#2e7d32; font-weight:bold; }
        .email-status-pending { color:#b26a00; font-weight:bold; }
        /* Utilities */
        .m-0 { margin:0; }
        .u-margin-right-10px { margin-right:10px; }
        .u-padding-4px-0 { padding:4px 0; }
        .u-align-top { vertical-align:top; }
        .u-color-333 { color:#333; }
        .u-color-666 { color:#666; }
        .u-line-height-1-6 { line-height:1.6; }
        .u-font-weight-bold { font-weight:bold; }
        .u-font-size-14px { font-size:14px; }
        .u-margin-top-10px { margin-top:10px; }
        .u-margin-top-20px { margin-top:20px; }
        .email-admin-header, .email-header { background: {$brandPrimary}; color:#fff; }
        .email-admin-summary-label { text-align:right; padding:8px; font-weight:bold; }
        .email-admin-summary-value { text-align:right; padding:8px; }
        .email-admin-notice { background:#fff8e1; border:1px solid #ffe082; padding:10px; border-radius:4px; margin:12px 0; }
        .email-shipping-box { border:1px solid #eee; border-radius:4px; padding:12px; margin:12px 0; background:#fafafa; }
        .email-admin-grid { display:block; }
        @media screen and (min-width: 480px) {
          .email-admin-grid { display:flex; gap:16px; }
          .email-admin-grid .email-section { flex:1; }
        }
        blockquote { margin:12px 0; padding-left:12px; border-left:3px solid #eee; color:#555; }
        </style>
    </head>
    <body class='email-body'>
        <div class='email-admin-header'>
            <h1 class='email-title'>🎉 NEW ORDER ALERT!</h1>
            <p class='email-subtitle'>WhimsicalFrog Admin Notification</p>
        </div>
        
        <div class='email-wrapper'>
            <div class='email-admin-notice'>
                <strong>⏰ Action Required:</strong> A new order has been placed and requires your attention!
            </div>
            
            <h2 class='email-admin-title'>Order #{$orderId}</h2>
            <p class='email-admin-summary'>
                <strong>Placed:</strong> {$orderDate} | 
                <strong>Total:</strong> \${$orderTotal} | 
                <strong>Items:</strong> {$totalQuantity}
            </p>
            
            <div class='email-admin-grid'>
                <div class='email-section'>
                    <h3 class='email-section-heading'>Customer Information</h3>
                    <table class='email-summary-table'>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Name:</td><td class='u-padding-4px-0'>{$customerName}</td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Email:</td><td class='u-padding-4px-0'>{$customerEmail}</td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Phone:</td><td class='u-padding-4px-0'>{$customerPhone}</td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0 u-align-top'>Address:</td><td class='u-padding-4px-0'>{$customerAddress}</td></tr>
                    </table>
                </div>
                
                <div class='email-section'>
                    <h3 class='email-section-heading'>Order Status</h3>
                    <table class='email-summary-table'>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Status:</td><td class='u-padding-4px-0'><span class='email-badge-warning'>{$orderStatus}</span></td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Payment:</td><td class='u-padding-4px-0'>{$paymentMethod}</td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Payment Status:</td><td class='u-padding-4px-0'><span class='" . ($paymentStatus === 'Received' ? 'email-status-received' : 'email-status-pending') . "'>{$paymentStatus}</span></td></tr>
                        <tr><td class='u-font-weight-bold u-padding-4px-0'>Shipping:</td><td class='u-padding-4px-0'>{$shippingMethod}</td></tr>
                    </table>
                </div>
            </div>
            
            " . (!empty($shippingAddress) ? "
            <div class='email-shipping-box'>
                <h4 class='email-shipping-heading'>📦 Custom Shipping Address</h4>
                <p class='m-0'>{$shippingAddress}</p>
            </div>
            " : "") . "
            
            <div class='email-section'>
                <h3 class='email-section-heading'>Ordered Items</h3>
                <table class='email-table'>
                    <thead>
                        <tr class='email-table-row-alt'>
                            <th class='email-table-header-cell'>Item</th>
                            <th class='email-table-header-cell email-table-cell-center'>Qty</th>
                            <th class='email-table-header-cell email-table-cell-right'>Price</th>
                            <th class='email-table-header-cell email-table-cell-right'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan='3' class='email-admin-summary-label'>
                                Order Total:
                            </td>
                            <td class='email-admin-summary-value'>
                                \${$orderTotal}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <div class='email-next-steps'>
                <a href='" . wf_url_with_params($adminBase, ['section' => 'orders', 'view' => $orderId]) . "' class='email-cta-button u-margin-right-10px'>
                    View in Admin Panel
                </a>
                <a href='" . wf_url_with_params($adminBase, ['section' => 'orders']) . "' class='email-secondary-cta'>
                    All Orders
                </a>
            </div>
            
            <div class='email-admin-quick-actions'>
                <h4>⚡ Quick Actions Needed</h4>
                <ul>
                    <li>Verify payment status and process if needed</li>
                    <li>Check inventory and prepare items for " . ($shippingMethod === 'Customer Pickup' ? 'pickup' : 'shipping') . "</li>
                    <li>Update order status as processing begins</li>
                    <li>Contact customer if any issues arise</li>
                </ul>
            </div>
            
            <div class='email-admin-footer'>
                <p class='email-footer-primary'>WhimsicalFrog Admin Notification System</p>
                <p class='email-footer-secondary'>Order placed at " . date('g:i A \o\n F j, Y') . "</p>
            </div>
        </div>
    </body>
    </html>";

    return $html;
}

// Note: sendOrderConfirmationEmails function moved to email_notifications.php
// to avoid function redeclaration conflicts
