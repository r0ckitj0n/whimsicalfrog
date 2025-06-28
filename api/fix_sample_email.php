<?php
// Comprehensive script to automatically fix sample email content
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
session_start();

try {
    // Include database configuration
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/config.php';

    // Use centralized authentication with API response mode
    requireAdmin(true);

    // Authentication is handled by requireAdmin() above
    $userData = getCurrentUser();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Configuration error: ' . $e->getMessage()
    ]);
    exit;
}

try {
    // Database connection
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Strategy 1: Update existing basic sample email
    $updateSql = "UPDATE email_logs SET 
        to_email = 'john.doe@example.com',
        from_email = 'orders@whimsicalfrog.us',
        subject = 'Order Confirmation #01F14P23 - WhimsicalFrog',
        content = :content,
        email_type = 'order_confirmation',
        order_id = '01F14P23',
        created_by = 'system'
        WHERE content LIKE '%Email Logging System%' 
        OR content LIKE '%successfully initialized%'
        OR subject LIKE '%Email System%'
        LIMIT 1";
    
    $realisticContent = '
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
    
    $stmt = $pdo->prepare($updateSql);
    $stmt->bindParam(':content', $realisticContent);
    $stmt->execute();
    
    $updatedRows = $stmt->rowCount();
    
    if ($updatedRows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Sample email fixed: Updated existing basic sample email',
            'data' => [
                'strategy_used' => 'Updated existing basic sample email',
                'rows_updated' => $updatedRows
            ]
        ]);
        exit;
    }
    
    // Strategy 2: Create new sample email if no basic one found
    $insertSql = "INSERT INTO email_logs (to_email, from_email, subject, content, email_type, status, sent_at, order_id, created_by) 
                  VALUES ('john.doe@example.com', 'orders@whimsicalfrog.us', 'Order Confirmation #01F14P23 - WhimsicalFrog', :content, 'order_confirmation', 'sent', NOW(), '01F14P23', 'system')";
    
    $stmt = $pdo->prepare($insertSql);
    $stmt->bindParam(':content', $realisticContent);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sample email fixed: Created new sample email',
        'data' => [
            'strategy_used' => 'Created new sample email'
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
?> 