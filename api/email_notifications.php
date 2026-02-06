<?php
/**
 * Email Notifications API
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/helpers/EmailNotificationHelper.php';

function sendOrderConfirmationEmails($order_id, $pdo)
{
    $results = [WF_Constants::ROLE_CUSTOMER => false, WF_Constants::ROLE_ADMIN => false];
    try {
        $order = Database::queryOne("SELECT o.*, u.first_name, u.last_name, u.email, u.username, u.phone_number FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?", [$order_id]);
        if (!$order)
            return $results;

        $orderItems = Database::queryAll("SELECT oi.*, i.name, i.sku, oi.quantity, oi.unit_price as price FROM order_items oi LEFT JOIN items i ON oi.sku = i.sku WHERE oi.order_id = ?", [$order_id]);
        $assignments = Database::queryAll("SELECT eta.email_type, et.* FROM email_template_assignments eta JOIN email_templates et ON eta.template_id = et.id WHERE eta.email_type IN (?, ?) AND et.is_active = 1", [
            WF_Constants::EMAIL_TYPE_ORDER_CONFIRMATION,
            WF_Constants::EMAIL_TYPE_ADMIN_NOTIFICATION
        ]);

        $templates = [];
        foreach ($assignments as $a)
            $templates[$a['email_type']] = $a;

        $vars = EmailNotificationHelper::prepareOrderVariables($order, $orderItems);

        if (isset($templates[WF_Constants::EMAIL_TYPE_ORDER_CONFIRMATION]) && !empty($order['email'])) {
            $results['customer'] = sendTemplatedEmail($templates[WF_Constants::EMAIL_TYPE_ORDER_CONFIRMATION], $order['email'], $vars, WF_Constants::EMAIL_TYPE_ORDER_CONFIRMATION);
        }

        if (isset($templates[WF_Constants::EMAIL_TYPE_ADMIN_NOTIFICATION])) {
            $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : null;
            if (!$adminEmail) {
                $adminRow = Database::queryOne("SELECT setting_value FROM business_settings WHERE setting_key = 'admin_email'");
                if ($adminRow)
                    $adminEmail = $adminRow['setting_value'];
            }
            if ($adminEmail)
                $results['admin'] = sendTemplatedEmail($templates[WF_Constants::EMAIL_TYPE_ADMIN_NOTIFICATION], $adminEmail, $vars, WF_Constants::EMAIL_TYPE_ADMIN_NOTIFICATION);
        }
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
    }
    return $results;
}

function sendTemplatedEmail($template, $toEmail, $variables, $emailType)
{
    try {
        $subject = $template['subject'];
        $htmlContent = $template['html_content'];
        $styleBlock = "<style>" . EmailNotificationHelper::getEmailStyles() . "</style>";

        $htmlContent = preg_replace('/<head>/', "<head>\n    " . $styleBlock, $htmlContent);
        $htmlContent = preg_replace('/<body(?![^>]*\bclass=)([^>]*)>/i', '<body$1 class="email-body">', $htmlContent);
        $htmlContent = preg_replace('/<body([^>]*)class=(\"|\')(?![^\2]*\bemail-body\b)([^\2]*)(\2)([^>]*)>/i', '<body$1class=$2$3 email-body$2$5>', $htmlContent);
        $textContent = $template['text_content'] ?? '';

        foreach ($variables as $key => $value) {
            $placeholder = '{' . $key . '}';
            $subject = str_replace($placeholder, $value, $subject);
            $htmlContent = str_replace($placeholder, $value, $htmlContent);
            $textContent = str_replace($placeholder, $value, $textContent);
        }

        // Configure EmailHelper using BusinessSettings and secret store
        require_once __DIR__ . '/../includes/secret_store.php';
        $emailSettings = BusinessSettings::getByCategory('email');
        $smtpEnabledVal = $emailSettings['smtp_enabled'] ?? false;
        $smtpEnabled = is_bool($smtpEnabledVal) ? $smtpEnabledVal : in_array(strtolower((string) $smtpEnabledVal), ['1', 'true', 'yes', 'on'], true);

        $fromEmail = BusinessSettings::getBusinessEmail();
        $fromName = BusinessSettings::getBusinessName() ?: 'WhimsicalFrog';
        $secUser = secret_get('smtp_username');
        $secPass = secret_get('smtp_password');

        EmailHelper::configure([
            'smtp_enabled' => $smtpEnabled,
            'smtp_host' => (string) ($emailSettings['smtp_host'] ?? ''),
            'smtp_port' => (int) ($emailSettings['smtp_port'] ?? 587),
            'smtp_username' => $secUser ?: '',
            'smtp_password' => $secPass ?: '',
            'smtp_encryption' => (string) ($emailSettings['smtp_encryption'] ?? 'tls'),
            'from_email' => $fromEmail,
            'from_name' => $fromName,
            'reply_to' => $fromEmail,
        ]);

        $success = EmailHelper::send($toEmail, $subject, $htmlContent, ['is_html' => true]);
        logEmailSend($toEmail, $subject, $emailType, $success ? WF_Constants::EMAIL_STATUS_SENT : WF_Constants::EMAIL_STATUS_FAILED, $template['id']);
        return $success;
    } catch (Exception $e) {
        logEmailSend($toEmail, $template['subject'] ?? 'Email', $emailType, WF_Constants::EMAIL_STATUS_FAILED, $template['id'], $e->getMessage());
        return false;
    }
}

function logEmailSend($to, $subject, $type, $status, $templateId, $error = null)
{
    try {
        Database::execute("INSERT INTO email_logs (to_email, subject, email_type, template_id, status, sent_at, error_message) VALUES (?, ?, ?, ?, ?, NOW(), ?)", [$to, $subject, $type, $templateId, $status, $error]);
    } catch (Exception $e) {
        error_log("Log error: " . $e->getMessage());
    }
}
