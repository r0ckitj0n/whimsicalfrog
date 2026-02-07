<?php
/**
 * /api/bootstrap.php
 * Aggregates all dynamic data for the React frontend to decouple from index.php
 */

require_once __DIR__ . '/../includes/bootstrap.php';
wf_bootstrap();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/site_settings.php';
require_once __DIR__ . '/../includes/branding_tokens_helper.php';
require_once __DIR__ . '/../includes/shop_data_loader.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!function_exists('wf_bootstrap_emit')) {
    function wf_bootstrap_emit(array $payload, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}

register_shutdown_function(static function (): void {
    $last = error_get_last();
    if ($last === null) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($last['type'] ?? null, $fatalTypes, true)) {
        return;
    }
    error_log('[bootstrap] Fatal shutdown: ' . ($last['message'] ?? 'unknown'));
    if (!headers_sent()) {
        wf_bootstrap_emit([
            'auth' => ['isLoggedIn' => false, 'user_id' => null, 'userData' => []],
            'site_settings' => [],
            'branding' => ['tokens' => [], 'style' => ''],
            'shop_data' => null,
            'about_data' => null,
            'contact_data' => null,
            'background_url' => '/images/backgrounds/background-roomA.webp',
            'timestamp' => time(),
            'error' => 'bootstrap_fallback_fatal'
        ], 200);
    }
});

