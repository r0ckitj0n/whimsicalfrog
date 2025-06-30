<?php
require_once __DIR__ . '/config.php';

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
    // Create email_templates table
    $createEmailTemplatesTable = "
        CREATE TABLE IF NOT EXISTS email_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_name VARCHAR(255) NOT NULL,
            template_type ENUM('order_confirmation', 'admin_notification', 'welcome', 'password_reset', 'custom') NOT NULL,
            subject VARCHAR(500) NOT NULL,
            html_content LONGTEXT NOT NULL,
            text_content LONGTEXT,
            description TEXT,
            variables JSON,
            is_active BOOLEAN DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_template_type (template_type),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    // Create email_template_assignments table
    $createAssignmentsTable = "
        CREATE TABLE IF NOT EXISTS email_template_assignments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email_type VARCHAR(100) NOT NULL UNIQUE,
            template_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE CASCADE,
            INDEX idx_email_type (email_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    echo "Creating email templates tables...\n";
    $pdo->exec($createEmailTemplatesTable);
    $pdo->exec($createAssignmentsTable);
    echo "Email templates tables created successfully!\n";
    
    // Check if we need to insert default templates
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM email_templates");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        echo "Inserting default email templates...\n";
        
        // Default Order Confirmation Template
        $orderConfirmationHtml = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Order Confirmation - WhimsicalFrog</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #87ac3a; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
        <h1 style="margin: 0; font-size: 28px;">WhimsicalFrog</h1>
        <p style="margin: 5px 0 0 0; font-size: 16px;">Order Confirmation</p>
    </div>
    
    <div style="background-color: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #ddd;">
        <h2 style="color: #87ac3a; margin-top: 0;">Thank you for your order, {customer_name}!</h2>
        
        <p>We\'ve received your order and we\'re getting it ready for you. Here are the details:</p>
        
        <div style="background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e0e0e0;">
            <h3 style="color: #87ac3a; margin-top: 0;">Order Information</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Order Number:</td>
                    <td style="padding: 8px 0;">{order_id}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Order Date:</td>
                    <td style="padding: 8px 0;">{order_date}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: bold;">Order Total:</td>
                    <td style="padding: 8px 0; font-weight: bold; color: #87ac3a;">{order_total}</td>
                </tr>
            </table>
        </div>
        
        <div style="background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e0e0e0;">
            <h3 style="color: #87ac3a; margin-top: 0;">Items Ordered</h3>
            <ul style="list-style: none; padding: 0;">
                {items}
            </ul>
        </div>
        
        <div style="background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e0e0e0;">
            <h3 style="color: #87ac3a; margin-top: 0;">Shipping Address</h3>
            <p style="margin: 0;">{shipping_address}</p>
        </div>
        
        <div style="background-color: #e8f4e8; padding: 20px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #87ac3a;">
            <h3 style="color: #87ac3a; margin-top: 0;">What\'s Next?</h3>
            <p style="margin-bottom: 10px;">• We\'ll send you another email when your order ships</p>
            <p style="margin-bottom: 10px;">• You can track your order status anytime by logging into your account</p>
            <p style="margin-bottom: 0;">• Questions? Contact us at orders@whimsicalfrog.us</p>
        </div>
        
        <div style="text-align: center; margin: 30px 0; padding: 20px; background-color: white; border-radius: 6px; border: 1px solid #e0e0e0;">
            <a href="https://whimsicalfrog.us/?page=receipt&orderId={order_id}" 
               style="display: inline-block; background-color: #87ac3a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                View Order Details
            </a>
        </div>
        
        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;">
            <p style="margin: 0; font-size: 14px;">Thank you for shopping with WhimsicalFrog!</p>
            <p style="margin: 5px 0 0 0; font-size: 12px;">© ' . date('Y') . ' WhimsicalFrog. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
        
        // Default Admin Notification Template
        $adminNotificationHtml = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Order Alert - WhimsicalFrog</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #d32f2f; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
        <h1 style="margin: 0; font-size: 28px;">🎉 NEW ORDER ALERT!</h1>
        <p style="margin: 5px 0 0 0; font-size: 16px;">WhimsicalFrog Admin Notification</p>
    </div>
    
    <div style="background-color: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; border: 1px solid #ddd;">
        <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <strong>⏰ Action Required:</strong> A new order has been placed and requires your attention!
        </div>
        
        <h2 style="color: #d32f2f; margin-top: 0;">Order #{order_id}</h2>
        <p style="font-size: 16px; margin-bottom: 20px;">
            <strong>Placed:</strong> {order_date} | 
            <strong>Total:</strong> {order_total}
        </p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
            <div style="background-color: white; padding: 20px; border-radius: 6px; border: 1px solid #e0e0e0;">
                <h3 style="color: #87ac3a; margin-top: 0;">Customer Information</h3>
                <table style="width: 100%;">
                    <tr><td style="font-weight: bold; padding: 4px 0;">Name:</td><td style="padding: 4px 0;">{customer_name}</td></tr>
                    <tr><td style="font-weight: bold; padding: 4px 0;">Email:</td><td style="padding: 4px 0;">{customer_email}</td></tr>
                </table>
            </div>
            
            <div style="background-color: white; padding: 20px; border-radius: 6px; border: 1px solid #e0e0e0;">
                <h3 style="color: #87ac3a; margin-top: 0;">Quick Actions</h3>
                <ul style="margin: 0; padding-left: 20px; color: #666;">
                    <li>Review order details</li>
                    <li>Check inventory levels</li>
                    <li>Start production process</li>
                    <li>Send confirmation to customer</li>
                </ul>
            </div>
        </div>
        
        <div style="background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #e0e0e0;">
            <h3 style="color: #87ac3a; margin-top: 0;">Ordered Items</h3>
            <ul style="list-style: none; padding: 0;">
                {items}
            </ul>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="https://whimsicalfrog.us/?page=admin_orders" 
               style="display: inline-block; background-color: #87ac3a; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-right: 10px;">
                View in Admin Panel
            </a>
            <a href="https://whimsicalfrog.us/?page=order_fulfillment" 
               style="display: inline-block; background-color: #d32f2f; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
                Start Fulfillment
            </a>
        </div>
        
        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666;">
            <p style="margin: 0; font-size: 14px;">WhimsicalFrog Admin System</p>
            <p style="margin: 5px 0 0 0; font-size: 12px;">This is an automated notification. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>';
        
        // Insert default templates
        $templates = [
            [
                'template_name' => 'Default Order Confirmation',
                'template_type' => 'order_confirmation',
                'subject' => 'Order Confirmation #{order_id} - Thank you for your order!',
                'html_content' => $orderConfirmationHtml,
                'text_content' => 'Thank you for your order {customer_name}! Your order #{order_id} has been received and is being processed. Order total: {order_total}. We\'ll send you another email when your order ships.',
                'description' => 'Default template for customer order confirmations',
                'variables' => json_encode(['customer_name', 'order_id', 'order_date', 'order_total', 'items', 'shipping_address'])
            ],
            [
                'template_name' => 'Default Admin Notification',
                'template_type' => 'admin_notification',
                'subject' => '🚨 New Order Alert #{order_id} - Action Required',
                'html_content' => $adminNotificationHtml,
                'text_content' => 'NEW ORDER ALERT! Order #{order_id} has been placed by {customer_name} ({customer_email}). Order total: {order_total}. Please review and process this order.',
                'description' => 'Default template for admin order notifications',
                'variables' => json_encode(['customer_name', 'customer_email', 'order_id', 'order_date', 'order_total', 'items'])
            ]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO email_templates 
            (template_name, template_type, subject, html_content, text_content, description, variables, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        foreach ($templates as $template) {
            $stmt->execute([
                $template['template_name'],
                $template['template_type'],
                $template['subject'],
                $template['html_content'],
                $template['text_content'],
                $template['description'],
                $template['variables']
            ]);
            echo "Created template: " . $template['template_name'] . "\n";
        }
        
        // Create default assignments
        echo "Creating default template assignments...\n";
        
        $orderConfirmationId = $pdo->lastInsertId() - 1; // Get first template ID
        $adminNotificationId = $pdo->lastInsertId(); // Get second template ID
        
        $assignments = [
            ['email_type' => 'order_confirmation', 'template_id' => $orderConfirmationId],
            ['email_type' => 'admin_notification', 'template_id' => $adminNotificationId]
        ];
        
        $assignmentStmt = $pdo->prepare("
            INSERT INTO email_template_assignments (email_type, template_id, created_at) 
            VALUES (?, ?, NOW())
        ");
        
        foreach ($assignments as $assignment) {
            $assignmentStmt->execute([$assignment['email_type'], $assignment['template_id']]);
            echo "Created assignment: " . $assignment['email_type'] . " -> Template ID " . $assignment['template_id'] . "\n";
        }
        
        echo "Default email templates and assignments created successfully!\n";
    } else {
        echo "Email templates already exist. Skipping default data insertion.\n";
    }
    
    // Create email_logs table for tracking email sends
    echo "Creating email logs table...\n";
    $createEmailLogsTable = "
        CREATE TABLE IF NOT EXISTS email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            to_email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            email_type VARCHAR(100),
            template_id INT,
            status ENUM('sent', 'failed') NOT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            error_message TEXT,
            order_id VARCHAR(50),
            INDEX idx_email_type (email_type),
            INDEX idx_sent_at (sent_at),
            INDEX idx_order_id (order_id),
            FOREIGN KEY (template_id) REFERENCES email_templates(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    $pdo->exec($createEmailLogsTable);
    echo "Email logs table created successfully!\n";
    
    echo "Email templates system initialization complete!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 