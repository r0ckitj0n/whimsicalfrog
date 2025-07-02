<?php
// Admin Email Settings Management
// This page provides tools for managing email settings and testing email functionality

// Prevent direct access
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

// Include database and email configuration
require_once 'api/config.php';
require_once 'api/email_config.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['test_email'])) {
        // Test email functionality
        $testEmail = $_POST['test_email_address'] ?? '';
        
        if (!empty($testEmail) && filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            // Create a test order data structure
            $testOrderData = [
                'id' => 'TEST' . date('His'),
                'date' => date('Y-m-d H:i:s'),
                'total' => 99.99,
                'paymentMethod' => 'Test Payment',
                'shippingMethod' => 'Test Shipping',
                'status' => 'Test Order',
                'paymentStatus' => 'Test Status'
            ];
            
            $testCustomerData = [
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'username' => 'testuser',
                'email' => $testEmail,
                'phoneNumber' => '555-1234',
                'addressLine1' => '123 Test Street',
                'city' => 'Test City',
                'state' => 'TS',
                'zipCode' => '12345'
            ];
            
            $testOrderItems = [
                [
                    'name' => 'Test Product 1',
                    'quantity' => 2,
                    'price' => 29.99
                ],
                [
                    'name' => 'Test Product 2',
                    'quantity' => 1,
                    'price' => 39.99
                ]
            ];
            
            // Send test emails
            $customerSubject = "TEST: Order Confirmation #{$testOrderData['id']} - WhimsicalFrog";
            $customerHtml = generateCustomerConfirmationEmail($testOrderData, $testCustomerData, $testOrderItems);
            
            $adminSubject = "TEST: New Order #{$testOrderData['id']} - WhimsicalFrog";
            $adminHtml = generateAdminNotificationEmail($testOrderData, $testCustomerData, $testOrderItems);
            
            $customerResult = sendEmail($testEmail, $customerSubject, $customerHtml);
            $adminResult = false;
            
            if (defined('ADMIN_EMAIL') && ADMIN_EMAIL) {
                $adminResult = sendEmail(ADMIN_EMAIL, $adminSubject, $adminHtml);
            }
            
            if ($customerResult) {
                $message = "Test emails sent successfully! Check your inbox at $testEmail";
                if ($adminResult) {
                    $message .= " and admin inbox at " . ADMIN_EMAIL;
                }
                $messageType = 'success';
            } else {
                $message = "Failed to send test email. Please check your email configuration.";
                $messageType = 'error';
            }
        } else {
            $message = "Please enter a valid email address for testing.";
            $messageType = 'error';
        }
    }
}

?>

