<?php
session_start();
// ob_start(); // Output buffering can be re-enabled if needed
// Error reporting & logging are configured centrally in api/config.php

// Load centralized systems
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/background_helpers.php';
require_once __DIR__ . '/api/marketing_helper.php';

// Get page parameter
$page = isset($_GET['page']) ? $_GET['page'] : 'landing';

// Define allowed pages
$allowed_pages = [
    'landing', 'room_main', 'shop', 'cart', 'login', 'register', 'admin', 'admin_inventory',
    'admin_customers', 'admin_orders', 'admin_reports', 'admin_marketing', 'admin_settings',
    'account_settings', 'receipt'
];

// Validate page parameter
if (!in_array($page, $allowed_pages)) {
    $page = 'landing';
}

// Authentication
$isLoggedIn = isLoggedIn();
$isAdmin = isAdmin();
$userData = getCurrentUser() ?? [];
$welcomeMessage = $isLoggedIn ? getUsername() : '';

// Cart authentication check - must be done before any content output
if ($page === 'cart' && !$isLoggedIn) {
    // Store the cart redirect intent
    $_SESSION['redirect_after_login'] = '/?page=cart';

    // Redirect to login page
    header('Location: /?page=login');
    exit;
}

// Admin page access control - development-friendly
if (strpos($page, 'admin') === 0 && !$isAdmin && !isAdminWithToken()) {
    // In development mode (localhost), allow admin access without strict authentication
    $isDevelopment = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) ||
                     (strpos($_SERVER['SERVER_NAME'] ?? '', 'localhost') !== false) ||
                     ($_SERVER['SERVER_ADDR'] ?? '') === '127.0.0.1';

    if (!$isDevelopment) {
        header('Location: /?page=login');
        exit;
    }
}

define('INCLUDED_FROM_INDEX', true);



// Initialize logging systems
if (class_exists('DatabaseLogger')) {
    DatabaseLogger::init();
}

if (class_exists('ErrorLogger')) {
    ErrorLogger::init();
}

