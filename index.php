<?php
session_start();
// ob_start(); // Temporarily removed for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load centralized systems
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/api/marketing_helper.php';

// Get page parameter
$page = isset($_GET['page']) ? $_GET['page'] : 'landing';

// Define allowed pages
$allowed_pages = [
    'landing', 'room_main', 'shop', 'cart', 'login', 'register', 'admin', 'admin_inventory',
    'room2', 'room3', 'room4', 'room5', 'room6',
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
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    
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
    function shouldShowSearchBar($pdo, $currentPage) {
        $pageToRoomMap = [
            'landing' => 0, 'room_main' => 1, 'room2' => 2, 'room3' => 3, 
            'room4' => 4, 'room5' => 5, 'room6' => 6
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
} elseif (in_array($page, ['room2', 'room3', 'room4', 'room5', 'room6'])) {
    $bodyClass = $page; // Add room-specific class (room2, room3, etc.)
} elseif ($page === 'room_main') {
    // Check if main room fullscreen is enabled
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM business_settings WHERE setting_key = 'main_room_fullscreen' AND category = 'rooms'");
        $stmt->execute();
        $fullScreenSetting = $stmt->fetch(PDO::FETCH_ASSOC);
        $isMainRoomFullscreen = $fullScreenSetting ? filter_var($fullScreenSetting['setting_value'], FILTER_VALIDATE_BOOLEAN) : false;
        
        if ($isMainRoomFullscreen) {
            $bodyClass = 'main-room-fullscreen';
        }
    } catch (Exception $e) {
        error_log("Error checking main room fullscreen setting: " . $e->getMessage());
    }
}

// Add room_main to fullscreen pages when fullscreen mode is enabled
$fullscreenPages = ['landing'];
if ($page === 'room_main' && $isMainRoomFullscreen) {
    $fullscreenPages[] = 'room_main';
}

$isFullscreenPage = in_array($page, $fullscreenPages);
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
    
    <!-- External Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Merienda:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Database-Generated CSS + Essential Rules -->
    
    <!-- All CSS now generated from database -->
    <style id="consolidated-css">
        <?php
        // Load CSS directly from database (no HTTP call to avoid infinite loop)
        try {
            $db = Database::getInstance();
            $rules = $db->query("SELECT rule_name, css_property, css_value, category FROM global_css_rules WHERE is_active = 1 ORDER BY category, rule_name")->fetchAll(PDO::FETCH_ASSOC);
            
            // Generate CSS from database rules using corrected logic
            $cssOutput = "/* Database-Generated CSS Rules */\n";
            $currentCategory = '';
            $groupedRules = [];
            
            // Group rules by selector
            foreach ($rules as $rule) {
                // Extract selector from rule_name (format: ".selector { property }")
                if (preg_match('/^(.+?)\s*\{\s*(.+?)\s*\}$/', $rule['rule_name'], $matches)) {
                    $selector = trim($matches[1]);
                    
                    if (!isset($groupedRules[$selector])) {
                        $groupedRules[$selector] = [];
                    }
                    
                    $groupedRules[$selector][] = [
                        'property' => $rule['css_property'],
                        'value' => $rule['css_value'],
                        'category' => $rule['category']
                    ];
                }
            }
            
            // Generate CSS from grouped rules
            $currentCategory = '';
            foreach ($groupedRules as $selector => $properties) {
                // Add category comment
                if (!empty($properties) && $properties[0]['category'] !== $currentCategory) {
                    $currentCategory = $properties[0]['category'];
                    $cssOutput .= "\n/* " . ucfirst($currentCategory) . " */\n";
                }
                
                $cssOutput .= "{$selector} {\n";
                foreach ($properties as $prop) {
                    $cssOutput .= "    {$prop['property']}: {$prop['value']};\n";
                }
                $cssOutput .= "}\n\n";
            }
            
            echo $cssOutput;
            
        } catch (Exception $e) {
            // Hard fail - no fallback CSS
            http_response_code(500);
            echo "/* FATAL ERROR: Database CSS generation failed: " . htmlspecialchars($e->getMessage()) . " */\n";
            echo "/* Database connection required for CSS loading. Please check database connection. */\n";
            // Stop execution to prevent mixed results
            exit('Database connection failed. Please refresh the page or contact support.');
        }
        
        // Add essential unique CSS rules that can't be in database
        echo "
/* Essential Animations & Interactions */
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes btn-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes slideInFromTop { from { opacity: 0; transform: translateX(-50%) translateY(-20px); } to { opacity: 1; transform: translateX(-50%) translateY(0); } }

/* Essential Button States */
.btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); }
.btn:active { transform: translateY(1px); }
.btn:focus { outline: 2px solid transparent; box-shadow: 0 0 0 3px rgba(135,172,58,0.3); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* Essential Responsive Design */
@media (max-width: 768px) {
  .admin-tab-navigation { flex-direction: column; gap: 0.5rem; }
  .form-grid { grid-template-columns: 1fr; }
  .nav-arrow { display: none; }
}

@media (max-width: 480px) {
  .toast-notification { top: 1rem; right: 1rem; left: 1rem; max-width: none; }
}

/* Essential Interactive Elements */
.editable:hover::after { content: '✏️'; position: absolute; right: 5px; top: 50%; transform: translateY(-50%); font-size: 12px; opacity: 0.5; }
.btn-loading::before { content: ''; position: absolute; top: 50%; left: 50%; width: 1rem; height: 1rem; margin: -0.5rem 0 0 -0.5rem; border: 2px solid transparent; border-top-color: currentColor; border-radius: 50%; animation: btn-spin 1s linear infinite; }
";
        ?>
    </style>

    <!-- All styling now handled by database-driven CSS system -->
    
    <!-- Database-driven Global CSS Variables -->
    <style id="global-css-variables">
        /* Global CSS variables will be loaded from database */
    </style>
    
    <!-- Database-driven Tooltip CSS -->
    <style id="tooltip-css">
        /* Tooltip styles will be loaded from database */
    </style>
    
    <script>
        // Load global CSS variables from database
        async function loadGlobalCSS() {
            try {
                const response = await fetch('/api/global_css_rules.php?action=generate_css');
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                const data = await response.json();
                if (data.success && data.css_content) {
                    const styleElement = document.getElementById('global-css-variables');
                    if (styleElement) {
                        styleElement.textContent = data.css_content;
                        console.log('✅ Global CSS loaded successfully:', data.css_content.length + ' characters');
                    }
                } else {
                    throw new Error(data.message || 'CSS generation failed');
                }
            } catch (error) {
                console.error('❌ FATAL: Failed to load global CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; left: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>Global CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
            }
        }

        // Load CSS immediately when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            loadGlobalCSS();
            loadTooltipCSS();
        });
        
        // Load tooltip CSS from database
        async function loadTooltipCSS() {
            try {
                const response = await fetch('/api/help_tooltips.php?action=generate_css');
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                const data = await response.json();
                if (data.success && data.css_content) {
                    const styleElement = document.getElementById('tooltip-css');
                    if (styleElement) {
                        styleElement.textContent = data.css_content;
                        console.log('✅ Tooltip CSS loaded successfully:', data.css_content.length + ' characters');
                    }
                } else {
                    throw new Error(data.message || 'Tooltip CSS generation failed');
                }
            } catch (error) {
                console.error('❌ FATAL: Failed to load tooltip CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 40px; left: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>Tooltip CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
            }
        }
        
        loadGlobalCSS();
        loadTooltipCSS();
    </script>
    
    <!-- Core Layout Styles -->
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body, html {
            color: #222 !important;
            background: #333;
            width: 100%;
            height: 100%;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        body {
            font-family: 'Merienda', cursive;
            background-image: url('images/home_background.png?v=cb2');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100%;
        }
        
        /* WebP Support */
        .webp body {
            background-image: url('images/home_background.webp?v=cb2');
        }
        
        /* Dynamic backgrounds will be set by JavaScript */
        body.dynamic-bg-loaded {
            /* Background set dynamically */
        }
        
        /* Non-landing pages */
        body:not(.is-landing) {
            background-image: url('images/room_main.png?v=cb2');
        }
        
        .webp body:not(.is-landing) {
            background-image: url('images/room_main.webp?v=cb2');
        }
        
        /* All navigation styles moved to database-driven CSS system */
        
        /* Custom Alert System */
        .custom-alert {
            display: none;
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #87ac3a;
            color: #ffffff;
            padding: 20px 30px;
            border: 2px solid #6b8e23;
            border-radius: 12px;
            z-index: 99999;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            text-align: center;
            font-weight: 600;
            font-size: 16px;
            min-width: 300px;
            animation: slideInFromTop 0.3s ease-out;
        }
        
        @keyframes slideInFromTop {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 1200px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .close-button {
            position: absolute;
            right: 20px;
            top: 10px;
            color: #556B2F;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-button:hover {
            color: #6B8E23;
        }
        
        /* Landing page specific */
        body.is-landing a[href="/?page=shop"],
        body.is-landing a[href="/?page=cart"],
        body.is-landing a[href="/?page=login"] {
            display: none !important;
        }
        
        /* Fullscreen layout */
        .body-fullscreen-layout {
            overflow: hidden;
        }
        
        .body-fullscreen-layout #mainContent {
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
            height: 100vh !important;
        }
        
        .fullscreen-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            overflow: hidden;
        }
        
        /* Utility Classes */
        .cottage-bg {
            background: transparent;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen <?php echo $bodyClass; ?>">

<!-- Custom Alert System -->
<div id="customAlertBox" class="custom-alert">
    <p id="customAlertMessage"></p>
    <button onclick="document.getElementById('customAlertBox').style.display = 'none';" 
            class="mt-3 px-6 py-2 bg-[#87ac3a] text-white rounded-lg hover:bg-[#a3cc4a] font-semibold border-2 border-[#87ac3a] transition-all duration-200">OK</button>
</div>

<!-- Main Navigation -->
<nav class="main-nav sticky top-0 z-50 transition-all duration-300 ease-in-out">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between w-full py-1">
            <!-- Logo and Tagline -->
            <div class="flex-none">
                <div class="flex items-center">
                    <a href="/?page=landing" class="flex items-center text-2xl font-bold font-merienda">
                        <?= getImageTag('images/sign_whimsicalfrog.webp', 'Whimsical Frog', 'h-12 mr-2') ?>
                    </a>
                    <div>
                        <p class="text-sm font-merienda ml-2 hidden md:block tagline">Discover unique custom crafts, made with love.</p>
                        <?php if ($isLoggedIn && !empty($welcomeMessage)): ?>
                            <p class="welcome-message">
                                <a href="/?page=account_settings" class="hover:underline text-[#87ac3a]" title="Edit your account settings"><?php echo htmlspecialchars($welcomeMessage); ?></a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Search Bar -->
            <?php if ($showSearchBar): ?>
            <div class="flex-grow flex justify-center">
                <div class="relative max-w-md w-full mx-4">
                    <input type="text" id="headerSearchInput" placeholder="Search products..." 
                           class="w-full px-4 py-2 pl-10 pr-4 text-sm bg-transparent border-2 rounded-full focus:outline-none focus:ring-2 transition-all duration-200 search-input">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #87ac3a;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <?php else: ?>
                <div class="flex-grow"></div>
            <?php endif; ?>
            
            <!-- Navigation Links -->
            <div class="flex-none">
                <div class="flex items-center">
                    <?php if ($isAdmin): ?>
                        <a href="/?page=admin" class="nav-link">Manage</a>
                    <?php endif; ?>
                    <a href="/?page=shop" class="nav-link">Shop</a>
                    <a href="/?page=cart" class="nav-link relative inline-flex items-center">
                        <div class="flex items-center space-x-1 md:space-x-2">
                            <span id="cartCount" class="text-sm font-medium whitespace-nowrap"><?php echo $cartCount; ?> items</span>
                            <svg class="w-5 h-5 md:w-6 md:h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span id="cartTotal" class="text-sm font-medium whitespace-nowrap hidden md:inline"><?php echo $formattedCartTotal; ?></span>
                        </div>
                    </a>
                    <?php if ($isLoggedIn): ?>
                        <a href="/logout.php" class="nav-link" onclick="logout(); return false;">Logout</a>
                    <?php else: ?>
                        <a href="/?page=login" class="nav-link">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content Area -->
<?php if ($isFullscreenPage): ?>
    <div class="fullscreen-container">
        <?php include "sections/{$page}.php"; ?>
    </div>
<?php else: ?>
    <main class="flex-grow container mx-auto p-2 md:p-4 lg:p-6 cottage-bg" id="mainContent">
        <?php 
        $pageFile = 'sections/' . $page . '.php';
        
        // Always load the main admin.php file for any admin page
        // It will handle section routing internally
        if (file_exists($pageFile)) {
            include $pageFile;
        } else {
            echo '<div class="text-center py-12"><h1 class="text-2xl font-bold text-red-600">Page not found</h1></div>';
        }
        ?>
    </main>
<?php endif; ?>

<!-- Product Modal -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="closeProductModal()">&times;</span>
        <div id="modalContent" class="p-4">
            <!-- Content will be dynamically inserted here -->
        </div>
    </div>
</div>

<!-- Core JavaScript Libraries -->
<script src="js/notification-css-loader.js?v=<?php echo time(); ?>"></script>
<script src="js/global-notifications.js?v=<?php echo time(); ?>"></script>
<script src="js/image-viewer.js?v=<?php echo time(); ?>"></script>

<!-- Page-specific Scripts -->
<?php 
// Load cart.js for non-admin pages AND admin pages that need cart functionality
$needsCart = ($page !== 'admin') || 
             ($page === 'admin' && isset($_GET['section']) && in_array($_GET['section'], ['pos'])) ||
             ($page === 'cart');

if ($needsCart): ?>
<script src="js/modal-manager.js?v=1751411847"></script>
<script src="js/cart.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>

<script src="js/global-item-modal.js?v=<?php echo time(); ?>"></script>
<script src="js/global-modals.js?v=<?php echo time(); ?>"></script>
<script src="js/sales-checker.js?v=<?php echo time(); ?>"></script>
<script src="js/search.js?v=<?php echo time(); ?>"></script>
<script src="js/analytics.js?v=<?php echo time(); ?>"></script>

<!-- Admin-specific Scripts -->
<?php if (strpos($page, 'admin') === 0): ?>
<script>
// Load tooltip JavaScript dynamically from database
async function loadTooltipJS() {
    try {
        const response = await fetch('/api/help_tooltips.php?action=generate_js');
        const data = await response.json();
        if (data.success && data.js_content) {
            const script = document.createElement('script');
            script.textContent = data.js_content;
            document.head.appendChild(script);
        }
    } catch (error) {
        console.warn('Failed to load tooltip JS:', error);
    }
}
loadTooltipJS();

// Admin Modal Functions
function openCSSRulesModal() {
    showNotification('CSS Rules modal not implemented yet', 'info');
}

function openBackgroundManagerModal() {
    showNotification('Background Manager modal not implemented yet', 'info');
}

function openRoomMapperModal() {
    showNotification('Room Mapper modal not implemented yet', 'info');
}

function openAreaItemMapperModal() {
    showNotification('Area Item Mapper modal not implemented yet', 'info');
}

function openRoomBackgroundSettingsModal() {
    createRoomBackgroundModal();
    showRoomBackgroundModal();
}

function openDashboardConfigModal() {
    showNotification('Dashboard Config modal not implemented yet', 'info');
}

function openCategoriesModal() {
    showNotification('Categories modal not implemented yet', 'info');
}

function openGlobalColorSizeModal() {
    showNotification('Global Color Size modal not implemented yet', 'info');
}

function openEnhancedRoomSettingsModal() {
    showNotification('Enhanced Room Settings modal not implemented yet', 'info');
}

function openRoomCategoryManagerModal() {
    showNotification('Room Category Manager modal not implemented yet', 'info');
}

function openBusinessSettingsModal() {
    showNotification('Business Settings modal not implemented yet', 'info');
}

function openSalesAdminModal() {
    showNotification('Sales Admin modal not implemented yet', 'info');
}

function openSquareSettingsModal() {
    showNotification('Square Settings modal not implemented yet', 'info');
}

function openEmailConfigModal() {
    showNotification('Email Config modal not implemented yet', 'info');
}

function openEmailHistoryModal() {
    showNotification('Email History modal not implemented yet', 'info');
}

function openReceiptSettingsModal() {
    showNotification('Receipt Settings modal not implemented yet', 'info');
}

function openSystemConfigModal() {
    showNotification('System Config modal not implemented yet', 'info');
}

function openDatabaseTablesModal() {
    showNotification('Database Tables modal not implemented yet', 'info');
}

function openFileExplorerModal() {
    showNotification('File Explorer modal not implemented yet', 'info');
}

function openWebsiteLogsModal() {
    showNotification('Website Logs modal not implemented yet', 'info');
}

function openAISettingsModal() {
    showNotification('AI Settings modal not implemented yet', 'info');
}

function openHelpHintsModal() {
    showNotification('Help Hints modal not implemented yet', 'info');
}

function openDatabaseMaintenanceModal() {
    showNotification('Database Maintenance modal not implemented yet', 'info');
}

function openSystemCleanupModal() {
    showNotification('System Cleanup modal not implemented yet', 'info');
}

// Room Background Settings Modal
function createRoomBackgroundModal() {
    // Remove existing modal if it exists
    const existingModal = document.getElementById('roomBackgroundModal');
    if (existingModal) {
        existingModal.remove();
    }

    const modal = document.createElement('div');
    modal.id = 'roomBackgroundModal';
    modal.className = 'admin-modal-overlay';
    modal.innerHTML = `
        <div class="admin-modal-content" style="max-width: 600px; width: 90%;">
            <div class="admin-modal-header">
                <h2>🏞️ Room Background Settings</h2>
                <button type="button" class="modal-close" onclick="closeRoomBackgroundModal()">×</button>
            </div>
            <div class="admin-modal-body">
                <div class="settings-section">
                    <h3>Main Room Background</h3>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="mainRoomFullscreen" onchange="toggleMainRoomFullscreen()">
                            Enable Fullscreen Background
                        </label>
                        <p class="help-text">Display the main room background image behind the navigation</p>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="mainRoomTitle" onchange="toggleMainRoomTitle()">
                            Show Room Title
                        </label>
                        <p class="help-text">Display the room title overlay</p>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3>Background Mode</h3>
                    <div class="form-group">
                        <label for="backgroundMode">Background Display Mode:</label>
                        <select id="backgroundMode" onchange="updateBackgroundMode()">
                            <option value="fullscreen">Fullscreen (covers entire viewport)</option>
                            <option value="normal">Normal (within container)</option>
                            <option value="overlay">Overlay (on top of previous background)</option>
                        </select>
                    </div>
                </div>
                
                <div class="settings-section">
                    <h3>Status</h3>
                    <div id="settingsStatus" class="status-info">
                        <div class="loading-spinner">Loading current settings...</div>
                    </div>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-secondary" onclick="closeRoomBackgroundModal()">Cancel</button>
                <button type="button" class="btn-primary" onclick="saveRoomBackgroundSettings()">Save Settings</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function showRoomBackgroundModal() {
    const modal = document.getElementById('roomBackgroundModal');
    if (modal) {
        modal.style.display = 'block';
        loadCurrentSettings();
    }
}

function closeRoomBackgroundModal() {
    const modal = document.getElementById('roomBackgroundModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

async function loadCurrentSettings() {
    try {
        const response = await fetch('/api/db_manager.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                admin_token: 'whimsical_admin_2024',
                query: 'SELECT * FROM business_settings WHERE category = "rooms"',
                operation: 'select'
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.results) {
            const settings = {};
            data.results.forEach(row => {
                settings[row.setting_key] = row.setting_value;
            });
            
            // Update form fields
            document.getElementById('mainRoomFullscreen').checked = settings.main_room_fullscreen === 'true';
            document.getElementById('mainRoomTitle').checked = settings.main_room_show_title === 'true';
            document.getElementById('backgroundMode').value = settings.room_background_mode || 'fullscreen';
            
            // Update status
            const statusDiv = document.getElementById('settingsStatus');
            statusDiv.innerHTML = `
                <div class="status-item">
                    <span class="status-label">Main Room Fullscreen:</span>
                    <span class="status-value ${settings.main_room_fullscreen === 'true' ? 'active' : 'inactive'}">
                        ${settings.main_room_fullscreen === 'true' ? 'Enabled' : 'Disabled'}
                    </span>
                </div>
                <div class="status-item">
                    <span class="status-label">Background Mode:</span>
                    <span class="status-value">${settings.room_background_mode || 'fullscreen'}</span>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading settings:', error);
        document.getElementById('settingsStatus').innerHTML = '<div class="error">Error loading settings</div>';
    }
}

async function toggleMainRoomFullscreen() {
    const enabled = document.getElementById('mainRoomFullscreen').checked;
    await updateSetting('main_room_fullscreen', enabled ? 'true' : 'false');
}

async function toggleMainRoomTitle() {
    const enabled = document.getElementById('mainRoomTitle').checked;
    await updateSetting('main_room_show_title', enabled ? 'true' : 'false');
}

async function updateBackgroundMode() {
    const mode = document.getElementById('backgroundMode').value;
    await updateSetting('room_background_mode', mode);
}

async function updateSetting(key, value) {
    try {
        const response = await fetch('/api/db_manager.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                admin_token: 'whimsical_admin_2024',
                query: `UPDATE business_settings SET setting_value = '${value}' WHERE setting_key = '${key}'`,
                operation: 'update'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification(`${key} updated successfully`, 'success');
        } else {
            showNotification(`Error updating ${key}`, 'error');
        }
    } catch (error) {
        console.error('Error updating setting:', error);
        showNotification('Error updating setting', 'error');
    }
}

async function saveRoomBackgroundSettings() {
    showNotification('All settings have been saved automatically', 'success');
    closeRoomBackgroundModal();
    
    // Reload the page to apply changes
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}
</script>
<?php endif; ?>

<!-- WebP Support Detection -->
<script>
    (function(){
        var d=document.createElement('div');
        d.innerHTML='<img src="data:image/webp;base64,UklGRjIAAABXRUJQVlA4ICYAAACyAgCdASoCAAEALmk0mk0iIiIiIgBoSygABc6zbAAA/v56QAAAAA==\" onerror=\"document.documentElement.className += \' no-webp\';\" onload=\"document.documentElement.className += \' webp\';\">';
    })();
</script>

<!-- Dynamic Background Loading -->
<script>
    async function loadDynamicBackground() {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page') || 'landing';
            const body = document.body;
            const supportsWebP = document.documentElement.classList.contains('webp');
            
            // Remove any existing background classes
            body.classList.remove('is-landing', 'is-main-room', 'is-individual-room', 'is-room2', 'is-room3', 'is-room4', 'is-room5', 'is-room6');
            
            // Landing page - show home background
            if (currentPage === 'landing') {
                const imageUrl = supportsWebP ? 'images/home_background.webp' : 'images/home_background.png';
                body.style.backgroundImage = `url('${imageUrl}?v=${Date.now()}')`;
                body.classList.add('is-landing');
                body.classList.add('dynamic-bg-loaded');
                return;
            }
            
            // Main room - show home background behind it
            if (currentPage === 'room_main') {
                const imageUrl = supportsWebP ? 'images/home_background.webp' : 'images/home_background.png';
                body.style.backgroundImage = `url('${imageUrl}?v=${Date.now()}')`;
                body.classList.add('is-main-room');
                body.classList.add('dynamic-bg-loaded');
                return;
            }
            
            // Individual rooms (2-6) - show main room background on body, specific room on container
            if (['room2', 'room3', 'room4', 'room5', 'room6'].includes(currentPage)) {
                // Set main room background on body
                const mainRoomUrl = supportsWebP ? 'images/room_main.webp' : 'images/room_main.png';
                body.style.backgroundImage = `url('${mainRoomUrl}?v=${Date.now()}')`;
                
                // Add classes for styling
                body.classList.add('is-individual-room', `is-${currentPage}`);
                body.classList.add('dynamic-bg-loaded');
                return;
            }
            
            // Other pages (shop, cart, login, admin) - show main room background
            const mainRoomUrl = supportsWebP ? 'images/room_main.webp' : 'images/room_main.png';
            body.style.backgroundImage = `url('${mainRoomUrl}?v=${Date.now()}')`;
            body.classList.add('is-main-room');
            body.classList.add('dynamic-bg-loaded');
            
        } catch (error) {
            console.error('Error loading dynamic background:', error);
        }
    }
    
    document.addEventListener('DOMContentLoaded', loadDynamicBackground);
