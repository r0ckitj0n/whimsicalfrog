<?php
/**
 * Business Settings API
 * Modern refactored version using backup logic.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/business_settings_api_helper.php';

$brandingTokenKeys = [
    'business_brand_primary', 'business_brand_secondary', 'business_brand_accent',
    'business_brand_background', 'business_brand_text', 'business_toast_text',
    'business_brand_font_primary', 'business_brand_font_secondary',
    'business_public_header_bg', 'business_public_header_text',
    'business_public_modal_bg', 'business_public_modal_text',
    'business_public_page_bg', 'business_public_page_text',
    'business_button_primary_bg', 'business_button_primary_hover',
    'business_button_secondary_bg', 'business_button_secondary_hover',
    'business_brand_palette', 'business_css_vars', 'brand_backup', 'brand_backup_saved_at'
];

try {
    Database::getInstance();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? '');
    // Fallback detection for common patterns from hooks
    if (!$action) {
        if (isset($_GET['category']) || isset($_POST['category']) || isset($input['category'])) {
            $action = WF_Constants::ACTION_GET_BY_CATEGORY;
        } elseif (isset($_GET['key']) || isset($_POST['key']) || isset($input['key']) || isset($_GET['setting_key'])) {
            $action = WF_Constants::ACTION_GET_SETTING;
        }
    }
    $allowedActions = [
        WF_Constants::ACTION_GET_ALL_SETTINGS,
        WF_Constants::ACTION_GET_SETTING,
        WF_Constants::ACTION_UPDATE_SETTING,
        WF_Constants::ACTION_UPSERT_SETTINGS,
        WF_Constants::ACTION_GET_BY_CATEGORY,
        WF_Constants::ACTION_GET_BUSINESS_INFO,
        WF_Constants::ACTION_GET_SALES_VERBIAGE
    ];
    if (!in_array($action, $allowedActions, true)) {
        Response::error('Invalid action: ' . $action, null, 400);
    }
    $publicReadActions = [
        WF_Constants::ACTION_GET_BUSINESS_INFO,
        WF_Constants::ACTION_GET_SALES_VERBIAGE,
    ];
    $publicCategoryAllowlist = ['shopping_cart'];
    $isPublicCategoryRead = (
        $action === WF_Constants::ACTION_GET_BY_CATEGORY
        && in_array((string) ($_GET['category'] ?? $_POST['category'] ?? $input['category'] ?? ''), $publicCategoryAllowlist, true)
    );

    if (!in_array($action, $publicReadActions, true) && !$isPublicCategoryRead) {
        AuthHelper::requireAdmin(403, 'Admin access required');
    }
    $mutatingActions = [WF_Constants::ACTION_UPDATE_SETTING, WF_Constants::ACTION_UPSERT_SETTINGS];
    if (in_array($action, $mutatingActions, true) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        Response::methodNotAllowed('Method not allowed');
    }

    switch ($action) {
        case WF_Constants::ACTION_GET_ALL_SETTINGS:
            $settings = Database::queryAll("SELECT * FROM business_settings ORDER BY category, display_order, setting_key");
            Response::success(['settings' => $settings, 'count' => count($settings)]);
            break;

        case WF_Constants::ACTION_GET_SETTING:
            $key = $_GET['key'] ?? $_GET['setting_key'] ?? '';
            if (empty($key)) Response::error('Key required', null, 400);
            $setting = Database::queryOne("SELECT * FROM business_settings WHERE setting_key = ?", [$key]);
            
            // Heuristic fallbacks for missing keys like cart_intent_heuristics
            if (!$setting && $key === 'cart_intent_heuristics') {
                $defaults = [
                    'weights' => [
                        'popularity_cap' => 3.0,
                        'kw_positive' => 2.5,
                        'cat_positive' => 3.5,
                        'seasonal' => 2.0,
                        'same_category' => 2.0,
                        'upgrade_price_ratio_threshold' => 1.25,
                        'upgrade_price_boost' => 3.0,
                        'upgrade_label_boost' => 2.5,
                        'replacement_label_boost' => 3.0,
                        'gift_set_boost' => 1.0,
                        'gift_price_boost' => 1.5,
                        'teacher_price_ceiling' => 30.0,
                        'teacher_price_boost' => 1.5,
                        'budget_proximity_mult' => 2.0,
                        'neg_keyword_penalty' => 2.0,
                        'intent_badge_threshold' => 2.0
                    ],
                    'budget_ranges' => [
                        'low' => [8.0, 20.0],
                        'mid' => [15.0, 40.0],
                        'high' => [35.0, 120.0]
                    ],
                    'keywords' => [
                        'positive' => [
                            'gift' => ["gift", "set", "bundle", "present", "pack", "box"],
                            'replacement' => ["refill", "replacement", "spare", "recharge", "insert"],
                            'upgrade' => ["upgrade", "pro", "deluxe", "premium", "xl", "plus", "pro+", "ultimate"],
                            'diy-project' => ["diy", "kit", "project", "starter", "make your own", "how to"],
                            'home-decor' => ["decor", "wall", "frame", "sign", "plaque", "art", "canvas"]
                        ],
                        'negative' => [
                            'gift' => ["refill", "replacement"],
                            'replacement' => ["gift", "decor"],
                            'upgrade' => ["refill"]
                        ],
                        'categories' => [
                            'gift' => ["gifts", "gift sets", "bundles"],
                            'replacement' => ["supplies", "refills", "consumables"],
                            'diy-project' => ["diy", "kits", "craft kits", "projects"],
                            'home-decor' => ["home decor", "decor", "wall art", "signs"]
                        ]
                    ],
                    'seasonal_months' => [
                        "1" => ["valentine"], "2" => ["valentine"], "12" => ["christmas"]
                    ]
                ];
                $setting = [
                    'setting_key' => 'cart_intent_heuristics',
                    'setting_value' => json_encode($defaults),
                    'category' => 'ecommerce',
                    'setting_type' => 'json'
                ];
            }
            
            $setting ? Response::success(['setting' => $setting]) : Response::error('Not found', null, 404);
            break;

        case WF_Constants::ACTION_UPDATE_SETTING:
            $key = $_POST['key'] ?? '';
            $value = $_POST['value'] ?? '';
            if (empty($key)) Response::error('Key required', null, 400);
            $res = Database::execute("UPDATE business_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?", [$value, $key]);
            if ($res > 0 && class_exists('BusinessSettings')) BusinessSettings::clearCache();
            $res > 0 ? Response::updated() : Response::noChanges();
            break;

        case WF_Constants::ACTION_UPSERT_SETTINGS:
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            handle_upsert_settings($input, $_POST, $brandingTokenKeys);
            break;

        case WF_Constants::ACTION_GET_BY_CATEGORY:
            $category = $_GET['category'] ?? $_POST['category'] ?? '';
            if (empty($category)) Response::error('Category required', null, 400);
            $settings = Database::queryAll("SELECT * FROM business_settings WHERE category = ? ORDER BY display_order, setting_key", [$category]);
            
            // Modern React hooks expect a flat object under 'settings' key
            $flat = [];
            foreach ($settings as $s) {
                $val = $s['setting_value'];
                if ($s['setting_type'] === 'number') $val = (float)$val;
                elseif ($s['setting_type'] === 'boolean') $val = ($val === 'true' || $val === '1');
                elseif ($s['setting_type'] === 'json') $val = json_decode($val, true);
                $flat[$s['setting_key']] = $val;
            }
            Response::json(['success' => true, 'settings' => $flat]);
            break;

        case WF_Constants::ACTION_GET_BUSINESS_INFO:
            handle_get_business_info();
            break;

        case WF_Constants::ACTION_GET_SALES_VERBIAGE:
            $rows = Database::queryAll("SELECT setting_key, setting_value FROM business_settings WHERE category = 'sales' ORDER BY display_order");
            $settings = [];
            foreach ($rows as $r) $settings[$r['setting_key']] = $r['setting_value'];
            Response::success(['verbiage' => $settings]);
            break;

        default:
            Response::error('Invalid action: ' . $action, null, 400);
    }
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
