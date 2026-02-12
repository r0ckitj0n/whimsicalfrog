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
    private static $emailLogSchemaEnsured = false;

    private static function fetchEmailLogColumnsFromDatabase(): array
    {
        $cols = Database::queryAll("SHOW COLUMNS FROM email_logs");
        $map = [];
        foreach (($cols ?: []) as $col) {
            $field = (string)($col['Field'] ?? '');
            if ($field !== '') {
                $map[$field] = true;
            }
        }
        return $map;
    }

    private static function ensureEmailLogsSchema(): void
    {
        if (self::$emailLogSchemaEnsured) {
            return;
        }

        Database::execute("
            CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                from_email VARCHAR(255) NULL,
                subject VARCHAR(500) NULL,
                email_subject VARCHAR(500) NULL,
                content LONGTEXT NULL,
                email_type VARCHAR(100) NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'sent',
                error_message TEXT NULL,
                sent_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                order_id VARCHAR(50) NULL,
                created_by VARCHAR(100) NULL,
                cc_email TEXT NULL,
                bcc_email TEXT NULL,
                reply_to VARCHAR(255) NULL,
                is_html TINYINT(1) NOT NULL DEFAULT 1,
                headers_json LONGTEXT NULL,
                attachments_json LONGTEXT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sent_at (sent_at),
                INDEX idx_email_type (email_type),
                INDEX idx_status (status),
                INDEX idx_to_email (to_email),
                INDEX idx_order_id (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$emailLogColumns = null;
        $columns = self::fetchEmailLogColumnsFromDatabase();

        $columnDefinitions = [
            'subject' => 'ADD COLUMN subject VARCHAR(500) NULL',
            'email_subject' => 'ADD COLUMN email_subject VARCHAR(500) NULL',
            'content' => 'ADD COLUMN content LONGTEXT NULL',
            'email_type' => 'ADD COLUMN email_type VARCHAR(100) NULL',
            'status' => "ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'sent'",
            'error_message' => 'ADD COLUMN error_message TEXT NULL',
            'sent_at' => 'ADD COLUMN sent_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
            'order_id' => 'ADD COLUMN order_id VARCHAR(50) NULL',
            'created_by' => 'ADD COLUMN created_by VARCHAR(100) NULL',
            'cc_email' => 'ADD COLUMN cc_email TEXT NULL',
            'bcc_email' => 'ADD COLUMN bcc_email TEXT NULL',
            'reply_to' => 'ADD COLUMN reply_to VARCHAR(255) NULL',
            'is_html' => 'ADD COLUMN is_html TINYINT(1) NOT NULL DEFAULT 1',
            'headers_json' => 'ADD COLUMN headers_json LONGTEXT NULL',
            'attachments_json' => 'ADD COLUMN attachments_json LONGTEXT NULL',
            'created_at' => 'ADD COLUMN created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP'
        ];
        foreach ($columnDefinitions as $column => $definition) {
            if (empty($columns[$column])) {
                self::safeAddEmailLogColumn($column, $definition);
            }
        }

        Database::execute('ALTER TABLE email_logs MODIFY COLUMN email_type VARCHAR(100) NULL');
        Database::execute("ALTER TABLE email_logs MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'sent'");
        Database::execute('ALTER TABLE email_logs MODIFY COLUMN content LONGTEXT NULL');

        self::$emailLogColumns = null;
        $columns = self::fetchEmailLogColumnsFromDatabase();
        self::$emailLogColumns = $columns;
        if (!empty($columns['email_subject']) && !empty($columns['subject'])) {
            Database::execute("UPDATE email_logs SET subject = email_subject WHERE (subject IS NULL OR subject = '') AND email_subject IS NOT NULL AND email_subject != ''");
        }

        self::$emailLogSchemaEnsured = true;
    }

    private static function getEmailLogColumns(): array
    {
        self::ensureEmailLogsSchema();

        if (is_array(self::$emailLogColumns)) {
            return self::$emailLogColumns;
        }

        try {
            $map = self::fetchEmailLogColumnsFromDatabase();
            self::$emailLogColumns = $map;
            return $map;
        } catch (Exception $e) {
            error_log("Unable to inspect email_logs schema: " . $e->getMessage());
            self::$emailLogColumns = [];
            return self::$emailLogColumns;
        }
    }

    private static function normalizeEmailList($value): string
    {
        if (is_array($value)) {
            $parts = array_filter(array_map(static function ($entry) {
                return trim((string) $entry);
            }, $value), static function ($entry) {
                return $entry !== '';
            });
            return implode(', ', $parts);
        }

        return trim((string) $value);
    }

    private static function jsonEncodeForLog($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return ($json === false) ? null : $json;
    }

    private static function safeAddEmailLogColumn(string $column, string $definition): void
    {
        $columns = self::fetchEmailLogColumnsFromDatabase();
        if (!empty($columns[$column])) {
            return;
        }

        try {
            Database::execute("ALTER TABLE email_logs $definition");
        } catch (Exception $e) {
            $message = strtolower((string) $e->getMessage());
            if (strpos($message, 'duplicate column name') === false) {
                throw $e;
            }
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
            'content' => null,
            'headers' => null
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
                        'cc' => $options['cc'],
                        'bcc' => $options['bcc'],
                        'reply_to' => $options['reply_to'],
                        'is_html' => $options['is_html'],
                        'attachments' => $options['attachments'],
                        'headers' => $options['headers'],
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
                    'cc' => $options['cc'],
                    'bcc' => $options['bcc'],
                    'reply_to' => $options['reply_to'],
                    'is_html' => $options['is_html'],
                    'attachments' => $options['attachments'],
                    'headers' => $options['headers'],
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
        $body = $testMessage . "\n\nSent at: " . gmdate('Y-m-d H:i:s') . " UTC";
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

            $toEmail = self::normalizeEmailList($to);
            $fromEmail = (string) ($meta['from_email'] ?? $config['from_email'] ?? '');
            $content = (string) ($meta['content'] ?? '');
            $emailType = isset($meta['email_type']) ? trim((string) $meta['email_type']) : '';
            $createdBy = isset($meta['created_by']) ? (string) $meta['created_by'] : WF_Constants::ROLE_SYSTEM;
            $ccEmail = self::normalizeEmailList($meta['cc'] ?? '');
            $bccEmail = self::normalizeEmailList($meta['bcc'] ?? '');
            $replyTo = trim((string) ($meta['reply_to'] ?? ''));
            $isHtml = !empty($meta['is_html']) ? 1 : 0;
            $headersJson = self::jsonEncodeForLog($meta['headers'] ?? null);
            $attachmentsJson = self::jsonEncodeForLog($meta['attachments'] ?? null);

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
            if (!empty($columns['cc_email'])) $insert['cc_email'] = $ccEmail;
            if (!empty($columns['bcc_email'])) $insert['bcc_email'] = $bccEmail;
            if (!empty($columns['reply_to'])) $insert['reply_to'] = $replyTo;
            if (!empty($columns['is_html'])) $insert['is_html'] = $isHtml;
            if (!empty($columns['headers_json'])) $insert['headers_json'] = $headersJson;
            if (!empty($columns['attachments_json'])) $insert['attachments_json'] = $attachmentsJson;
            if (!empty($columns['sent_at'])) $insert['sent_at'] = gmdate('Y-m-d H:i:s');
            if (!empty($columns['created_at'])) $insert['created_at'] = gmdate('Y-m-d H:i:s');

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
