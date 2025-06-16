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

<style>
    .email-title.text-2xl.font-bold {
        color: #87ac3a !important;
    }
    
    .brand-button {
        background-color: #87ac3a !important;
        color: white !important;
        transition: background-color 0.3s ease;
    }
    
    .brand-button:hover {
        background-color: #6b8e23 !important;
    }
    
    .config-section {
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .config-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .config-item:last-child {
        border-bottom: none;
    }
    
    .config-label {
        font-weight: 600;
        color: #374151;
        flex: 1;
    }
    
    .config-value {
        font-family: monospace;
        background-color: #f9fafb;
        padding: 4px 8px;
        border-radius: 4px;
        flex: 2;
        margin-left: 20px;
        word-break: break-all;
    }
    
    .status-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 8px;
    }
    
    .status-active {
        background-color: #10b981;
    }
    
    .status-inactive {
        background-color: #ef4444;
    }
    
    .test-section {
        background-color: #f0f9ff;
        border: 1px solid #0ea5e9;
        border-radius: 8px;
        padding: 20px;
        margin-top: 20px;
    }
    
    .alert {
        padding: 12px 16px;
        border-radius: 6px;
        margin-bottom: 20px;
    }
    
    .alert-success {
        background-color: #d1fae5;
        border: 1px solid #10b981;
        color: #065f46;
    }
    
    .alert-error {
        background-color: #fee2e2;
        border: 1px solid #ef4444;
        color: #991b1b;
    }
    
    .alert-warning {
        background-color: #fef3c7;
        border: 1px solid #f59e0b;
        color: #92400e;
    }
</style>

<div class="container mx-auto px-4 py-6">
    <h1 class="email-title text-2xl font-bold mb-6">Email Settings</h1>
    
    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Current Configuration Section -->
    <div class="config-section">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">📧 Current Email Configuration</h2>
        
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

    <!-- Configuration Instructions -->
    <div class="config-section">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">⚙️ Configuration Instructions</h2>
        
        <div class="alert alert-warning">
            <strong>Note:</strong> Email settings are configured in <code>api/email_config.php</code>. 
            To modify these settings, edit that file directly.
        </div>
        
        <div class="space-y-4">
            <div>
                <h3 class="font-medium text-gray-700 mb-2">📝 Basic Setup Steps:</h3>
                <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600">
                    <li>Edit <code>api/email_config.php</code> to configure your email settings</li>
                    <li>Update <code>FROM_EMAIL</code> to your business email address</li>
                    <li>Set <code>ADMIN_EMAIL</code> to where you want order notifications sent</li>
                    <li>If using SMTP, enable it and configure SMTP settings</li>
                    <li>Test the configuration using the form below</li>
                </ol>
            </div>
            
            <div>
                <h3 class="font-medium text-gray-700 mb-2">📬 For IONOS Hosting:</h3>
                <ul class="list-disc list-inside space-y-1 text-sm text-gray-600">
                    <li>Use your domain email address (e.g., orders@whimsicalfrog.us)</li>
                    <li>SMTP Host: Usually <code>smtp.ionos.com</code> or similar</li>
                    <li>Port: 587 (TLS) or 465 (SSL)</li>
                    <li>Authentication: Use your email credentials</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Email Test Section -->
    <div class="test-section">
        <h2 class="text-lg font-semibold text-blue-800 mb-4">🧪 Test Email Functionality</h2>
        
        <p class="text-sm text-blue-700 mb-4">
            Send test emails to verify your configuration is working properly. This will send both 
            customer confirmation and admin notification emails using sample order data.
        </p>
        
        <form method="POST" class="space-y-4">
            <div>
                <label for="test_email_address" class="block text-sm font-medium text-gray-700 mb-2">
                    Test Email Address:
                </label>
                <div class="flex gap-3">
                    <input type="email" 
                           id="test_email_address" 
                           name="test_email_address" 
                           placeholder="Enter email address to receive test emails"
                           class="flex-1 p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           required>
                    <button type="submit" 
                            name="test_email" 
                            value="1"
                            class="brand-button px-6 py-3 rounded-lg font-medium">
                        Send Test Emails
                    </button>
                </div>
            </div>
        </form>
        
        <div class="mt-4 text-xs text-blue-600">
            <strong>What will be sent:</strong>
            <ul class="list-disc list-inside mt-1 space-y-1">
                <li>Customer order confirmation email to the address you enter above</li>
                <li>Admin order notification email to <?= htmlspecialchars(ADMIN_EMAIL) ?> (if configured)</li>
            </ul>
        </div>
    </div>

    <!-- Email Templates Preview -->
    <div class="config-section">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">📋 Email Templates</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="font-medium text-gray-700 mb-2">📧 Customer Confirmation Email</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>• Personalized thank you message</li>
                    <li>• Complete order details and items</li>
                    <li>• Payment and shipping information</li>
                    <li>• Branded design with WhimsicalFrog colors</li>
                    <li>• Link to view order details online</li>
                </ul>
            </div>
            
            <div class="border border-gray-200 rounded-lg p-4">
                <h3 class="font-medium text-gray-700 mb-2">🚨 Admin Notification Email</h3>
                <ul class="text-sm text-gray-600 space-y-1">
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
    <div class="config-section">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">🔧 Troubleshooting</h2>
        
        <div class="space-y-4">
            <div>
                <h3 class="font-medium text-red-600 mb-2">Common Issues:</h3>
                <ul class="list-disc list-inside space-y-2 text-sm text-gray-600">
                    <li><strong>Emails not sending:</strong> Check your hosting provider's email settings and make sure the domain is verified</li>
                    <li><strong>Emails going to spam:</strong> Use a domain email address and consider setting up SPF/DKIM records</li>
                    <li><strong>SMTP authentication errors:</strong> Verify your email credentials and server settings</li>
                    <li><strong>Missing customer emails:</strong> Ensure customer email addresses are properly saved during registration</li>
                </ul>
            </div>
            
            <div>
                <h3 class="font-medium text-gray-700 mb-2">📊 Email Logs:</h3>
                <p class="text-sm text-gray-600">
                    Email sending results are logged to the server error log. Check your hosting control panel 
                    or contact your hosting provider to access email delivery logs.
                </p>
            </div>
        </div>
    </div>
</div> 