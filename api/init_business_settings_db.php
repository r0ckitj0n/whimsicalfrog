<?php
// Business Settings Database Initialization
// This script creates the business_settings table and populates it with default values

require_once 'config.php';

header('Content-Type: text/html; charset=UTF-8');

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "<h2>Business Settings Database Initialization</h2>";
    
    // Create business_settings table
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS business_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_type ENUM('text', 'color', 'email', 'url', 'number', 'json', 'boolean') DEFAULT 'text',
        category VARCHAR(50) NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        description TEXT,
        is_required BOOLEAN DEFAULT FALSE,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($createTableSQL);
    echo "✅ Business settings table created successfully<br>";
    
    // Default business settings
    $defaultSettings = [
        // Business Information
        ['business_name', 'WhimsicalFrog', 'text', 'business_info', 'Business Name', 'The name of your business', true, 1],
        ['business_domain', 'whimsicalfrog.us', 'url', 'business_info', 'Business Domain', 'Your website domain (without https://)', true, 2],
        ['business_email', 'orders@whimsicalfrog.us', 'email', 'business_info', 'Business Email', 'Primary business email address', true, 3],
        ['admin_email', 'admin@whimsicalfrog.us', 'email', 'business_info', 'Admin Email', 'Admin notification email address', true, 4],
        ['support_email', 'orders@whimsicalfrog.us', 'email', 'business_info', 'Support Email', 'Customer support email address', true, 5],
        ['business_phone', '', 'text', 'business_info', 'Business Phone', 'Business phone number', false, 6],
        ['business_address', '', 'text', 'business_info', 'Business Address', 'Physical business address', false, 7],
        
        // Brand Colors
        ['primary_color', '#87ac3a', 'color', 'branding', 'Primary Brand Color', 'Main brand color used throughout the site', true, 1],
        ['secondary_color', '#6b8e23', 'color', 'branding', 'Secondary Brand Color', 'Secondary brand color for hover states', true, 2],
        ['accent_color', '#a3cc4a', 'color', 'branding', 'Accent Color', 'Accent color for highlights', false, 3],
        ['text_color', '#333333', 'color', 'branding', 'Text Color', 'Primary text color', false, 4],
        ['background_color', '#ffffff', 'color', 'branding', 'Background Color', 'Main background color', false, 5],
        
        // Payment Methods
        ['payment_methods', '["Credit Card", "PayPal", "Check", "Cash", "Venmo"]', 'json', 'payment', 'Payment Methods', 'Available payment methods for customers', true, 1],
        ['default_payment_method', 'Credit Card', 'text', 'payment', 'Default Payment Method', 'Default payment method selection', true, 2],
        ['payment_statuses', '["Pending", "Processing", "Received", "Refunded", "Failed"]', 'json', 'payment', 'Payment Statuses', 'Available payment status options', true, 3],
        ['default_payment_status', 'Pending', 'text', 'payment', 'Default Payment Status', 'Default payment status for new orders', true, 4],
        
        // Shipping Methods
        ['shipping_methods', '["Customer Pickup", "Local Delivery", "USPS", "FedEx", "UPS"]', 'json', 'shipping', 'Shipping Methods', 'Available shipping methods for customers', true, 1],
        ['default_shipping_method', 'Customer Pickup', 'text', 'shipping', 'Default Shipping Method', 'Default shipping method selection', true, 2],
        ['shipping_statuses', '["Pending", "Processing", "Shipped", "Delivered", "Cancelled"]', 'json', 'shipping', 'Shipping Statuses', 'Available shipping status options', true, 3],
        ['default_shipping_status', 'Pending', 'text', 'shipping', 'Default Shipping Status', 'Default shipping status for new orders', true, 4],
        
        // Order Settings
        ['order_statuses', '["Pending", "Processing", "Completed", "Cancelled", "Refunded"]', 'json', 'orders', 'Order Statuses', 'Available order status options', true, 1],
        ['default_order_status', 'Pending', 'text', 'orders', 'Default Order Status', 'Default order status for new orders', true, 2],
        ['order_id_prefix', '', 'text', 'orders', 'Order ID Prefix', 'Prefix for order IDs (optional)', false, 3],
        ['auto_order_confirmation', 'true', 'boolean', 'orders', 'Auto Order Confirmation', 'Automatically send order confirmation emails', true, 4],
        
        // Tax Settings
        ['tax_rate', '0.00', 'number', 'tax', 'Tax Rate', 'Default tax rate (as decimal, e.g., 0.08 for 8%)', true, 1],
        ['tax_enabled', 'false', 'boolean', 'tax', 'Tax Enabled', 'Enable tax calculation on orders', true, 2],
        ['tax_name', 'Sales Tax', 'text', 'tax', 'Tax Name', 'Display name for tax (e.g., "Sales Tax", "VAT")', false, 3],
        
        // Email Settings
        ['email_subject_prefix', '', 'text', 'email', 'Email Subject Prefix', 'Prefix for all email subjects (optional)', false, 1],
        ['email_footer_text', 'Thank you for shopping with us!', 'text', 'email', 'Email Footer Text', 'Footer text for all emails', false, 2],
        ['email_signature', 'Best regards,<br>The Team', 'text', 'email', 'Email Signature', 'Email signature for all emails', false, 3],
        
        // Site Settings
        ['site_title', 'WhimsicalFrog', 'text', 'site', 'Site Title', 'Website title shown in browser tab', true, 1],
        ['site_description', 'Custom products and creative designs', 'text', 'site', 'Site Description', 'Website description for SEO', false, 2],
        ['site_keywords', 'custom products, designs, t-shirts, tumblers', 'text', 'site', 'Site Keywords', 'Website keywords for SEO', false, 3],
        ['maintenance_mode', 'false', 'boolean', 'site', 'Maintenance Mode', 'Enable maintenance mode to hide site from customers', false, 4],
        
        // Inventory Settings
        ['low_stock_threshold', '5', 'number', 'inventory', 'Low Stock Threshold', 'Default threshold for low stock warnings', true, 1],
        ['auto_reorder_point', '10', 'number', 'inventory', 'Auto Reorder Point', 'Default reorder point for new inventory items', true, 2],
        ['default_markup_percentage', '100', 'number', 'inventory', 'Default Markup %', 'Default markup percentage for pricing (100 = double cost)', true, 3],
    ];
    
    // Insert default settings
    $insertSQL = "INSERT IGNORE INTO business_settings 
                  (setting_key, setting_value, setting_type, category, display_name, description, is_required, display_order) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($insertSQL);
    
    $insertedCount = 0;
    foreach ($defaultSettings as $setting) {
        if ($stmt->execute($setting)) {
            $insertedCount++;
        }
    }
    
    echo "✅ Inserted {$insertedCount} default business settings<br>";
    
    // Show current settings
    echo "<h3>Current Business Settings</h3>";
    $settingsStmt = $pdo->query("SELECT * FROM business_settings ORDER BY category, display_order");
    $settings = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $currentCategory = '';
    foreach ($settings as $setting) {
        if ($setting['category'] !== $currentCategory) {
            if ($currentCategory !== '') echo "</ul>";
            echo "<h4>" . ucwords(str_replace('_', ' ', $setting['category'])) . "</h4><ul>";
            $currentCategory = $setting['category'];
        }
        
        $value = $setting['setting_value'];
        if ($setting['setting_type'] === 'json') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? implode(', ', $decoded) : $value;
        } elseif ($setting['setting_type'] === 'color') {
            $value = "<span style='background-color: {$value}; padding: 2px 8px; border-radius: 3px; color: white;'>{$value}</span>";
        }
        
        echo "<li><strong>{$setting['display_name']}:</strong> {$value}</li>";
    }
    echo "</ul>";
    
    echo "<br><h3>✅ Business Settings Database Initialization Complete!</h3>";
    echo "<p>You can now manage these settings through the admin panel.</p>";
    
} catch (PDOException $e) {
    echo "<h3>❌ Database Error</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
} catch (Exception $e) {
    echo "<h3>❌ Error</h3>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?> 