<div class="container mx-auto px-4 py-6">
    <h1 class="admin-title mb-6">Email Settings</h1>
    
    <?php if ($message): ?>
        <div class="admin-alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Current Configuration -->
    <div class="admin-card">
        <h2 class="admin-card-title">📧 Current Email Configuration</h2>
        
        <div class="config-grid">
            <div class="config-item">
                <div class="config-label">Email System Status:</div>
                <div class="config-value">
                    <span class="status-indicator <?= SMTP_ENABLED ? 'status-active' : 'status-inactive' ?>"></span>
                    <?= SMTP_ENABLED ? 'SMTP Enabled' : 'PHP mail() Function' ?>
                </div>
            </div>
            
            <div class="config-item">
                <div class="config-label">From Email:</div>
                <div class="config-value"><?= htmlspecialchars(FROM_EMAIL) ?></div>
            </div>
            
            <div class="config-item">
                <div class="config-label">From Name:</div>
                <div class="config-value"><?= htmlspecialchars(FROM_NAME) ?></div>
            </div>
            
            <div class="config-item">
                <div class="config-label">Admin Notification Email:</div>
                <div class="config-value"><?= htmlspecialchars(ADMIN_EMAIL) ?></div>
            </div>
            
            <?php if (defined('BCC_EMAIL') && BCC_EMAIL): ?>
            <div class="config-item">
                <div class="config-label">BCC Email:</div>
                <div class="config-value"><?= htmlspecialchars(BCC_EMAIL) ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (SMTP_ENABLED): ?>
            <div class="config-item">
                <div class="config-label">SMTP Host:</div>
                <div class="config-value"><?= htmlspecialchars(SMTP_HOST) ?></div>
            </div>
            
            <div class="config-item">
                <div class="config-label">SMTP Port:</div>
                <div class="config-value"><?= htmlspecialchars(SMTP_PORT) ?></div>
            </div>
            
            <div class="config-item">
                <div class="config-label">SMTP Encryption:</div>
                <div class="config-value"><?= htmlspecialchars(SMTP_ENCRYPTION) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Configuration Instructions -->
    <div class="admin-card">
        <h2 class="admin-card-title">⚙️ Configuration Instructions</h2>
        
        <div class="admin-alert alert-warning">
            <strong>Note:</strong> Email settings are configured in <code class="code-badge">api/email_config.php</code>. 
            To modify these settings, edit that file directly.
        </div>
        
        <div class="config-instructions">
            <div class="instruction-section">
                <h3 class="instruction-title">📝 Basic Setup Steps:</h3>
                <ol class="instruction-list ordered">
                    <li>Edit <code class="code-badge">api/email_config.php</code> to configure your email settings</li>
                    <li>Update <code class="code-badge">FROM_EMAIL</code> to your business email address</li>
                    <li>Set <code class="code-badge">ADMIN_EMAIL</code> to where you want order notifications sent</li>
                    <li>If using SMTP, enable it and configure SMTP settings</li>
                    <li>Test the configuration using the form below</li>
                </ol>
            </div>
            
            <div class="instruction-section">
                <h3 class="instruction-title">📬 For IONOS Hosting:</h3>
                <ul class="instruction-list">
                    <li>Use your domain email address (e.g., orders@whimsicalfrog.us)</li>
                    <li>SMTP Host: Usually <code class="code-badge">smtp.ionos.com</code> or similar</li>
                    <li>Port: 587 (TLS) or 465 (SSL)</li>
                    <li>Authentication: Use your email credentials</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Email Test Section -->
    <div class="admin-test-card">
        <h2 class="test-card-title">🧪 Test Email Functionality</h2>
        
        <p class="test-description">
            Send test emails to verify your configuration is working properly. This will send both 
            customer confirmation and admin notification emails using sample order data.
        </p>
        
        <form method="POST" class="test-form">
            <div class="test-form-group">
                <label for="test_email_address" class="form-label">Test Email Address:</label>
                <div class="test-form-input-group">
                    <input type="email" 
                           id="test_email_address" 
                           name="test_email_address" 
                           placeholder="Enter email address to receive test emails"
                           class="form-input flex-1"
                           required>
                    <button type="submit" name="test_email" value="1" class="btn-primary">
                        Send Test Emails
                    </button>
                </div>
            </div>
        </form>
        
        <div class="test-info">
            <strong>What will be sent:</strong>
            <ul class="test-info-list">
                <li>Customer order confirmation email to the address you enter above</li>
                <li>Admin order notification email to <?= htmlspecialchars(ADMIN_EMAIL) ?> (if configured)</li>
            </ul>
        </div>
    </div>

    <!-- Email Templates Preview -->
    <div class="admin-card">
        <h2 class="admin-card-title">📋 Email Templates</h2>
        
        <div class="template-grid">
            <div class="template-card">
                <h3 class="template-title">📧 Customer Confirmation Email</h3>
                <ul class="template-features">
                    <li>• Personalized thank you message</li>
                    <li>• Complete order details and items</li>
                    <li>• Payment and shipping information</li>
                    <li>• Branded design with WhimsicalFrog colors</li>
                    <li>• Link to view order details online</li>
                </ul>
            </div>
            
            <div class="template-card">
                <h3 class="template-title">🚨 Admin Notification Email</h3>
                <ul class="template-features">
                    <li>• Immediate new order alert</li>
                    <li>• Customer contact information</li>
                    <li>• Order details and status</li>
                    <li>• Quick action items checklist</li>
                    <li>• Direct links to admin panel</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Troubleshooting Section -->
    <div class="admin-card">
        <h2 class="admin-card-title">🔧 Troubleshooting</h2>
        
        <div class="troubleshooting-section">
            <div class="troubleshooting-group">
                <h3 class="troubleshooting-title">Common Issues:</h3>
                <ul class="troubleshooting-list">
                    <li><strong>Emails not sending:</strong> Check your hosting provider's email settings and make sure the domain is verified</li>
                    <li><strong>Emails going to spam:</strong> Use a domain email address and consider setting up SPF/DKIM records</li>
                    <li><strong>SMTP authentication errors:</strong> Verify your email credentials and server settings</li>
                    <li><strong>Missing customer emails:</strong> Ensure customer email addresses are properly saved during registration</li>
                </ul>
            </div>
            
            <div class="troubleshooting-group">
                <h3 class="troubleshooting-title">📊 Email Logs:</h3>
                <p class="troubleshooting-text">
                    Email sending results are logged to the server error log. Check your hosting control panel 
                    or contact your hosting provider to access email delivery logs.
                </p>
            </div>
        </div>
    </div>
</div> 