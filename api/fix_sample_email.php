<?php
// Comprehensive script to automatically fix sample email content
ob_start();
ob_clean();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Start session to verify admin access
session_start();

// Check if user is admin - handle different session structures
$isAdmin = false;
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
    $isAdmin = true;
} elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $isAdmin = true;
} elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $isAdmin = true;
}

if (!$isAdmin) {
    ob_clean();
    http_response_code(403);
    echo json_encode([
        'success' => false, 
        'error' => 'Access denied - admin authentication required',
        'debug' => [
            'session_keys' => array_keys($_SESSION ?? []),
            'user_data' => isset($_SESSION['user']) ? array_keys($_SESSION['user']) : 'no user key'
        ]
    ]);
    exit;
}

// Include database configuration
require_once __DIR__ . '/config.php';

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Realistic sample email content
    $sampleEmailContent = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #87ac3a; margin: 0;">WhimsicalFrog</h1>
            <p style="color: #666; margin: 5px 0;">Custom Products & Design</p>
        </div>
        
        <h2 style="color: #333;">Order Confirmation #01F14P23</h2>
        
        <p>Dear John Doe,</p>
        
        <p>Thank you for your order! We have received your order and it is being processed.</p>
        
        <h3 style="color: #87ac3a;">Order Details:</h3>
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr style="background-color: #f5f5f5;">
                <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Item</th>
                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Quantity</th>
                <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Price</th>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;">Custom T-Shirt Design</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">2</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">$25.00</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;">Custom Tumbler</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">1</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">$15.00</td>
            </tr>
            <tr style="background-color: #f5f5f5; font-weight: bold;">
                <td style="padding: 10px; border: 1px solid #ddd;" colspan="2">Total:</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">$40.00</td>
            </tr>
        </table>
        
        <h3 style="color: #87ac3a;">Shipping Information:</h3>
        <p>John Doe<br>
        123 Main Street<br>
        Anytown, ST 12345</p>
        
        <p><strong>Delivery Method:</strong> Pickup</p>
        <p><strong>Expected Completion:</strong> 3-5 business days</p>
        
        <p>We will notify you when your order is ready for pickup!</p>
        
        <p>If you have any questions, please contact us at orders@whimsicalfrog.us</p>
        
        <p>Thank you for choosing WhimsicalFrog!</p>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;">
            <p>This is an automated email. Please do not reply to this email address.</p>
        </div>
    </div>';
    
    $result = ['success' => false, 'message' => '', 'debug' => []];
    
    // Get current emails for debugging
    $checkSQL = "SELECT id, subject, created_by, email_type, content FROM email_logs ORDER BY sent_at DESC LIMIT 5";
    $stmt = $pdo->query($checkSQL);
    $existingEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result['debug']['total_emails'] = count($existingEmails);
    $result['debug']['existing_emails'] = [];
    
    foreach ($existingEmails as $email) {
        $result['debug']['existing_emails'][] = [
            'id' => $email['id'],
            'subject' => $email['subject'],
            'type' => $email['email_type'],
            'created_by' => $email['created_by'] ?? 'NULL',
            'content_preview' => substr(strip_tags($email['content']), 0, 100) . '...'
        ];
    }
    
    // Try multiple update strategies
    $updated = false;
    $updateMessage = "";
    
    // Strategy 1: Update by subject patterns (most specific first)
    $patterns = [
        '%Email System Initialized%',
        '%Email Logging System%', 
        '%initialized%',
        '%system%'
    ];
    
    foreach ($patterns as $pattern) {
        if ($updated) break;
        
        $updateSQL = "
        UPDATE email_logs 
        SET 
            to_email = 'john.doe@example.com',
            subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
            content = :sample_content,
            email_type = 'order_confirmation',
            order_id = '01F14P23'
        WHERE subject LIKE :pattern
        LIMIT 1";
        
        $stmt = $pdo->prepare($updateSQL);
        $updateResult = $stmt->execute([
            ':sample_content' => $sampleEmailContent,
            ':pattern' => $pattern
        ]);
        
        if ($updateResult && $stmt->rowCount() > 0) {
            $updated = true;
            $updateMessage = "Updated email by subject pattern: $pattern";
            break;
        }
    }
    
    // Strategy 2: Update by created_by = 'system'
    if (!$updated) {
        $updateSQL = "
        UPDATE email_logs 
        SET 
            to_email = 'john.doe@example.com',
            subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
            content = :sample_content,
            email_type = 'order_confirmation',
            order_id = '01F14P23'
        WHERE created_by = 'system'
        LIMIT 1";
        
        $stmt = $pdo->prepare($updateSQL);
        $updateResult = $stmt->execute([':sample_content' => $sampleEmailContent]);
        
        if ($updateResult && $stmt->rowCount() > 0) {
            $updated = true;
            $updateMessage = "Updated email by created_by = 'system'";
        }
    }
    
    // Strategy 3: Update the first test_email
    if (!$updated && !empty($existingEmails)) {
        foreach ($existingEmails as $email) {
            if ($email['email_type'] === 'test_email') {
                $updateSQL = "
                UPDATE email_logs 
                SET 
                    to_email = 'john.doe@example.com',
                    subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
                    content = :sample_content,
                    email_type = 'order_confirmation',
                    order_id = '01F14P23'
                WHERE id = :email_id";
                
                $stmt = $pdo->prepare($updateSQL);
                $updateResult = $stmt->execute([
                    ':sample_content' => $sampleEmailContent,
                    ':email_id' => $email['id']
                ]);
                
                if ($updateResult && $stmt->rowCount() > 0) {
                    $updated = true;
                    $updateMessage = "Updated test_email with ID: " . $email['id'];
                    break;
                }
            }
        }
    }
    
    // Strategy 4: Update the most recent email (if it looks like a sample/test)
    if (!$updated && !empty($existingEmails)) {
        $firstEmail = $existingEmails[0];
        $content = strtolower($firstEmail['content']);
        
        // Check if it looks like a sample/test email
        if (strpos($content, 'email') !== false && 
            (strpos($content, 'system') !== false || 
             strpos($content, 'initialized') !== false || 
             strpos($content, 'test') !== false ||
             strlen(strip_tags($firstEmail['content'])) < 200)) {
            
            $updateSQL = "
            UPDATE email_logs 
            SET 
                to_email = 'john.doe@example.com',
                subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
                content = :sample_content,
                email_type = 'order_confirmation',
                order_id = '01F14P23'
            WHERE id = :email_id";
            
            $stmt = $pdo->prepare($updateSQL);
            $updateResult = $stmt->execute([
                ':sample_content' => $sampleEmailContent,
                ':email_id' => $firstEmail['id']
            ]);
            
            if ($updateResult && $stmt->rowCount() > 0) {
                $updated = true;
                $updateMessage = "Updated most recent email (appeared to be sample/test)";
            }
        }
    }
    
    // Strategy 5: Create a new sample email if nothing worked
    if (!$updated) {
        $insertSQL = "
        INSERT INTO email_logs (to_email, from_email, subject, content, email_type, status, sent_at, order_id, created_by) 
        VALUES 
        ('john.doe@example.com', 'orders@whimsicalfrog.us', 'Order Confirmation #01F14P23 - WhimsicalFrog', 
         :sample_content, 'order_confirmation', 'sent', NOW(), '01F14P23', 'system')";
        
        $stmt = $pdo->prepare($insertSQL);
        $insertResult = $stmt->execute([':sample_content' => $sampleEmailContent]);
        
        if ($insertResult) {
            $updated = true;
            $updateMessage = "Created new sample email (no existing email could be updated)";
        }
    }
    
    if ($updated) {
        $result['success'] = true;
        $result['message'] = "Sample email fixed successfully! " . $updateMessage;
    } else {
        $result['success'] = false;
        $result['message'] = "Failed to fix sample email. Please check the debug information.";
    }
    
    // Clean output buffer and return JSON
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'error' => 'Database error: ' . $e->getMessage(),
        'debug' => ['exception' => $e->getMessage()]
    ]);
}
?> 