try {
    try {
        ensureSessionStarted();
    } catch (\Throwable $e) {
        error_log('[bootstrap] ensureSessionStarted failed: ' . $e->getMessage());
    }

    // 1. Auth Status
    $isLoggedIn = false;
    $user_id = null;
    $userData = [];

    if (class_exists('AuthHelper')) {
        $isLoggedIn = AuthHelper::isLoggedIn();
        if ($isLoggedIn) {
            $userData = AuthHelper::getCurrentUser() ?? [];
            $user_id = $userData['id'] ?? null;
        }
    }

    // 2. Site Settings
    $site_settings = [
        'name' => wf_site_name(),
        'tagline' => wf_site_tagline(),
        'logo' => wf_brand_logo_path(),
        'email' => wf_business_email(),
        'social' => wf_social_links(),
        'brand_primary' => class_exists('BusinessSettings') ? BusinessSettings::getPrimaryColor() : '#87ac3a',
        'brand_secondary' => class_exists('BusinessSettings') ? BusinessSettings::getSecondaryColor() : '#BF5700',
    ];

    // 3. Branding Tokens
    $branding_tokens = BrandingTokens::getTokens();
    $branding_style = BrandingTokens::buildStyleBlock($branding_tokens);

    // 4. Shop/About/Contact Data (always include for simplicity in decoupled mode)
    $shop_data = null;
    if (isset($categories) && !empty($categories)) {
        require_once __DIR__ . '/../includes/image_helper.php';
        require_once __DIR__ . '/../includes/business_settings_helper.php';

        $processed_categories = [];
        foreach ($categories as $slug => $catData) {
            $processedItems = [];
            $items = $catData['items'] ?? [];
            foreach ($items as $item) {
                if (!isset($item['item_name']) || !isset($item['price'])) {
                    continue;
                }

                $sku = $item['sku'] ?? 'NO-SKU';
                $primaryImageData = function_exists('getPrimaryImageBySku') ? getPrimaryImageBySku($sku) : null;

                $processedItems[] = [
                    'sku' => $sku,
                    'item_name' => $item['item_name'],
                    'price' => $item['price'],
                    'stock' => (int) ($item['stock'] ?? 0),
                    'description' => $item['description'] ?? 'No description available',
                    'custom_button_text' => $item['custom_button_text'] ?? getRandomCartButtonText(),
                    'image_url' => $primaryImageData ? $primaryImageData['image_path'] : ($item['image_url'] ?? null)
                ];
            }
            $processed_categories[$slug] = [
                'slug' => $slug,
                'label' => $catData['label'] ?? ucfirst($slug),
                'items' => $processedItems
            ];
        }

        $shop_data = [
            'categories' => $processed_categories,
            'current_page' => 1
        ];
    }

    $about_data = [
        'title' => class_exists('BusinessSettings') ? BusinessSettings::get('about_page_title', 'Our Story') : 'Our Story',
        'content' => class_exists('BusinessSettings') ? BusinessSettings::get('about_page_content', '') : ''
    ];

    // Fallback for default story if empty
    $defaultStory = '<p>Once upon a time in a cozy little workshop, Calvin & Lisa Lemley began crafting whimsical treasures for friends and family. What started as a weekend habit of chasing ideas and laughter soon grew into:<br/>WhimsicalFrog—a tiny brand with a big heart.</p><p>Every piece we make is a small celebration of play and everyday magic: things that delight kids, spark curiosity, and make grown-ups smile. We believe in craftsmanship, kindness, and creating goods that feel like they were made just for you.</p><p>Thank you for visiting our little corner of the pond.<br/>We hope our creations bring a splash of joy to your day!</p>';
    if (empty(trim(strip_tags($about_data['content'])))) {
        $about_data['content'] = $defaultStory;
    }

    $contact_data = [
        'name' => base64_encode(wf_site_name()),
        'email' => base64_encode(wf_business_email()),
        'phone' => base64_encode(class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_phone', '') : ''),
        'address' => base64_encode(class_exists('BusinessSettings') ? (string) BusinessSettings::getBusinessAddressBlock() : ''),
        'hours' => base64_encode(class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_hours', '') : ''),
        'owner' => base64_encode(class_exists('BusinessSettings') ? (string) BusinessSettings::get('business_owner', '') : ''),
        'site' => base64_encode(class_exists('BusinessSettings') ? (string) BusinessSettings::getSiteUrl() : ''),
        'social' => wf_social_links()
    ];

    // 5. Determine background based on current page
    // Each page now has its own dedicated background setting in the database
    $bgRoomType = 'A'; // default to landing page
    // Prefer the 'path' parameter from frontend over REQUEST_URI (which is the API endpoint path)
    $reqPath = $_GET['path'] ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $reqPath = strtolower(trim(urldecode($reqPath), '/'));
    $roomIdParam = $_GET['room_id'] ?? null;

    if ($roomIdParam === 'S' || strpos($reqPath, 'shop') !== false) {
        $bgRoomType = 'S';
    } elseif ($roomIdParam === 'X' || strpos($reqPath, 'admin') !== false) {
        $bgRoomType = 'X';
    } elseif (strpos($reqPath, 'about') !== false) {
        $bgRoomType = 'about';
    } elseif (strpos($reqPath, 'contact') !== false) {
        $bgRoomType = 'contact';
    } elseif ($roomIdParam === '0' || strpos($reqPath, 'room_main') !== false) {
        $bgRoomType = '0';
    } elseif ($roomIdParam === 'A' || $reqPath === '' || $reqPath === 'index.html' || strpos($reqPath, 'landing') !== false) {
        $bgRoomType = 'A';
    }

    $backgroundUrl = function_exists('get_active_background') ? ('/' . ltrim(get_active_background($bgRoomType), '/')) : '/images/backgrounds/background-roomA.webp';

    // 6. Response
    wf_bootstrap_emit([
        'auth' => [
            'isLoggedIn' => $isLoggedIn,
            'user_id' => $user_id,
            'userData' => $userData
        ],
        'site_settings' => $site_settings,
        'branding' => [
            'tokens' => $branding_tokens,
            'style' => $branding_style
        ],
        'shop_data' => $shop_data,
        'about_data' => $about_data,
        'contact_data' => $contact_data,
        'background_url' => $backgroundUrl,
        'timestamp' => time()
    ], 200);
} catch (\Throwable $e) {
    error_log('[bootstrap] request failed: ' . $e->getMessage());
    wf_bootstrap_emit([
        'auth' => ['isLoggedIn' => false, 'user_id' => null, 'userData' => []],
        'site_settings' => [],
        'branding' => ['tokens' => [], 'style' => ''],
        'shop_data' => null,
        'about_data' => null,
        'contact_data' => null,
        'background_url' => '/images/backgrounds/background-roomA.webp',
        'timestamp' => time(),
        'error' => 'bootstrap_fallback_runtime'
    ], 200);
}