// Log page view for analytics
if (class_exists('DatabaseLogger')) {
    $pageUrl = $_SERVER['REQUEST_URI'] ?? '/?page=' . $page;
    $eventData = [
        'page' => $page,
        'user_type' => $isAdmin ? 'admin' : ($isLoggedIn ? 'user' : 'guest'),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    DatabaseLogger::logPageView($pageUrl, $eventData);
}

// Note: getImageTag() function is now centralized in includes/functions.php

// Database-driven content initialization
$categories = [];
$inventory = [];
$showSearchBar = true;

try {
    try {
        $pdo = Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Fetch items data with comprehensive field mapping including custom button text (live items only)
    $stmt = $pdo->query('SELECT sku, sku AS inventoryId, name AS productName, stockLevel, retailPrice, description,
                                category AS productType, custom_button_text FROM items WHERE status = "live" ORDER BY category, name');
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($products && is_array($products)) {
        foreach ($products as $product) {
            if (!isset($product['productType'])) {
                continue;
            }
            $category = $product['productType'];
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }

            // Normalize field names for compatibility across all sections
            $product['productId'] = $product['sku'];
            $product['price'] = $product['retailPrice'];
            $product['basePrice'] = $product['retailPrice'];
            $product['stock'] = (int)$product['stockLevel'];
            $product['imageUrl'] = '';
            $product['id'] = $product['sku'];
            $product['name'] = $product['productName'] ?? null;
            $product['image'] = '';

            $categories[$category][] = $product;
        }
    }

    // Get items for admin sections
    $stmt = $pdo->query('SELECT * FROM items ORDER BY category, name');
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Database-driven search bar visibility
    function shouldShowSearchBar($pdo, $currentPage)
    {
        $pageToRoomMap = [
            'landing' => 0, 'room_main' => 1
        ];

        if (!isset($pageToRoomMap[$currentPage])) {
            return true;
        }

        try {
            $stmt = $pdo->prepare("SELECT show_search_bar FROM room_settings WHERE room_number = ? AND is_active = 1");
            $stmt->execute([$pageToRoomMap[$currentPage]]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (bool)$result['show_search_bar'] : true;
        } catch (PDOException $e) {
            return true;
        }
    }

    $showSearchBar = shouldShowSearchBar($pdo, $page);

} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
}

// Set body classes
$bodyClass = '';
if ($page === 'landing') {
    $bodyClass = 'is-landing';
} elseif (strpos($page, 'admin') === 0) {
    $bodyClass = 'is-admin admin-page';
    // Add POS-specific body class to hide main header
    if ($page === 'admin_pos' || ($page === 'admin' && isset($_GET['section']) && $_GET['section'] === 'pos')) {
        $bodyClass .= ' is-pos-page';
    }
}

// Cart handling
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cartCount = 0;
$cartTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['quantity'];
    $cartTotal += $item['price'] * $item['quantity'];
}

$formattedCartTotal = '$' . number_format($cartTotal, 2);

// Generate dynamic SEO
$currentSku = $_GET['product'] ?? null;
$seoData = generatePageSEO($page, $currentSku);

// Determine the room type for the background
$backgroundRoomType = 'landing'; // Default
if ($page === 'room_main' || $page === 'shop' || $page === 'cart' || $page === 'login' || strpos($page, 'admin') === 0) {
    $backgroundRoomType = 'room_main';
} elseif ($page === 'landing') {
    $backgroundRoomType = 'landing';
}

// Set body classes for fullscreen pages
$isFullscreenPage = in_array($page, ['landing', 'room_main', 'shop']);
if ($isFullscreenPage) {
    $bodyClass .= ' body-fullscreen-layout';
    // Add background classes to body instead of inline styles
    if ($backgroundRoomType === 'room_main') {
        $bodyClass .= ' room-bg-main';
    } elseif ($backgroundRoomType === 'landing') {
        $bodyClass .= ' room-bg-landing';
    }
}

// Vite Asset Helper for PHP
// Handles both development and production modes.
function vite(string $entry): string
{
    static $manifest = null;
    static $isDev = null;

    $viteDevServer = 'http://localhost:5173'; // As defined in vite.config.js

    if ($isDev === null) {
        // Check if the Vite dev server is running by trying to open a connection
        $handle = @fopen($viteDevServer, 'r');
        if ($handle !== false) {
            $isDev = true;
            fclose($handle);
        } else {
            $isDev = false;
        }
    }

    if ($isDev) {
        return '<script type="module" src="' . $viteDevServer . '/@vite/client"></script>' .
               '<script type="module" src="' . $viteDevServer . '/' . $entry . '"></script>';
    }

    // Production mode: load assets from the manifest
    if ($manifest === null) {
        $manifestPath = __DIR__ . '/dist/.vite/manifest.json';
        if (!file_exists($manifestPath)) {
            return ''; // or throw an exception
        }
        $manifest = json_decode(file_get_contents($manifestPath), true);
    }

    $html = '';
    if (isset($manifest[$entry])) {
        // Add CSS links
        if (!empty($manifest[$entry]['css'])) {
            foreach ($manifest[$entry]['css'] as $cssFile) {
                $html .= '<link rel="stylesheet" href="/dist/' . $cssFile . '">';
            }
        }
        // Add the main JS script
        $html .= '<script type="module" src="/dist/' . $manifest[$entry]['file'] . '"></script>';
    }

    return $html;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seoData['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seoData['description']) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($seoData['keywords']) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seoData['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seoData['description']) ?>">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($seoData['title']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($seoData['description']) ?>">
    
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <!- External Dependencies ->
    
    
    <link href="https://fonts.googleapis.com/css2?family=Merienda:wght@400;700&display=swap" rel="stylesheet">
    
    <?= vite('js/main.js') ?>

    <!- Main Application Script ->



</head>
<body class="<?php echo $page; ?>-page flex flex-col min-h-screen <?php echo $bodyClass; ?>">
<!-- Universal Page Header -->
<?php if ($page !== 'landing'): ?>
<?php
// Configure header for current page
$header_config = [
    'show_search' => $showSearchBar,
    'show_cart' => true,
    'show_user_menu' => true,
    'show_logo' => true,
    'logo_text' => 'Whimsical Frog',
    'logo_tagline' => ($page === 'room_main') ? 'Discover unique custom crafts, made with love.' : 'Enchanted Treasures',
    'logo_image' => 'images/logos/logo_whimsicalfrog.png',
    'navigation_items' => [
        ['label' => 'Shop', 'url' => '/?page=shop', 'active' => ($page === 'shop')],
    ],
    'search_placeholder' => ($page === 'room_main') ? 'Search products...' : 'Search magical items...'
];

// Room main image link moved to individual page content areas instead of header
// This was causing background styling conflicts in the header navigation
/*
// Add Rooms image link for non-room_main pages
if ($page !== 'room_main') {
    $header_config['navigation_items'][] = [
        'label' => '<picture><source srcset="images/signs/sign_main.webp" type="image/webp"><img src="images/signs/sign_main.png" alt="Rooms" class="nav-rooms-image"></picture>',
        'url' => '/?page=room_main',
        'active' => false,
        'is_image' => true
    ];
}
*/

// Add admin link for admin users
if (function_exists('isAdmin') && isAdmin()) {
    array_unshift($header_config['navigation_items'], [
        'label' => 'Manage',
        'url' => '/?page=admin',
        'active' => (strpos($page, 'admin') === 0)
    ]);
}

include 'components/header_template.php';
endif; ?>

<!- Main Content Area ->
<?php
// Determine template file path (supports both new root-level files and legacy sections/ folder)
$pageFile = "sections/{$page}.php";
if (!file_exists($pageFile)) {
    $pageFile = "{$page}.php"; // fallback to root-level template
}
?>
<?php if ($isFullscreenPage): ?>
    <div class="fullscreen-container">
        <?php include $pageFile; ?>
    </div>
<?php else: ?>
    <main class="md:p-4 lg:p-6 cottage-bg page-content" id="mainContent">
        <?php
        // Use resolved $pageFile unless admin routing overrides

        // Route all admin pages through the main admin handler for consistent navbar
        if (strpos($page, 'admin_') === 0) {
            // Direct admin_* pages map to a section under the main admin handler
            $_GET['section'] = substr($page, strlen('admin_'));
            include 'admin/admin.php';
        } elseif ($page === 'admin') {
            // Main admin dashboard
            include 'admin/admin.php';
        } elseif (file_exists($pageFile)) {
            include $pageFile;
        } else {
            echo '<div class="text-center"><h1 class="text-2xl font-bold text-red-600">Page not found</h1></div>';
        }
    ?>
    </main>
<?php endif; ?>



<!- Product Modal ->
<div id="productModal" class="modal">
    <div class="modal-content">
        <span class="close-button" data-action="closeProductModal">&times;</span>
        <div id="modalContent" class="">
            <!- Content will be dynamically inserted here ->
        </div>
    </div>
    </div>
</div>

<?php
// Include global popup component
include_once 'components/global_popup.php';
echo renderGlobalPopupCSS();
echo renderGlobalPopup();
?>

<!- WhimsicalFrog Unified JavaScript System ->
<?php
$debug_js = isset($_GET['debug_js']);

if ($debug_js) {
    // In debug mode, load Vite dev server directly
    echo "<script type='module' src='http://localhost:5173/src/main.js'></script>\n";
} else {
    // Production mode: load the built JS from Vite manifest
    if (!empty($viteAssets['js'])) {
        echo $viteAssets['js'];
    } else {
        // Fallback for development or if manifest is missing
        echo '<script type="module" src="/js/bundle.js?v=' . filemtime(__DIR__ . '/js/bundle.js') . '"></script>';
    }
}
?>

<!- Admin-specific Scripts ->
<?php if (strpos($page, 'admin') === 0): ?>
<script>
// Database-driven tooltip system initialization
async function initializeTooltipSystem() {
    try {
        // Load tooltip JS from database
        const response = await fetch('/api/help_tooltips.php?action=generate_js');
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        const data = await response.json();
        if (data.success && data.js_content) {
            // Execute the generated JavaScript
            const script = document.createElement('script');
            script.textContent = data.js_content;
            document.head.appendChild(script);
            console.log('✅ Tooltip system initialized successfully');
        } else {
            throw new Error(data.message || 'Tooltip JS generation failed');
        }
    } catch (error) {
        console.error('❌ Failed to initialize tooltip system:', error);
        // Fallback to basic tooltip functionality
        console.log('🔄 Using basic tooltip fallback');
    }
}

// Initialize tooltips when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeTooltipSystem();
});
</script>
<?php endif; ?>

<!- WebP Support Detection ->
<script>
    (function(){
        var d=document.createElement('div');
        d.innerHTML='<img src="data:image/webp;base64,UklGRjIAAABXRUJQVlA4ICYAAACyAgCdASoCAAEALmk0mk0iIiIiIgBoSygABc6zbAAA/v56QAAAAA==\" onerror=\"document.documentElement.className += \' no-webp\';\" onload=\"document.documentElement.className += \' webp\';\">';
    })();
</script>

<!-- All legacy scripts removed. JavaScript is now exclusively handled by the Vite build process. -->
</body>
</html>