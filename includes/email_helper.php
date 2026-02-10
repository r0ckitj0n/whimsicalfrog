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
    private static $emailLogColumns = null;

    private static function getEmailLogColumns(): array
    {
        if (is_array(self::$emailLogColumns)) {
            return self::$emailLogColumns;
        }

        try {
            $cols = Database::queryAll("SHOW COLUMNS FROM email_logs");
            $map = [];
            foreach (($cols ?: []) as $col) {
                $field = (string)($col['Field'] ?? '');
                if ($field !== '') {
                    $map[$field] = true;
                }
            }
            self::$emailLogColumns = $map;
            return $map;
        } catch (Exception $e) {
            error_log("Unable to inspect email_logs schema: " . $e->getMessage());
            self::$emailLogColumns = [];
            return self::$emailLogColumns;
        }
    }

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
            'bcc' => [],
            'email_type' => null,
            'order_id' => null,
            'created_by' => WF_Constants::ROLE_SYSTEM,
            'content' => null
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
                self::logEmail(
                    $to,
                    $subject,
                    WF_Constants::EMAIL_STATUS_SENT,
                    null,
                    $options['order_id'],
                    [
                        'from_email' => $options['from_email'],
                        'content' => $options['content'] ?? $body,
                        'email_type' => $options['email_type'],
                        'created_by' => $options['created_by'],
                    ]
                );
            }

            return $result;
        } catch (Exception $e) {
            self::logEmail(
                $to,
                $subject,
                WF_Constants::EMAIL_STATUS_FAILED,
                $e->getMessage(),
                $options['order_id'],
                [
                    'from_email' => $options['from_email'],
                    'content' => $options['content'] ?? $body,
                    'email_type' => $options['email_type'],
                    'created_by' => $options['created_by'],
                ]
            );
            
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

    public static function logEmail($to, $subject, $status = 'sent', $error = null, $order_id = null, $meta = [])
    {
        try {
            $config = EmailConfig::getConfig();
            $columns = self::getEmailLogColumns();
            if (empty($columns)) {
                return false;
            }

            $toEmail = is_array($to) ? implode(', ', $to) : (string) $to;
            $fromEmail = (string) ($meta['from_email'] ?? $config['from_email'] ?? '');
            $content = (string) ($meta['content'] ?? '');
            $emailType = isset($meta['email_type']) ? trim((string) $meta['email_type']) : '';
            $createdBy = isset($meta['created_by']) ? (string) $meta['created_by'] : WF_Constants::ROLE_SYSTEM;

            $insert = [];
            if (!empty($columns['to_email'])) $insert['to_email'] = $toEmail;
            if (!empty($columns['from_email'])) $insert['from_email'] = $fromEmail;
            if (!empty($columns['subject'])) {
                $insert['subject'] = (string) $subject;
            } elseif (!empty($columns['email_subject'])) {
                $insert['email_subject'] = (string) $subject;
            }
            if (!empty($columns['content'])) $insert['content'] = $content;
            if (!empty($columns['email_type']) && $emailType !== '') $insert['email_type'] = $emailType;
            if (!empty($columns['status'])) $insert['status'] = (string) $status;
            if (!empty($columns['error_message'])) $insert['error_message'] = $error;
            if (!empty($columns['order_id']) && $order_id !== null && $order_id !== '') $insert['order_id'] = (string) $order_id;
            if (!empty($columns['created_by'])) $insert['created_by'] = $createdBy;
            if (!empty($columns['sent_at'])) $insert['sent_at'] = date('Y-m-d H:i:s');
            if (!empty($columns['created_at'])) $insert['created_at'] = date('Y-m-d H:i:s');

            if (empty($insert)) {
                return false;
            }

            $names = array_keys($insert);
            $placeholders = implode(', ', array_fill(0, count($names), '?'));
            $sql = "INSERT INTO email_logs (" . implode(', ', $names) . ") VALUES ($placeholders)";
            Database::execute($sql, array_values($insert));
            return true;
        } catch (Exception $e) {
            error_log("Failed to log email: " . $e->getMessage());
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
