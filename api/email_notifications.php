<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/business_settings_helper.php';
require_once __DIR__ . '/../includes/email_helper.php';

/**
 * Send order confirmation and admin notification emails using templates
 */
function sendOrderConfirmationEmails($orderId, $pdo)
{
    $results = ['customer' => false, 'admin' => false];

    try {
        // Get order details
        $order = Database::queryOne("
            SELECT o.*, u.firstName, u.lastName, u.email, u.username, u.phoneNumber 
            FROM orders o 
            LEFT JOIN users u ON o.userId = u.id 
            WHERE o.id = ?
        ", [$orderId]);

        if (!$order) {
            error_log("Email notification: Order $orderId not found");
            return $results;
        }

        // Get order items
        $orderItems = Database::queryAll("
            SELECT oi.*, i.name, i.sku, oi.quantity, oi.price 
            FROM order_items oi 
            LEFT JOIN items i ON oi.sku = i.sku 
            WHERE oi.orderId = ?
        ", [$orderId]);

        // Get email template assignments
        $assignments = Database::queryAll("
            SELECT eta.email_type, et.* 
            FROM email_template_assignments eta
            JOIN email_templates et ON eta.template_id = et.id
            WHERE eta.email_type IN ('order_confirmation', 'admin_notification') AND et.is_active = 1
        ");

        $templates = [];
        foreach ($assignments as $assignment) {
            $templates[$assignment['email_type']] = $assignment;
        }

        // Prepare data for email templates
        $customerName = trim(($order['firstName'] ?? '') . ' ' . ($order['lastName'] ?? ''));
        if (empty($customerName)) {
            $customerName = $order['username'] ?? 'Valued Customer';
        }

        $orderDate = date('F j, Y g:i A', strtotime($order['date'] ?? 'now'));
        $orderTotal = '$' . number_format((float)$order['total'], 2);

        // Format shipping address (support both camelCase and snake_case keys)
        $shippingAddress = 'Not specified';
        if (!empty($order['shippingAddress'])) {
            $addressData = json_decode($order['shippingAddress'], true);
            if (is_array($addressData)) {
                $line1 = $addressData['addressLine1'] ?? $addressData['address_line1'] ?? '';
                $line2 = $addressData['addressLine2'] ?? $addressData['address_line2'] ?? '';
                $city  = $addressData['city'] ?? '';
                $state = $addressData['state'] ?? '';
                $zip   = $addressData['zipCode'] ?? $addressData['zip_code'] ?? '';
                $addressParts = array_filter([$line1, $line2, $city, $state, $zip]);
                $shippingAddress = $addressParts ? implode(', ', $addressParts) : 'Not specified';
            } else {
                $shippingAddress = $order['shippingAddress'];
            }
        }

        // Format order items for email
        $itemsListHtml = '';
        $itemsListText = '';
        foreach ($orderItems as $item) {
            $itemName = $item['name'] ?? 'Unknown Item';
            $itemSku = $item['sku'] ?? '';
            $itemQuantity = $item['quantity'] ?? 1;
            $itemPrice = '$' . number_format((float)($item['price'] ?? 0), 2);
            $itemTotal = '$' . number_format($itemQuantity * (float)($item['price'] ?? 0), 2);

            $itemsListHtml .= "<li class='email-list-item'>";
            $itemsListHtml .= "<strong>{$itemName}</strong>";
            if ($itemSku) {
                $itemsListHtml .= " <small class='u-color-666'>({$itemSku})</small>";
            }
            $itemsListHtml .= "<br>";
            $itemsListHtml .= "<span class='u-color-666'>Quantity: {$itemQuantity} × {$itemPrice} = {$itemTotal}</span>";
            $itemsListHtml .= "</li>";

            $itemsListText .= "- {$itemName}";
            if ($itemSku) {
                $itemsListText .= " ({$itemSku})";
            }
            $itemsListText .= " - Qty: {$itemQuantity} × {$itemPrice} = {$itemTotal}\n";
        }

        // Common email variables
        $emailVariables = [
            'customer_name' => $customerName,
            'customer_email' => $order['email'] ?? 'N/A',
            'order_id' => $orderId,
            'order_date' => $orderDate,
            'order_total' => $orderTotal,
            'items' => $itemsListHtml,
            'items_text' => $itemsListText,
            'shipping_address' => $shippingAddress,
            'payment_method' => $order['paymentMethod'] ?? 'Not specified',
            'shipping_method' => $order['shippingMethod'] ?? 'Not specified',
            'order_status' => $order['status'] ?? 'Processing',
            'payment_status' => $order['paymentStatus'] ?? 'Pending'
        ];

        // Send customer confirmation email
        if (isset($templates['order_confirmation']) && !empty($order['email'])) {
            $results['customer'] = sendTemplatedEmail(
                $templates['order_confirmation'],
                $order['email'],
                $emailVariables,
                'order_confirmation'
            );
        }

        // Send admin notification email
        if (isset($templates['admin_notification'])) {
            // Get admin email from config
            $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : null;

            // Also try to get from business settings
            if (!$adminEmail) {
                try {
                    $adminRow = Database::queryOne("SELECT setting_value FROM business_settings WHERE setting_key = 'admin_email'");
                    if ($adminRow && isset($adminRow['setting_value'])) {
                        $adminEmail = $adminRow['setting_value'];
                    }
                } catch (Exception $e) {
                    error_log("Email notification: Could not get admin email from business settings: " . $e->getMessage());
                }
            }

            if ($adminEmail) {
                $results['admin'] = sendTemplatedEmail(
                    $templates['admin_notification'],
                    $adminEmail,
                    $emailVariables,
                    'admin_notification'
                );
            } else {
                error_log("Email notification: No admin email configured for order $orderId");
            }
        }

    } catch (Exception $e) {
        error_log("Email notification error for order $orderId: " . $e->getMessage());
    }

    return $results;
}

/**
 * Send an email using a template
 */
function sendTemplatedEmail($template, $toEmail, $variables, $emailType)
{
    try {
        // Replace variables in subject and content
        $subject = $template['subject'];
        $htmlContent = $template['html_content'];
        // Inject shared CSS and CSS variable custom properties for dynamic brand colors
        $brandPrimary = BusinessSettings::getPrimaryColor();
        $brandSecondary = BusinessSettings::getSecondaryColor();
        $styleBlock = "<style>
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
        </style>";
        $htmlContent = preg_replace('/<head>/', "<head>\n    " . $styleBlock, $htmlContent);
        // Ensure body has class email-body without overwriting other attributes
        // Add class if none present
        $htmlContent = preg_replace('/<body(?![^>]*\\bclass=)([^>]*)>/i', '<body$1 class="email-body">', $htmlContent);
        // If class exists but does not include email-body, append it
        $htmlContent = preg_replace('/<body([^>]*)class=(\"|\')(?![^\2]*\\bemail-body\\b)([^\2]*)(\2)([^>]*)>/i', '<body$1class=$2$3 email-body$2$5>', $htmlContent);
        $textContent = $template['text_content'] ?? '';

        foreach ($variables as $key => $value) {
            $placeholder = '{' . $key . '}';
            $subject = str_replace($placeholder, $value, $subject);
            $htmlContent = str_replace($placeholder, $value, $htmlContent);
            $textContent = str_replace($placeholder, $value, $textContent);
        }

        // Attempt to load strict email_config to define constants; fallback gracefully if unavailable
        try {
            require_once __DIR__ . '/email_config.php';
        } catch (Throwable $e) {
            error_log('email_notifications: email_config.php unavailable: ' . $e->getMessage());
        }

        // Configure EmailHelper using constants if defined, otherwise derive minimal config from BusinessSettings
        $fallbackFromEmail = (string) BusinessSettings::getBusinessEmail();
        $fallbackFromName  = (string) BusinessSettings::getBusinessName();
        EmailHelper::configure([
            'smtp_enabled'   => defined('SMTP_ENABLED') ? (bool)SMTP_ENABLED : false,
            'smtp_host'      => defined('SMTP_HOST') ? (string)SMTP_HOST : '',
            'smtp_port'      => defined('SMTP_PORT') ? (int)SMTP_PORT : 587,
            'smtp_username'  => defined('SMTP_USERNAME') ? (string)SMTP_USERNAME : '',
            'smtp_password'  => defined('SMTP_PASSWORD') ? (string)SMTP_PASSWORD : '',
            'smtp_encryption'=> defined('SMTP_ENCRYPTION') ? (string)SMTP_ENCRYPTION : 'tls',
            'from_email'     => defined('FROM_EMAIL') && FROM_EMAIL ? (string)FROM_EMAIL : $fallbackFromEmail,
            'from_name'      => defined('FROM_NAME') && FROM_NAME ? (string)FROM_NAME : ($fallbackFromName ?: 'WhimsicalFrog'),
            'reply_to'       => defined('FROM_EMAIL') && FROM_EMAIL ? (string)FROM_EMAIL : $fallbackFromEmail,
        ]);

        // Send email
        $success = EmailHelper::send($toEmail, $subject, $htmlContent, [
            'is_html' => true
        ]);

        // Log the email
        logEmailSend($toEmail, $subject, $emailType, $success ? 'sent' : 'failed', $template['id']);

        return $success;

    } catch (Exception $e) {
        error_log("Send templated email error: " . $e->getMessage());
        logEmailSend($toEmail, $template['subject'] ?? 'Email', $emailType, 'failed', $template['id'], $e->getMessage());
        return false;
    }
}

/**
 * Log email sending attempts
 */
function logEmailSend($toEmail, $subject, $emailType, $status, $templateId = null, $errorMessage = null)
{
    try {
        Database::execute("
            INSERT INTO email_logs 
            (to_email, subject, email_type, template_id, status, sent_at, error_message) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ", [
            $toEmail,
            $subject,
            $emailType,
            $templateId,
            $status,
            $errorMessage
        ]);

    } catch (Exception $e) {
        error_log("Email logging error: " . $e->getMessage());
    }
}
// sendTestEmail function moved to email_manager.php for centralization