</script>

<!-- Main Application Script -->
<script>
    // Cart display updates for main site navigation
    function updateMainCartCounter() {
        const cartCountEl = document.getElementById('cartCount');
        if (typeof window.cart !== 'undefined' && cartCountEl) {
            const count = window.cart.items.reduce((total, item) => total + item.quantity, 0);
            cartCountEl.textContent = count + ' items';
        }
    }

    window.addEventListener('cartUpdated', updateMainCartCounter);

    // Enhanced authentication handling using centralized AuthUtils
    async function logout() {
        if (window.AuthUtils && typeof window.AuthUtils.logout === 'function') {
            // Use centralized AuthUtils for consistent logout functionality
            await window.AuthUtils.logout({
                showNotifications: true,
                trackAnalytics: true,
                redirectDelay: 500
            });
        } else {
            console.warn('AuthUtils not available, using fallback logout');
            
            // Fallback logout functionality
            try {
                sessionStorage.clear();
                localStorage.removeItem('cart');
                localStorage.removeItem('user');
                
                setTimeout(() => {
                    window.location.href = '/logout.php';
                }, 500);
            } catch (error) {
                console.error('Fallback logout error:', error);
                window.location.href = '/logout.php';
            }
        }
    }

    // Login form handling
    document.addEventListener('DOMContentLoaded', function() {
        const isAdminPage = window.location.search.includes('page=admin');
        
        if (!isAdminPage) {
            if (typeof window.cart === 'undefined') {
                console.error('Cart not initialized properly');
            } else {
                updateMainCartCounter();
            }
        }
        
        // Login form
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;
                const errorMessage = document.getElementById('errorMessage');
                
                try {
                    const response = await fetch('/process_login.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ username, password })
                    });
                    
                    const data = await response.json();
                    
                    if (!response.ok) {
                        throw new Error(data.error || 'Login failed');
                    }
                    
                    sessionStorage.setItem('user', JSON.stringify(data.user || data));
                    
                    if (data.redirectUrl) {
                        window.location.href = data.redirectUrl;
                        return;
                    }
                    
                    const pendingCheckout = localStorage.getItem('pendingCheckout');
                    if (pendingCheckout === 'true') {
                        localStorage.removeItem('pendingCheckout');
                        window.location.href = '/?page=cart';
                        return;
                    }
                    
                    window.location.href = data.role === 'Admin' ? '/?page=admin' : '/?page=room_main';
                } catch (error) {
                    errorMessage.textContent = error.message;
                    errorMessage.classList.remove('hidden');
                }
            });
        }
    });
</script>
</body>
</html>