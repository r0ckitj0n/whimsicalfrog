<?php
/**
 * Email Logger Helper
 * Provides functions for logging emails to the database
 */

require_once 'config.php';

function initializeEmailLogsTable() {
    global $dsn, $user, $pass, $options;
    
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        
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
        
        $pdo->exec($createTableSQL);
        return true;
    } catch (Exception $e) {
        error_log("Failed to initialize email_logs table: " . $e->getMessage());
        return false;
    }
}

function logEmail($toEmail, $fromEmail, $subject, $content, $emailType, $status = 'sent', $errorMessage = null, $orderId = null, $createdBy = null) {
    global $dsn, $user, $pass, $options;
    
    try {
        // Initialize table if needed
        initializeEmailLogsTable();
        
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        $sql = "INSERT INTO email_logs (to_email, from_email, subject, content, email_type, status, error_message, order_id, created_by) 
                VALUES (:to_email, :from_email, :subject, :content, :email_type, :status, :error_message, :order_id, :created_by)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':to_email' => $toEmail,
            ':from_email' => $fromEmail,
            ':subject' => $subject,
            ':content' => $content,
            ':email_type' => $emailType,
            ':status' => $status,
            ':error_message' => $errorMessage,
            ':order_id' => $orderId,
            ':created_by' => $createdBy
        ]);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Failed to log email: " . $e->getMessage());
        return false;
    }
}

function logOrderConfirmationEmail($toEmail, $fromEmail, $subject, $content, $orderId, $status = 'sent', $errorMessage = null) {
    return logEmail($toEmail, $fromEmail, $subject, $content, 'order_confirmation', $status, $errorMessage, $orderId);
}

function logAdminNotificationEmail($toEmail, $fromEmail, $subject, $content, $orderId = null, $status = 'sent', $errorMessage = null, $createdBy = null) {
    return logEmail($toEmail, $fromEmail, $subject, $content, 'admin_notification', $status, $errorMessage, $orderId, $createdBy);
}

function logTestEmail($toEmail, $fromEmail, $subject, $content, $status = 'sent', $errorMessage = null, $createdBy = null) {
    return logEmail($toEmail, $fromEmail, $subject, $content, 'test_email', $status, $errorMessage, null, $createdBy);
}

function logManualResendEmail($toEmail, $fromEmail, $subject, $content, $status = 'sent', $errorMessage = null, $createdBy = null) {
    return logEmail($toEmail, $fromEmail, $subject, $content, 'manual_resend', $status, $errorMessage, null, $createdBy);
}
?> 