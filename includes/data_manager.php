<?php
/**
 * WhimsicalFrog Data Management and Operations
 * Centralized system functions to eliminate duplication
 * Generated: 2025-07-01 23:30:28
 */

// Include data and database dependencies
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';


function getMarketingData($pdo) {
    $sku = $_GET['sku'] ?? '';
    if (empty($sku)) {
        echo json_encode(['success' => false, 'error' => 'SKU is required.']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM marketing_suggestions WHERE sku = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$sku]);
    $data = $stmt->fetch();
    
    if ($data) {
        // Decode JSON fields
        $jsonFields = [
            'keywords', 'emotional_triggers', 'selling_points', 'competitive_advantages',
            'unique_selling_points', 'value_propositions', 'marketing_channels',
            'urgency_factors', 'social_proof_elements', 'call_to_action_suggestions',
            'conversion_triggers', 'objection_handlers', 'seo_keywords', 'content_themes',
            'customer_benefits', 'pain_points_addressed', 'lifestyle_alignment'
        ];
        
        foreach ($jsonFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = json_decode($data[$field], true) ?? [];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => true, 'data' => null]);
    }
}


function resetToDefaults($pdo) {
    try {
        // Get the default settings from the initialization file
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
            
            // Site Features
            ['enable_user_accounts', 'boolean', 'true', 'site', 'Enable user registration and accounts'],
            ['enable_guest_checkout', 'boolean', 'true', 'site', 'Allow checkout without account'],
            ['enable_search', 'boolean', 'true', 'site', 'Enable product search'],
            ['items_per_page', 'number', '12', 'site', 'Items per page in shop/category views'],
            ['enable_ai_features', 'boolean', 'true', 'site', 'Enable AI-powered features']
        ];
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE business_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
        $resetCount = 0;
        
        foreach ($defaultSettings as $setting) {
            $key = $setting[0];
            $value = $setting[2];
            
            if ($stmt->execute([$value, $key])) {
                $resetCount++;
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Reset {$resetCount} settings to default values",
            'reset_count' => $resetCount
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to reset settings: ' . $e->getMessage()]);
    }
}

?>