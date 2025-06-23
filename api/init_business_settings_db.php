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
        // Website Branding & Identity
        ['site_name', 'text', 'Whimsical Frog', 'branding', 'Main website name/title'],
        ['site_tagline', 'text', 'Custom Crafts & Creative Designs', 'branding', 'Website tagline/subtitle'],
        ['site_logo_url', 'url', '/images/WhimsicalFrog_Logo.webp', 'branding', 'Main logo image URL'],
        ['site_favicon_url', 'url', '/favicon.ico', 'branding', 'Favicon URL'],
        ['brand_primary_color', 'color', '#87ac3a', 'branding', 'Primary brand color'],
        ['brand_secondary_color', 'color', '#556B2F', 'branding', 'Secondary brand color'],
        ['brand_accent_color', 'color', '#6B8E23', 'branding', 'Accent brand color'],
        
        // Business Information
        ['business_name', 'text', 'Whimsical Frog LLC', 'business_info', 'Legal business name'],
        ['business_description', 'text', 'We create custom crafts, personalized gifts, and unique creative designs for every occasion.', 'business_info', 'Business description'],
        ['business_address', 'text', '123 Craft Lane, Creative City, CC 12345', 'business_info', 'Business address'],
        ['business_phone', 'text', '(555) 123-FROG', 'business_info', 'Business phone number'],
        ['business_email', 'email', 'hello@whimsicalfrog.us', 'business_info', 'Primary business email'],
        ['business_hours', 'text', 'Mon-Fri: 9AM-6PM, Sat: 10AM-4PM, Closed Sundays', 'business_info', 'Business operating hours'],
        ['business_social_facebook', 'url', 'https://facebook.com/whimsicalfrog', 'business_info', 'Facebook page URL'],
        ['business_social_instagram', 'url', 'https://instagram.com/whimsicalfrog', 'business_info', 'Instagram profile URL'],
        ['business_social_twitter', 'url', 'https://twitter.com/whimsicalfrog', 'business_info', 'Twitter profile URL'],
        
        // Room/Category Configuration
        ['room_system_enabled', 'boolean', 'true', 'rooms', 'Enable the room-based navigation system'],
        ['room_main_title', 'text', 'Welcome to Our Creative Workshop', 'rooms', 'Main room title'],
        ['room_main_description', 'text', 'Explore our different departments by clicking on the doors', 'rooms', 'Main room description'],
        ['room_2_category', 'text', 'T-Shirts', 'rooms', 'Room 2 category name'],
        ['room_3_category', 'text', 'Tumblers', 'rooms', 'Room 3 category name'],
        ['room_4_category', 'text', 'Artwork', 'rooms', 'Room 4 category name'],
        ['room_5_category', 'text', 'Sublimation', 'rooms', 'Room 5 category name'],
        ['room_6_category', 'text', 'Window Wraps', 'rooms', 'Room 6 category name'],
        
        // E-commerce Settings
        ['currency_symbol', 'text', '$', 'ecommerce', 'Currency symbol'],
        ['currency_code', 'text', 'USD', 'ecommerce', 'Currency code'],
        ['tax_rate', 'number', '0.08', 'ecommerce', 'Tax rate (decimal, e.g., 0.08 for 8%)'],
        ['shipping_enabled', 'boolean', 'true', 'ecommerce', 'Enable shipping options'],
        ['local_pickup_enabled', 'boolean', 'true', 'ecommerce', 'Enable local pickup option'],
        ['min_order_amount', 'number', '10.00', 'ecommerce', 'Minimum order amount'],
        ['free_shipping_threshold', 'number', '50.00', 'ecommerce', 'Free shipping threshold'],
        
        // Email Configuration
        ['email_from_name', 'text', 'Whimsical Frog', 'email', 'Email sender name'],
        ['email_from_address', 'email', 'noreply@whimsicalfrog.us', 'email', 'Email sender address'],
        ['email_support_address', 'email', 'support@whimsicalfrog.us', 'email', 'Support email address'],
        ['email_order_notifications', 'boolean', 'true', 'email', 'Send order notification emails'],
        ['email_welcome_enabled', 'boolean', 'true', 'email', 'Send welcome emails to new customers'],
        
        // Inventory Settings
        ['low_stock_threshold', 'number', '5', 'inventory', 'Low stock warning threshold'],
        ['out_of_stock_behavior', 'text', 'show_disabled', 'inventory', 'How to handle out of stock items (hide/show_disabled/show_normal)'],
        ['auto_sku_generation', 'boolean', 'true', 'inventory', 'Automatically generate SKUs for new items'],
        ['sku_prefix', 'text', 'WF', 'inventory', 'SKU prefix for auto-generation'],
        ['enable_backorders', 'boolean', 'false', 'inventory', 'Allow orders when items are out of stock'],
        
        // Order Management
        ['order_id_prefix', 'text', '', 'orders', 'Order ID prefix'],
        ['order_confirmation_required', 'boolean', 'true', 'orders', 'Require order confirmation'],
        ['auto_order_status', 'text', 'pending', 'orders', 'Default order status'],
        ['payment_methods', 'json', '["Credit Card", "PayPal", "Check", "Cash", "Venmo"]', 'orders', 'Available payment methods'],
        ['order_statuses', 'json', '["pending", "confirmed", "processing", "shipped", "delivered", "cancelled"]', 'orders', 'Available order statuses'],
        
        // Payment Settings
        ['payment_gateway', 'text', 'manual', 'payment', 'Payment gateway (manual/stripe/paypal/square)'],
        ['payment_instructions', 'text', 'Payment instructions will be provided after order confirmation.', 'payment', 'Payment instructions for customers'],
        ['accept_cash', 'boolean', 'true', 'payment', 'Accept cash payments'],
        ['accept_checks', 'boolean', 'true', 'payment', 'Accept check payments'],
        
        // Shipping Configuration
        ['shipping_methods', 'json', '["Customer Pickup", "Local Delivery", "USPS", "FedEx", "UPS"]', 'shipping', 'Available shipping methods'],
        ['local_delivery_radius', 'number', '25', 'shipping', 'Local delivery radius in miles'],
        ['local_delivery_fee', 'number', '5.00', 'shipping', 'Local delivery fee'],
        ['standard_shipping_fee', 'number', '8.99', 'shipping', 'Standard shipping fee'],
        ['expedited_shipping_fee', 'number', '15.99', 'shipping', 'Expedited shipping fee'],
        
        // Site Features
        ['enable_user_accounts', 'boolean', 'true', 'site', 'Enable user registration and accounts'],
        ['enable_guest_checkout', 'boolean', 'true', 'site', 'Allow checkout without account'],
        ['enable_wishlist', 'boolean', 'false', 'site', 'Enable wishlist functionality'],
        ['enable_reviews', 'boolean', 'false', 'site', 'Enable product reviews'],
        ['enable_search', 'boolean', 'true', 'site', 'Enable product search'],
        ['items_per_page', 'number', '12', 'site', 'Items per page in shop/category views'],
        ['enable_ai_features', 'boolean', 'true', 'site', 'Enable AI-powered features'],
        
        // SEO & Metadata
        ['meta_title', 'text', 'Whimsical Frog - Custom Crafts & Creative Designs', 'seo', 'Default page title'],
        ['meta_description', 'text', 'Discover unique custom crafts, personalized gifts, and creative designs at Whimsical Frog. Quality handmade items for every occasion.', 'seo', 'Default meta description'],
        ['meta_keywords', 'text', 'custom crafts, personalized gifts, creative designs, handmade, custom t-shirts, tumblers, artwork', 'seo', 'Default meta keywords'],
        ['google_analytics_id', 'text', '', 'seo', 'Google Analytics tracking ID'],
        ['facebook_pixel_id', 'text', '', 'seo', 'Facebook Pixel ID'],
        
        // Tax Configuration
        ['tax_enabled', 'boolean', 'true', 'tax', 'Enable tax calculations'],
        ['tax_name', 'text', 'Sales Tax', 'tax', 'Tax display name'],
        ['tax_inclusive', 'boolean', 'false', 'tax', 'Prices include tax'],
        ['tax_based_on', 'text', 'billing', 'tax', 'Tax calculation based on (billing/shipping/store)'],
        
        // Admin Interface
        ['admin_items_per_page', 'number', '25', 'admin', 'Items per page in admin views'],
        ['admin_auto_save', 'boolean', 'true', 'admin', 'Auto-save admin form changes'],
        ['admin_session_timeout', 'number', '3600', 'admin', 'Admin session timeout in seconds'],
        ['enable_admin_notifications', 'boolean', 'true', 'admin', 'Enable admin email notifications'],
        
        // Performance & Caching
        ['enable_image_optimization', 'boolean', 'true', 'performance', 'Enable automatic image optimization'],
        ['image_quality', 'number', '90', 'performance', 'Image compression quality (1-100)'],
        ['enable_css_minification', 'boolean', 'false', 'performance', 'Enable CSS minification'],
        ['enable_js_minification', 'boolean', 'false', 'performance', 'Enable JavaScript minification'],
        ['cache_duration', 'number', '3600', 'performance', 'Cache duration in seconds']
    ];
    
    // Insert default settings
    $insertSQL = "INSERT IGNORE INTO business_settings 
                  (setting_key, setting_type, setting_value, category, display_name, description, is_required, display_order) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($insertSQL);
    
    $insertedCount = 0;
    $displayOrder = 1;
    foreach ($defaultSettings as $setting) {
        // New format: [key, type, value, category, description]
        $key = $setting[0];
        $type = $setting[1];
        $value = $setting[2];
        $category = $setting[3];
        $description = $setting[4];
        $displayName = ucwords(str_replace('_', ' ', $key));
        $isRequired = in_array($category, ['branding', 'business_info', 'ecommerce']) ? 1 : 0;
        
        if ($stmt->execute([$key, $type, $value, $category, $displayName, $description, $isRequired, $displayOrder])) {
            $insertedCount++;
        }
        $displayOrder++;
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