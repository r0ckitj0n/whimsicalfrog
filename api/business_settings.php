<?php
// Business Settings API
// Handles comprehensive business configuration for website customization

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_all_settings':
            getAllSettings($pdo);
            break;
            
        case 'get_setting':
            getSetting($pdo);
            break;
            
        case 'update_setting':
            updateSetting($pdo);
            break;
            
        case 'update_multiple_settings':
            updateMultipleSettings($pdo);
            break;
            
        case 'reset_to_defaults':
            resetToDefaults($pdo);
            break;
            
        case 'get_by_category':
            getByCategory($pdo);
            break;
            
        case 'get_sales_verbiage':
            try {
                $stmt = $pdo->prepare("
                    SELECT setting_key, setting_value 
                    FROM business_settings 
                    WHERE category = 'sales' 
                    ORDER BY display_order
                ");
                $stmt->execute();
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                Response::json([
                    'success' => true,
                    'verbiage' => $settings
                ]);
            } catch (Exception $e) {
                Response::json([
                    'success' => false,
                    'error' => 'Failed to load sales verbiage: ' . $e->getMessage()
                ], 500);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

function getAllSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM business_settings ORDER BY category, display_order, setting_key");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'count' => count($settings)
    ]);
}

function getSetting($pdo) {
    $key = $_GET['key'] ?? '';
    
    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Setting key is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($setting) {
        echo json_encode(['success' => true, 'setting' => $setting]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Setting not found']);
    }
}

function updateSetting($pdo) {
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? '';
    
    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Setting key is required']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE business_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
    $result = $stmt->execute([$value, $key]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update setting or setting not found']);
    }
}

function updateMultipleSettings($pdo) {
    $settingsJson = $_POST['settings'] ?? '';
    
    if (empty($settingsJson)) {
        echo json_encode(['success' => false, 'message' => 'Settings data is required']);
        return;
    }
    
    $settings = json_decode($settingsJson, true);
    if (!$settings) {
        echo json_encode(['success' => false, 'message' => 'Invalid settings data']);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        $stmt = $pdo->prepare("UPDATE business_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
        $updatedCount = 0;
        
        foreach ($settings as $key => $value) {
            if ($stmt->execute([$value, $key])) {
                $updatedCount++;
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => "Updated {$updatedCount} settings successfully",
            'updated_count' => $updatedCount
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update settings: ' . $e->getMessage()]);
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

function getByCategory($pdo) {
    $category = $_GET['category'] ?? '';
    
    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Category is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE category = ? ORDER BY display_order, setting_key");
    $stmt->execute([$category]);
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'category' => $category,
        'count' => count($settings)
    ]);
}
?> 