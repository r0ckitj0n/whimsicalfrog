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

    // Fetch items data with comprehensive field mapping
    $stmt = $pdo->query('SELECT sku, sku AS inventoryId, name AS productName, stockLevel, retailPrice, description,
                                category AS productType FROM items ORDER BY category, name');
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

$isFullscreenPage = in_array($page, ['landing']);
if ($isFullscreenPage) {
    $bodyClass .= ' body-fullscreen-layout';
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

// Get background style
if ($backgroundRoomType === 'landing' && function_exists('get_landing_background_path')) {
    $backgroundUrl = get_landing_background_path();
} else {
    $backgroundUrl = get_active_background($backgroundRoomType);
}
$backgroundStyle = !empty($backgroundUrl) ? "style=\"background-image: url('{$backgroundUrl}');\"" : '';
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
    
    <!- Dynamic CSS Placeholders ->
    <!- Database CSS style elements removed - using static CSS files only ->
    
    <!- Z-Index Hierarchy CSS ->
    
    
    <!-- Vite CSS build -->
<?php if (file_exists('dist/assets/app.css')): ?>
    <link href="dist/assets/app.css?v=<?php echo filemtime('dist/assets/app.css'); ?>" rel="stylesheet">
<?php endif; ?>

<!-- Core CSS Bundle -->
    <link href="css/bundle.css?v=<?php echo filemtime('css/bundle.css'); ?>" rel="stylesheet">
    <link href="css/room-iframe.css?v=<?php echo filemtime(__DIR__ . '/css/room-iframe.css'); ?>" rel="stylesheet">
    <!-- Room iframe backgrounds CSS -->
    
    <!- Static CSS Rules + Essential Styles ->
    
    <!- Search Input Styling ->
    
    

    <!- All styling now handled by static CSS system ->
    
    <!- Static Global CSS Variables ->
    
    
    <!- Static Tooltip CSS ->
    
    
    <script>
        // Static CSS system - no database dependencies
        // Global CSS variables loaded from js/css-initializer.js
        // Tooltip CSS loaded from static CSS files
        console.log('Using static CSS system');
        // Vite dev server tag will be appended below

    </script>
    
    <!- Core Layout Styles ->
    
    
    <!- Database CSS loading removed - using static CSS files only ->
    

    
<?php if ($page === 'landing' && file_exists('css/landing.css')): ?>
    <link href="css/landing.css?v=<?php echo filemtime('css/landing.css'); ?>" rel="stylesheet">
<?php endif; ?>

    <!- Main Application Script ->
    

</head>
<body <?php echo $backgroundStyle; ?> class="<?php echo $page; ?>-page flex flex-col min-h-screen <?php echo $bodyClass; ?>">
<!- Main Navigation ->
<?php if ($page !== 'landing'): ?>
<nav class="main-nav site-header">
    <div class="header-container">
        <div class="header-content">
            <!- Logo and Tagline ->
            <div class="header-left">
                <a href="/?page=landing" class="logo-link">
                    <?php echo getImageTag('images/logos/logo_whimsicalfrog.png', 'Whimsical Frog', 'header-logo'); ?>
                    <span class="logo-text">Whimsical Frog</span>
                </a>
                <span class="logo-tagline">Discover unique custom crafts, made with love.</span>
            </div>
            
            <!- Search Bar ->
            <?php if ($showSearchBar): ?>
            <div class="header-center">
                <div class="search-container">
        <div class="search-input-container">
                    <input type="text" id="headerSearchInput" placeholder="Search products..." 
                           class="search-bar"
                           data-focus-action="handleFormFocus"
                           data-blur-action="handleFormBlur">
                    <div class="search-icon">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="header-center"></div>
            <?php endif; ?>
            
            <!- Navigation Links ->
            <div class="header-right">
                    <?php if ($isAdmin): ?>
                        <a href="/?page=admin" class="nav-link">Manage</a>
                    <?php endif; ?>
                    <a href="/?page=shop" class="nav-link">Shop</a>
                    <?php if ($isLoggedIn): ?>
                        <span class="welcome-message">
                            <a href="/?page=account_settings" class="nav-link"><?php echo htmlspecialchars($welcomeMessage); ?></a>
                        </span>
                        <a href="/logout.php" class="nav-link">Logout</a>
                    <?php else: ?>
                        <a href="/?page=login" class="nav-link">Login</a>
                    <?php endif; ?>
                    <a href="/?page=cart" class="nav-link cart-link">
                        <div class="flex items-center space-x-1 md:space-x-2">
                            <span id="cartCount" class="text-sm font-medium whitespace-nowrap"><?php echo $cartCount; ?> items</span>
                            <svg class="w-5 h-5 md:w-6 md:h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span id="cartTotal" class="text-sm font-medium whitespace-nowrap hidden md:inline"><?php echo $formattedCartTotal; ?></span>
                        </div>
                    </a>
                </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<!- Main Content Area ->
<?php
// Determine template file path (supports both new root-level files and legacy sections/ folder)
$pageFile = "sections/{$page}.php";
if (!file_exists($pageFile)) {
    $pageFile = "{$page}.php"; // fallback to root-level template
}
?>
<?php if ($isFullscreenPage): ?>
    <div class="fullscreen-container" <?php echo $backgroundStyle; ?>>
        <?php include $pageFile; ?>
    </div>
<?php else: ?>
    <main class="md:p-4 lg:p-6 cottage-bg" id="mainContent" <?php echo $backgroundStyle; ?>>
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
    // In debug mode, load Vite dev server directly; individual legacy scripts are no longer necessary
    echo "<script type='module' src='http://localhost:5173/src/main.js'></script>\n";
} else {
    // Production mode: load global popup plus built bundle
    // Ensure popup system is available before iframe bridges call it
    echo "<script type='module' src='src/ui/globalPopup.js?v=" . filemtime('src/ui/globalPopup.js') . "'></script>\n";
    

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

<!- Dynamic Background Loading ->
        <script>window.WF_BUNDLE_LOADED = true;</script>
<script src="js/api-client.js?v=<?php echo filemtime('js/api-client.js'); ?>"></script>
    <script src="js/bundle.js?v=<?php echo filemtime('js/bundle.js'); ?>"></script>
    <script src="js/main-app.js?v=<?php echo filemtime('js/main-app.js'); ?>"></script>
    <script src="js/room-modal-manager.js?v=<?php echo filemtime('js/room-modal-manager.js'); ?>"></script>
    <!-- Dynamic Background Loader -->
    <script src="js/dynamic-background-loader.js?v=<?php echo filemtime('js/dynamic-background-loader.js'); ?>"></script>
</body>
</html>