<?php
/**
 * Email Logger Helper
 * Provides functions for logging emails to the database
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/Constants.php';

function initializeEmailLogsTable()
{
    global $dsn, $user, $pass, $options;

    try {
        try {
            $pdo = Database::getInstance();
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }

        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                from_email VARCHAR(255) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                content TEXT NOT NULL,
                email_type ENUM('order_confirmation', 'admin_notification', 'test_email', 'manual_resend') NOT NULL,
                status ENUM('sent', 'failed') NOT NULL DEFAULT 'sent',
                error_message TEXT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                order_id VARCHAR(50) NULL,
                created_by VARCHAR(50) NULL,
                INDEX idx_sent_at (sent_at),
                INDEX idx_email_type (email_type),
                INDEX idx_status (status),
                INDEX idx_to_email (to_email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        Database::execute($createTableSQL);
        return true;
    } catch (Exception $e) {
        error_log("Failed to initialize email_logs table: " . $e->getMessage());
        return false;
    }
}

function logEmail($toEmail, $fromEmail, $subject, $content, $emailType, $status = WF_Constants::EMAIL_STATUS_SENT, $errorMessage = null, $order_id = null, $createdBy = null)
{
    global $dsn, $user, $pass, $options;

    try {
        // Initialize table if needed
        initializeEmailLogsTable();

        try {
            $pdo = Database::getInstance();
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }

        $sql = "INSERT INTO email_logs (to_email, from_email, subject, content, email_type, status, error_message, order_id, created_by) 
                VALUES (:to_email, :from_email, :subject, :content, :email_type, :status, :error_message, :order_id, :created_by)";

        $result = Database::execute($sql, [
            ':to_email' => $toEmail,
            ':from_email' => $fromEmail,
            ':subject' => $subject,
            ':content' => $content,
            ':email_type' => $emailType,
            ':status' => $status,
            ':error_message' => $errorMessage,
            ':order_id' => $order_id,
            ':created_by' => $createdBy
        ]);

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
