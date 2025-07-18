<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';
require_once __DIR__ . '/../includes/email_helper.php';

/**
 * Send order confirmation and admin notification emails using templates
 */
function sendOrderConfirmationEmails($orderId, $pdo)
{
    $results = ['customer' => false, 'admin' => false];

    try {
        // Get order details
        $orderStmt = $pdo->prepare("
            SELECT o.*, u.firstName, u.lastName, u.email, u.username, u.phoneNumber 
            FROM orders o 
            LEFT JOIN users u ON o.userId = u.userId 
            WHERE o.id = ?
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            error_log("Email notification: Order $orderId not found");
            return $results;
        }

        // Get order items
        $itemsStmt = $pdo->prepare("
            SELECT oi.*, i.name, i.sku, oi.quantity, oi.price 
            FROM order_items oi 
            LEFT JOIN items i ON oi.sku = i.sku 
            WHERE oi.orderId = ?
        ");
        $itemsStmt->execute([$orderId]);
        $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get email template assignments
        $assignmentsStmt = $pdo->prepare("
            SELECT eta.email_type, et.* 
            FROM email_template_assignments eta
            JOIN email_templates et ON eta.template_id = et.id
            WHERE eta.email_type IN ('order_confirmation', 'admin_notification') AND et.is_active = 1
        ");
        $assignmentsStmt->execute();
        $assignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);

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

        // Format shipping address
        $shippingAddress = 'Not specified';
        if (!empty($order['shippingAddress'])) {
            $addressData = json_decode($order['shippingAddress'], true);
            if (is_array($addressData)) {
                $addressParts = array_filter([
                    $addressData['addressLine1'] ?? '',
                    $addressData['addressLine2'] ?? '',
                    $addressData['city'] ?? '',
                    $addressData['state'] ?? '',
                    $addressData['zipCode'] ?? ''
                ]);
                $shippingAddress = implode(', ', $addressParts);
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
                    $adminStmt = $pdo->prepare("SELECT setting_value FROM business_settings WHERE setting_key = 'admin_email'");
                    $adminStmt->execute();
                    $adminEmailResult = $adminStmt->fetchColumn();
                    if ($adminEmailResult) {
                        $adminEmail = $adminEmailResult;
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
        $htmlContent = preg_replace('/<head>/', "<head>\n    <link rel='stylesheet' href='https://whimsicalfrog.us/css/email-styles.css'>", $htmlContent);
        $htmlContent = preg_replace('/<body([^>]*)>/', "<body$1 class='email-body' style=\"-brand-primary: {$brandPrimary}; -brand-secondary: {$brandSecondary};\">", $htmlContent);
        $textContent = $template['text_content'] ?? '';

        foreach ($variables as $key => $value) {
            $placeholder = '{' . $key . '}';
            $subject = str_replace($placeholder, $value, $subject);
            $htmlContent = str_replace($placeholder, $value, $htmlContent);
            $textContent = str_replace($placeholder, $value, $textContent);
        }

        // Configure EmailHelper
        EmailHelper::configure([
            'smtp_enabled' => defined('SMTP_ENABLED') ? SMTP_ENABLED : false,
            'smtp_host' => defined('SMTP_HOST') ? SMTP_HOST : '',
            'smtp_port' => defined('SMTP_PORT') ? SMTP_PORT : 587,
            'smtp_username' => defined('SMTP_USERNAME') ? SMTP_USERNAME : '',
            'smtp_password' => defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '',
            'smtp_encryption' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls',
            'from_email' => defined('FROM_EMAIL') ? FROM_EMAIL : '',
            'from_name' => defined('FROM_NAME') ? FROM_NAME : 'WhimsicalFrog',
            'reply_to' => defined('FROM_EMAIL') ? FROM_EMAIL : '',
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
        $pdo = new PDO($GLOBALS['dsn'], $GLOBALS['user'], $GLOBALS['pass'], $GLOBALS['options']);

        $stmt = $pdo->prepare("
            INSERT INTO email_logs 
            (to_email, subject, email_type, template_id, status, sent_at, error_message) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");

        $stmt->execute([
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
?> 