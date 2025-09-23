<?php

// Business Settings API
// Handles comprehensive business configuration for website customization

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
// Ensure we can clear cached settings after writes
@require_once __DIR__ . '/business_settings_helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

/**
 * Ensure About Page settings exist with defaults
 */
function ensureAboutSettings()
{
    try {
        Database::execute("INSERT INTO business_settings (category, setting_key, setting_value, description, setting_type, display_name) 
            VALUES 
            ('site', 'about_page_title', 'Our Story', 'Title shown at the top of the About page', 'text', 'About Page Title'),
            ('site', 'about_page_content', '<p>Once upon a time in a cozy little workshop, Calvin &amp; Lisa Lemley began crafting whimsical treasures for friends and family. What started as a weekend habit of chasing ideas and laughter soon grew into WhimsicalFrog&mdash;a tiny brand with a big heart.</p><p>Every piece we make is a small celebration of play and everyday magic: things that delight kids, spark curiosity, and make grown‑ups smile. We believe in craftsmanship, kindness, and creating goods that feel like they were made just for you.</p><p>Thank you for visiting our little corner of the pond. We hope our creations bring a splash of joy to your day!</p>', 'Main content of the About page (HTML)', 'html', 'About Page Content (HTML)')
           ON DUPLICATE KEY UPDATE setting_value = setting_value, description = VALUES(description), setting_type = VALUES(setting_type), display_name = VALUES(display_name)");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Ensure Email Settings exist with sensible defaults
 */
function ensureEmailSettings($pdo)
{
    try {
        Database::execute("INSERT INTO business_settings (category, setting_key, setting_value, description, setting_type, display_name)
              VALUES
              ('email', 'from_email', '', 'Default From email address used for outgoing emails', 'text', 'From Email'),
              ('email', 'from_name', '', 'Default From name used for outgoing emails', 'text', 'From Name'),
              ('email', 'admin_email', '', 'Primary admin email address for notifications', 'text', 'Admin Email'),
              ('email', 'bcc_email', '', 'Optional BCC email for order confirmations and notifications', 'text', 'BCC Email'),
              ('email', 'smtp_enabled', 'false', 'Enable SMTP for sending emails (true/false)', 'boolean', 'SMTP Enabled'),
              ('email', 'smtp_host', '', 'SMTP server hostname', 'text', 'SMTP Host'),
              ('email', 'smtp_port', '587', 'SMTP server port (e.g., 587 for TLS, 465 for SSL)', 'number', 'SMTP Port'),
              ('email', 'smtp_encryption', 'tls', 'SMTP encryption method (none, tls, ssl)', 'text', 'SMTP Encryption'),
              ('email', 'reply_to', '', 'Reply-To email address for outgoing emails', 'text', 'Reply-To Email'),
              ('email', 'test_recipient', '', 'Default recipient for test emails', 'text', 'Test Recipient Email'),
              ('email', 'smtp_username', '', 'SMTP username if authentication is required', 'text', 'SMTP Username'),
              ('email', 'smtp_password', '', 'SMTP password (store securely if supported)', 'text', 'SMTP Password'),
              ('email', 'smtp_auth', 'true', 'Whether SMTP authentication is required', 'boolean', 'SMTP Auth Enabled'),
              ('email', 'smtp_timeout', '30', 'Timeout in seconds for SMTP connections', 'number', 'SMTP Timeout'),
              ('email', 'smtp_debug', 'false', 'Enable verbose SMTP debug logging', 'boolean', 'SMTP Debug')
              ON DUPLICATE KEY UPDATE setting_value = setting_value, description = VALUES(description), setting_type = VALUES(setting_type), display_name = VALUES(display_name)");

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Ensure Contact Page settings exist with defaults
 */
function ensureContactSettings($pdo)
{
    try {
        Database::execute("INSERT INTO business_settings (category, setting_key, setting_value, description, setting_type, display_name) 
              VALUES 
              ('site', 'contact_page_title', 'Contact Us', 'Title shown at the top of the Contact page', 'text', 'Contact Page Title'),
              ('site', 'contact_page_intro', '<p>Have a question or special request? Send us a message and we\'ll get back to you soon.</p>', 'Introductory HTML content displayed above the contact form', 'html', 'Contact Page Intro (HTML)'),
              ('business_info', 'business_owner', '', 'Owner or proprietor name', 'text', 'Business Owner'),
              ('business_info', 'business_address', '123 Craft Lane, Creative City, CC 12345', 'Business address', 'text', 'Business Address'),
              ('business_info', 'business_phone', '', 'Primary business phone number (displayed and used for tel: link)', 'text', 'Business Phone'),
              ('business_info', 'business_hours', '', 'Business hours (multi-line text supported)', 'text', 'Business Hours')
              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), description = VALUES(description), setting_type = VALUES(setting_type), display_name = VALUES(display_name)");

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

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

        case 'upsert_settings':
            upsertSettings($pdo);
            break;

        case 'reset_to_defaults':
            resetToDefaults($pdo);
            break;

        case 'get_by_category':
            getByCategory($pdo);
            break;

        case 'get_sales_verbiage':
            try {
                $rows = Database::queryAll(
                    "SELECT setting_key, setting_value FROM business_settings WHERE category = 'sales' ORDER BY display_order"
                );
                $settings = [];
                foreach ($rows as $r) {
                    $settings[$r['setting_key']] = $r['setting_value'];
                }

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

        case 'ensure_contact_settings':
            ensureContactSettings($pdo);
            break;

        case 'ensure_about_settings':
            ensureAboutSettings($pdo);
            break;

        case 'ensure_email_settings':
            ensureEmailSettings($pdo);
            break;

        case 'get_business_info':
            getBusinessInfo();
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

function getAllSettings($pdo)
{
    $settings = Database::queryAll("SELECT * FROM business_settings ORDER BY category, display_order, setting_key");

    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'count' => count($settings)
    ]);
}

function getSetting($pdo)
{
    $key = $_GET['key'] ?? '';

    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Setting key is required']);
        return;
    }

    $setting = Database::queryOne("SELECT * FROM business_settings WHERE setting_key = ?", [$key]);

    if ($setting) {
        echo json_encode(['success' => true, 'setting' => $setting]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Setting not found']);
    }
}

function updateSetting($pdo)
{
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? '';

    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Setting key is required']);
        return;
    }

    $result = Database::execute("UPDATE business_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?", [$value, $key]);

    if ($result > 0) {
        // Clear settings cache so subsequent requests see the latest values
        if (class_exists('BusinessSettings')) {
            BusinessSettings::clearCache();
        }
        echo json_encode(['success' => true, 'message' => 'Setting updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update setting or setting not found']);
    }
}

function updateMultipleSettings($pdo)
{
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

    Database::beginTransaction();

    try {
        $updatedCount = 0;

        foreach ($settings as $key => $value) {
            $affected = Database::execute("UPDATE business_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?", [$value, $key]);
            if ($affected > 0) {
                $updatedCount++;
            }
        }

        Database::commit();

        // Clear settings cache so subsequent requests see the latest values
        if (class_exists('BusinessSettings')) {
            BusinessSettings::clearCache();
        }

        echo json_encode([
            'success' => true,
            'message' => "Updated {$updatedCount} settings successfully",
            'updated_count' => $updatedCount
        ]);

    } catch (Exception $e) {
        Database::rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update settings: ' . $e->getMessage()]);
    }
}
/**
 * Insert or update multiple settings, creating rows if they don't exist
 * Accepts either JSON body { settings: { key: value, ... }, category?: 'ecommerce' }
 * or form POST with 'settings' (JSON string) and optional 'category'
 */
function upsertSettings($pdo)
{
    // Prefer JSON payload
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    $category = 'ecommerce';
    $settings = null;

    if (is_array($input)) {
        // Support either flat object or wrapped under 'settings'
        if (isset($input['settings']) && is_array($input['settings'])) {
            $settings = $input['settings'];
        } else {
            // If not wrapped, assume the object is the settings map
            $settings = $input;
        }
        if (!empty($input['category']) && is_string($input['category'])) {
            $category = $input['category'];
        }
    }

    // Fallback to form POST
    if ($settings === null) {
        $category = $_POST['category'] ?? $category;
        $settingsJson = $_POST['settings'] ?? '';
        if (!empty($settingsJson)) {
            $decoded = json_decode($settingsJson, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
    }

    if (!is_array($settings) || empty($settings)) {
        echo json_encode(['success' => false, 'message' => 'Settings map is required']);
        return;
    }

    $pdo->beginTransaction();

    try {
        $sql = "INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), updated_at = CURRENT_TIMESTAMP";

        $saved = 0;
        foreach ($settings as $key => $value) {
            // Infer type and normalize value for storage
            $type = 'text';
            if (is_bool($value) || (is_string($value) && in_array(strtolower($value), ['true','false','1','0'], true))) {
                // Normalize booleans
                $boolVal = is_bool($value) ? $value : in_array(strtolower($value), ['true','1'], true);
                $value = $boolVal ? 'true' : 'false';
                $type = 'boolean';
            } elseif (is_numeric($value)) {
                $type = 'number';
                $value = (string)$value;
            } elseif (is_array($value)) {
                $type = 'json';
                $value = json_encode($value);
            } else {
                $type = 'text';
                $value = (string)$value;
            }

            $displayName = ucwords(str_replace('_', ' ', (string)$key));
            $description = 'Business setting ' . (string)$key;

            $affected = Database::execute($sql, [$category, (string)$key, $value, $type, $displayName, $description]);
            if ($affected > 0) {
                $saved++;
            }
        }

        Database::commit();

        // Clear settings cache so subsequent requests see the latest values
        if (class_exists('BusinessSettings')) {
            BusinessSettings::clearCache();
        }

        echo json_encode([
            'success' => true,
            'message' => "Upserted {$saved} settings successfully",
            'updated_count' => $saved,
            'category' => $category
        ]);

    } catch (Exception $e) {
        Database::rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to upsert settings: ' . $e->getMessage()]);
    }
}
// resetToDefaults function moved to data_manager.php for centralization

function getByCategory($pdo)
{
    $category = $_GET['category'] ?? '';

    if (empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Category is required']);
        return;
    }

    $settings = Database::queryAll("SELECT * FROM business_settings WHERE category = ? ORDER BY display_order, setting_key", [$category]);

    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'category' => $category,
        'count' => count($settings)
    ]);
}

/**
 * Return a consolidated map of business info values using BusinessSettings helper.
 * This ensures callers (like the Business Information modal) see the same values
 * the receipt and other server-side code use, regardless of category.
 */
function getBusinessInfo()
{
    // BusinessSettings helper is included at top of this file (if available)
    if (!class_exists('BusinessSettings')) {
        // Guard against fatal error if helper is missing
        if (file_exists(__DIR__ . '/business_settings_helper.php')) {
            require_once __DIR__ . '/business_settings_helper.php';
        } else {
            echo json_encode(['success' => false, 'message' => 'BusinessSettings helper not found']);
            return;
        }
    }

    // Build a flat map matching the keys from the modal's collectBusinessInfo() JS function
    $get = function ($key, $default = '') {
        try {
            return BusinessSettings::get($key, $default);
        } catch (Exception $e) {
            return $default;
        }
    };

    $info = [
        'business_name' => BusinessSettings::getBusinessName(),
        'business_email' => BusinessSettings::getBusinessEmail(),
        'business_phone' => $get('business_phone'),
        'business_hours' => $get('business_hours'),
        'business_address' => $get('business_address'),
        'business_address2' => $get('business_address2'),
        'business_city' => $get('business_city'),
        'business_state' => $get('business_state'),
        'business_postal' => $get('business_postal'),
        'business_country' => $get('business_country'),
        'business_website' => $get('business_website', BusinessSettings::getSiteUrl()),
        'business_logo_url' => $get('business_logo_url'),
        'business_tagline' => $get('business_tagline'),
        'business_description' => $get('business_description'),
        'business_support_email' => $get('business_support_email'),
        'business_support_phone' => $get('business_support_phone'),
        'business_facebook' => $get('business_facebook'),
        'business_instagram' => $get('business_instagram'),
        'business_twitter' => $get('business_twitter'),
        'business_tiktok' => $get('business_tiktok'),
        'business_youtube' => $get('business_youtube'),
        'business_linkedin' => $get('business_linkedin'),
        'business_terms_url' => $get('business_terms_url'),
        'business_privacy_url' => $get('business_privacy_url'),
        'business_tax_id' => $get('business_tax_id'),
        'business_timezone' => $get('business_timezone'),
        'business_currency' => $get('business_currency'),
        'business_locale' => $get('business_locale'),
        'business_brand_primary' => $get('business_brand_primary', '#0ea5e9'),
        'business_brand_secondary' => $get('business_brand_secondary', '#6366f1'),
        'business_brand_accent' => $get('business_brand_accent', '#22c55e'),
        'business_brand_background' => $get('business_brand_background', '#ffffff'),
        'business_brand_text' => $get('business_brand_text', '#111827'),
        'business_footer_note' => $get('business_footer_note'),
        'business_footer_html' => $get('business_footer_html'),
        'business_policy_return' => $get('business_policy_return'),
        'business_policy_shipping' => $get('business_policy_shipping'),
        'business_policy_warranty' => $get('business_policy_warranty'),
        'business_policy_url' => $get('business_policy_url'),
        'business_brand_font_primary' => $get('business_brand_font_primary'),
        'business_brand_font_secondary' => $get('business_brand_font_secondary'),
        'business_css_vars' => $get('business_css_vars'),
    ];

    echo json_encode(['success' => true, 'data' => $info]);
}
