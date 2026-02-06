<?php

/**
 * Centralized Email Helper (Conductor)
 * Handles email sending by delegating to specialized modular components.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/secret_store.php';
require_once __DIR__ . '/email/EmailConfig.php';
require_once __DIR__ . '/email/SmtpSender.php';
require_once __DIR__ . '/email/MailSender.php';
require_once __DIR__ . '/email/TemplateSender.php';

class EmailHelper
{
    /**
     * Configure email settings at runtime.
     */
    public static function configure($config)
    {
        EmailConfig::configure($config);
    }

    /**
     * Check SMTP connectivity
     */
    public static function preflightSMTP()
    {
        return SmtpSender::preflight(EmailConfig::getConfig());
    }

    /**
     * Send email using configured method (SMTP or mail())
     */
    public static function send($to, $subject, $body, $options = [])
    {
        $config = EmailConfig::getConfig();
        $options = array_merge([
            'from_email' => $config['from_email'],
            'from_name' => $config['from_name'],
            'reply_to' => $config['reply_to'],
            'is_html' => true,
            'attachments' => [],
            'cc' => [],
            'bcc' => []
        ], $options);

        try {
            $result = false;
            if ($config['smtp_enabled']) {
                try {
                    $result = SmtpSender::send($to, $subject, $body, $options, $config);
                } catch (Exception $smtpEx) {
                    error_log('SMTP send failed, falling back to mail(): ' . $smtpEx->getMessage());
                    $result = MailSender::send($to, $subject, $body, $options, $config['charset']);
                }
            } else {
                $result = MailSender::send($to, $subject, $body, $options, $config['charset']);
            }

            if ($result) {
                self::logEmail($to, $subject, 'sent');
            }

            return $result;
        } catch (Exception $e) {
            self::logEmail($to, $subject, 'failed', $e->getMessage());
            
            if (class_exists('Logger')) {
                Logger::error('Email send failed', [
                    'to' => $to,
                    'subject' => $subject,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }

    /**
     * Specialized sending methods delegated to TemplateSender
     */
    public static function sendTemplate($template, $to, $subject, $variables = [], $options = []) { return TemplateSender::send($template, $to, $subject, $variables, $options); }
    public static function sendOrderConfirmation($order, $customer, $items) { return TemplateSender::sendOrderConfirmation($order, $customer, $items); }
    public static function sendAdminNotification($order, $customer, $items, $adminEmail = null) { return TemplateSender::sendAdminNotification($order, $customer, $items, $adminEmail); }
    public static function sendPasswordReset($email, $token, $name = '') { return TemplateSender::sendPasswordReset($email, $token, $name, self::getBaseUrl()); }
    public static function sendWelcome($email, $name, $token = null) { return TemplateSender::sendWelcome($email, $name, $token, self::getBaseUrl()); }

    public static function test($testEmail, $testMessage = 'This is a test email.')
    {
        $subject = 'Email Configuration Test';
        $body = $testMessage . "\n\nSent at: " . date('Y-m-d H:i:s');
        return self::send($testEmail, $subject, $body, ['is_html' => false]);
    }

    public static function createFromBusinessSettings($pdo)
    {
        return EmailConfig::createFromBusinessSettings($pdo);
    }

    public static function logEmail($to, $subject, $status = 'sent', $error = null, $order_id = null)
    {
        try {
            $config = EmailConfig::getConfig();
            Database::execute(
                "INSERT INTO email_logs (to_email, from_email, subject, status, error_message, order_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [is_array($to) ? implode(', ', $to) : $to, $config['from_email'], $subject, $status, $error, $order_id]
            );
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function getBaseUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host;
    }
}

// Convenience functions
function send_email($to, $subject, $body, $options = []) { return EmailHelper::send($to, $subject, $body, $options); }
function send_order_confirmation($o, $c, $i) { return EmailHelper::sendOrderConfirmation($o, $c, $i); }
function send_admin_notification($o, $c, $i, $a = null) { return EmailHelper::sendAdminNotification($o, $c, $i, $a); }
function test_email($e, $m = 'Test') { return EmailHelper::test($e, $m); }
