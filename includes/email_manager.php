<?php
/**
 * WhimsicalFrog Email System Management
 * Centralized system functions to eliminate duplication
 * Generated: 2025-07-01 23:30:28
 */

// Include email and database dependencies
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/email_helper.php';


/**
 * Send a test email using a specific template
 */
function sendTestEmail($templateId, $testEmail, $pdo) {
    try {
        // Get template
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ? AND is_active = 1");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$template) {
            throw new Exception("Template not found or inactive");
        }
        
        // Sample variables for testing
        $testVariables = [
            'customer_name' => 'John Doe',
            'customer_email' => $testEmail,
            'order_id' => 'TEST-001',
            'order_date' => date('F j, Y g:i A'),
            'order_total' => '$45.99',
            'items' => '<li>Sample T-Shirt - $25.00</li><li>Custom Tumbler - $20.99</li>',
            'items_text' => "- Sample T-Shirt - $25.00\n- Custom Tumbler - $20.99",
            'shipping_address' => '123 Test Street, Test City, TS 12345',
            'payment_method' => 'Credit Card',
            'shipping_method' => 'Standard Shipping',
            'order_status' => 'Processing',
            'payment_status' => 'Paid',
            'user_name' => 'John Doe',
            'reset_url' => 'https://whimsicalfrog.us/reset-password?token=test',
            'activation_url' => 'https://whimsicalfrog.us/activate?token=test'
        ];
        
        return sendTemplatedEmail($template, $testEmail, $testVariables, 'test_email');
        
    } catch (Exception $e) {
        error_log("Test email error: " . $e->getMessage());
        return false;
    }
}

?>