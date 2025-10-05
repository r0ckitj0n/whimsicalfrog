<?php

// Centralized session initialization with consistent cookie params across apex and www
require_once __DIR__ . '/includes/session.php';
// Enforce canonical host to avoid cookie splits between apex and www
try {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host && stripos($host, 'www.whimsicalfrog.us') === 0) {
        $scheme = 'https';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $target = $scheme . '://whimsicalfrog.us' . $uri;
        header('Location: ' . $target, true, 301);
        exit;
    }
} catch (\Throwable $e) { /* non-fatal */
}
// Derive base domain like whimsicalfrog.us from host (www.whimsicalfrog.us -> whimsicalfrog.us)
$host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
if (strpos($host, ':') !== false) {
    $host = explode(':', $host)[0];
}
$parts = explode('.', $host);
$baseDomain = $host;
if (count($parts) >= 2) {
    $baseDomain = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
}
$cookieDomain = '.' . $baseDomain; // works for apex and www
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
    (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
    (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
);
session_init([
    'name' => 'PHPSESSID',
    'lifetime' => 0,
    'path' => '/',
    'domain' => $cookieDomain,
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'None',
]);
// Mark that other scripts are included from index for security checks
if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

// Centralized configuration and essential functions
require_once __DIR__ . '/api/config.php';
error_log('DEBUG: index.php - after config.php');
require_once __DIR__ . '/includes/functions.php';
error_log('DEBUG: index.php - after functions.php');
require_once __DIR__ . '/includes/auth.php';
error_log('DEBUG: index.php - after auth.php');
// Reconstruct session from WF_AUTH (if present) before rendering anything
try {
    ensureSessionStarted();
} catch (\Throwable $e) {
}
require_once __DIR__ . '/includes/vite_helper.php';
error_log('DEBUG: index.php - after vite_helper.php');

// Resolve requested page (clean URLs take precedence over ?page=)
$requestUri = $_SERVER['REQUEST_URI'];
error_log('DEBUG index.php: REQUEST_URI = ' . $requestUri);
$path = parse_url($requestUri, PHP_URL_PATH);
error_log('DEBUG index.php: parsed path = ' . $path);
$slug = trim($path, '/');
error_log('DEBUG index.php: slug = "' . $slug . '"');
if ($slug === '') {
    $page = 'landing';
    error_log('DEBUG index.php: slug is empty, setting page = landing');
} else {
    // Only take the first segment (e.g., /room/1 => room)
    $segments = explode('/', $slug);
    $page = $segments[0];
    error_log('DEBUG index.php: slug not empty, segments = ' . print_r($segments, true) . ', setting page = ' . $page);
}
error_log('DEBUG index.php: final page = ' . $page);

// Fallback to query string if still not determined
if (isset($_GET['page'])) {
    $page = $_GET['page'];
}

// If it's an admin page, ensure the user is authenticated
// DEVELOPMENT BYPASS: Temporarily disable auth for localhost
$isDev = strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;
if (!$isDev && ((strpos($page, 'admin_') === 0) || (strpos($requestUri, '/admin') === 0))) {
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        header('Location: /login?redirect_to=' . urlencode($requestUri));
        exit;
    }
}

// Handle AJAX room content requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' &&
    isset($_GET['room'])) {

    // This is an AJAX request for room content
    $roomNumber = $_GET['room'];

    // Load the room content API functionality
    require_once __DIR__ . '/api/load_room_content.php';
    exit; // Stop further processing as the room content API handles the response
}

// Routing logic
// Determine page path; special-case the admin hub
if ($page === 'admin') {
    // Determine admin subsection from URL (e.g., /admin/orders)
    $segments = explode('/', $slug);
    $adminSection = $segments[1] ?? 'dashboard';
    // Normalize common aliases
    $aliases = [
        'index' => 'dashboard',
        'home' => 'dashboard',
        'order' => 'orders',
        'product' => 'inventory',
        'products' => 'inventory',
        'inventory' => 'inventory',
        'customer' => 'customers',
        'users' => 'customers',
        'report' => 'reports',
        'marketing' => 'marketing',
        'pos' => 'pos',
        'settings' => 'settings',
        'categories' => 'categories'
    ];
    $key = strtolower($adminSection);
    if (isset($aliases[$key])) {
        $adminSection = $aliases[$key];
    }
    // Support query param fallback when pretty URLs aren't available
    // Example: /?page=admin&section=inventory
    if (isset($_GET['section']) && is_string($_GET['section']) && $_GET['section'] !== '') {
        $q = strtolower($_GET['section']);
        if (isset($aliases[$q])) {
            $adminSection = $aliases[$q];
        } else {
            $adminSection = $q;
        }
    }
    $page_path = __DIR__ . '/sections/admin_router.php';
} else {
    // Dynamic room pages: map room1..roomN to the universal room template
    if (preg_match('/^room\d+$/i', (string)$page)) {
        // Ensure the room identifier is visible to the template
        $_GET['page'] = $page;
        $page_path = __DIR__ . '/sections/room_template.php';
    } else {
        $page_path = __DIR__ . '/' . $page . '.php';
    }
}

// If visiting the shop page, preload categories so shop.php always has $categories defined
if ($page === 'shop') {
    require_once __DIR__ . '/includes/shop_data_loader.php';
}

// Start output buffering
ob_start();

// Determine if we should skip the global layout (header/footer)
// Use a conservative scope: only honor for the canonical receipt page
// when explicitly requested via bare/embed/modal/print flags.
$__wf_skip_layout = false;
try {
    $p = strtolower((string)($page ?? ''));
    if ($p === 'receipt') {
        $qs = $_GET ?? [];
        $__wf_skip_layout = (
            (isset($qs['bare']) && $qs['bare'] === '1') ||
            (isset($qs['embed']) && $qs['embed'] === '1') ||
            (isset($qs['modal']) && $qs['modal'] === '1') ||
            (isset($qs['print']) && $qs['print'] === '1')
        );
    }
} catch (\Throwable $e) { $__wf_skip_layout = false; }

// Include the header unless skipping layout for a bare receipt render
if (!$__wf_skip_layout) {
    include __DIR__ . '/partials/header.php';
}

// Main content inclusion
if (file_exists($page_path)) {
    include $page_path;
} else {
    // If missing page, show generic under construction notice
    http_response_code(200);
    include __DIR__ . '/under_construction.php';
}

// Include the footer unless skipping layout for a bare receipt render
if (!$__wf_skip_layout) {
    include __DIR__ . '/partials/footer.php';
}

// Get the buffered content and end buffering
$content = ob_get_clean();

// Output the final content
echo $content;
