<?php
/**
 * Email Logger Helper
 * Provides functions for logging emails to the database
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';

function getEmailLogColumnsMap()
{
    $map = [];
    $cols = Database::queryAll("SHOW COLUMNS FROM email_logs");
    foreach (($cols ?: []) as $col) {
        $field = trim((string) ($col['Field'] ?? ''));
        if ($field !== '') {
            $map[$field] = true;
        }
    }
    return $map;
}

function safeAddEmailLogColumn($column, $definition)
{
    $columns = getEmailLogColumnsMap();
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

function initializeEmailLogsTable()
{
    try {
        $createTableSQL = "
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
        ";

        Database::execute($createTableSQL);

        $columns = getEmailLogColumnsMap();
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
                safeAddEmailLogColumn($column, $definition);
            }
        }

        Database::execute('ALTER TABLE email_logs MODIFY COLUMN email_type VARCHAR(100) NULL');
        Database::execute("ALTER TABLE email_logs MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'sent'");
        Database::execute('ALTER TABLE email_logs MODIFY COLUMN content LONGTEXT NULL');

        $columns = getEmailLogColumnsMap();
        if (!empty($columns['email_subject']) && !empty($columns['subject'])) {
            Database::execute("UPDATE email_logs SET subject = email_subject WHERE (subject IS NULL OR subject = '') AND email_subject IS NOT NULL AND email_subject != ''");
        }

        return true;
    } catch (Exception $e) {
        error_log("Failed to initialize email_logs table: " . $e->getMessage());
        return false;
    }
}

function logEmail($toEmail, $fromEmail, $subject, $content, $emailType, $status = WF_Constants::EMAIL_STATUS_SENT, $errorMessage = null, $order_id = null, $createdBy = null, $meta = [])
{
    try {
        // Initialize table if needed
        initializeEmailLogsTable();
        $columns = getEmailLogColumnsMap();

        $insert = [];
        if (!empty($columns['to_email'])) {
            $insert['to_email'] = is_array($toEmail) ? implode(', ', $toEmail) : (string) $toEmail;
        }
        if (!empty($columns['from_email'])) $insert['from_email'] = (string) $fromEmail;
        if (!empty($columns['subject'])) {
            $insert['subject'] = (string) $subject;
        } elseif (!empty($columns['email_subject'])) {
            $insert['email_subject'] = (string) $subject;
        }
        if (!empty($columns['content'])) $insert['content'] = (string) $content;
        if (!empty($columns['email_type'])) $insert['email_type'] = (string) $emailType;
        if (!empty($columns['status'])) $insert['status'] = (string) $status;
        if (!empty($columns['error_message'])) $insert['error_message'] = $errorMessage;
        if (!empty($columns['order_id'])) $insert['order_id'] = $order_id;
        if (!empty($columns['created_by'])) $insert['created_by'] = $createdBy;
        if (!empty($columns['cc_email'])) $insert['cc_email'] = is_array($meta['cc'] ?? null) ? implode(', ', $meta['cc']) : (string) ($meta['cc'] ?? '');
        if (!empty($columns['bcc_email'])) $insert['bcc_email'] = is_array($meta['bcc'] ?? null) ? implode(', ', $meta['bcc']) : (string) ($meta['bcc'] ?? '');
        if (!empty($columns['reply_to'])) $insert['reply_to'] = (string) ($meta['reply_to'] ?? '');
        if (!empty($columns['is_html'])) $insert['is_html'] = !empty($meta['is_html']) ? 1 : 0;
        if (!empty($columns['headers_json'])) {
            $json = json_encode($meta['headers'] ?? null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $insert['headers_json'] = ($json === false) ? null : $json;
        }
        if (!empty($columns['attachments_json'])) {
            $json = json_encode($meta['attachments'] ?? null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $insert['attachments_json'] = ($json === false) ? null : $json;
        }

        if (empty($insert)) {
            return false;
        }

        $names = array_keys($insert);
        $sql = 'INSERT INTO email_logs (' . implode(', ', $names) . ') VALUES (' . implode(', ', array_fill(0, count($names), '?')) . ')';
        $result = Database::execute($sql, array_values($insert));

        if ($result) {
            return Database::lastInsertId();
        }

        return false;
    } catch (Exception $e) {
        error_log("Failed to log email: " . $e->getMessage());
        return false;
    }
}

function logOrderConfirmationEmail($toEmail, $fromEmail, $subject, $content, $order_id, $status = WF_Constants::EMAIL_STATUS_SENT, $errorMessage = null)
{
    return logEmail($toEmail, $fromEmail, $subject, $content, 'order_confirmation', $status, $errorMessage, $order_id);
}

function logAdminNotificationEmail($toEmail, $fromEmail, $subject, $content, $order_id = null, $status = WF_Constants::EMAIL_STATUS_SENT, $errorMessage = null, $createdBy = null)
{
    return logEmail($toEmail, $fromEmail, $subject, $content, 'admin_notification', $status, $errorMessage, $order_id, $createdBy);
}

function logTestEmail($toEmail, $fromEmail, $subject, $content, $status = WF_Constants::EMAIL_STATUS_SENT, $errorMessage = null, $createdBy = null)
{
    return logEmail($toEmail, $fromEmail, $subject, $content, 'test_email', $status, $errorMessage, null, $createdBy);
}

function logManualResendEmail($toEmail, $fromEmail, $subject, $content, $status = WF_Constants::EMAIL_STATUS_SENT, $errorMessage = null, $createdBy = null)
{
    return logEmail($toEmail, $fromEmail, $subject, $content, 'manual_resend', $status, $errorMessage, null, $createdBy);
}
