<?php
// Prevent any output before JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'email_config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
session_start();
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role']) || 
    ($_SESSION['user']['role'] !== 'Admin' && $_SESSION['user']['role'] !== 'admin')) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Create email_logs table if it doesn't exist
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
    
    // Get form data
    $originalEmailId = intval($_POST['originalEmailId'] ?? 0);
    $emailTo = trim($_POST['emailTo'] ?? '');
    $emailSubject = trim($_POST['emailSubject'] ?? '');
    $emailContent = trim($_POST['emailContent'] ?? '');
    
    // Validate input
    if (empty($emailTo) || empty($emailSubject) || empty($emailContent)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required']);
        exit;
    }
    
    if (!filter_var($emailTo, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit;
    }
    
    // Send email using the email configuration
    $success = false;
    $errorMessage = '';
    
    try {
        if (SMTP_ENABLED) {
            // Use SMTP
            require_once '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
            require_once '../vendor/phpmailer/phpmailer/src/SMTP.php';
            require_once '../vendor/phpmailer/phpmailer/src/Exception.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port = SMTP_PORT;
            
            $mail->setFrom(FROM_EMAIL, FROM_NAME);
            $mail->addAddress($emailTo);
            
            if (!empty(BCC_EMAIL)) {
                $mail->addBCC(BCC_EMAIL);
            }
            
            $mail->isHTML(true);
            $mail->Subject = $emailSubject;
            $mail->Body = $emailContent;
            
            $mail->send();
            $success = true;
        } else {
            // Use PHP mail()
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>' . "\r\n";
            
            if (!empty(BCC_EMAIL)) {
                $headers .= 'Bcc: ' . BCC_EMAIL . "\r\n";
            }
            
            $success = mail($emailTo, $emailSubject, $emailContent, $headers);
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        error_log("Email send error: " . $errorMessage);
    }
    
    // Log the email
    $logSQL = "INSERT INTO email_logs (to_email, from_email, subject, content, email_type, status, error_message, created_by) 
               VALUES (:to_email, :from_email, :subject, :content, :email_type, :status, :error_message, :created_by)";
    
    $logStmt = $pdo->prepare($logSQL);
    $logStmt->execute([
        ':to_email' => $emailTo,
        ':from_email' => FROM_EMAIL,
        ':subject' => $emailSubject,
        ':content' => $emailContent,
        ':email_type' => 'manual_resend',
        ':status' => $success ? 'sent' : 'failed',
        ':error_message' => $success ? null : $errorMessage,
        ':created_by' => $_SESSION['user']['userId'] ?? $_SESSION['user']['username'] ?? 'admin'
    ]);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Email sent successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send email: ' . $errorMessage
        ]);
    }
    
} catch (Exception $e) {
    error_log("Resend email error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Failed to resend email: ' . $e->getMessage()
    ]);
}
?> 