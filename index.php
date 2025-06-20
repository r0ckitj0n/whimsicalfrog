<?php
session_start();
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Load environment variables
require_once __DIR__ . '/api/config.php';
// Load marketing helper for dynamic content
require_once __DIR__ . '/api/marketing_helper.php';
// Start or resume session (already started at the top)

// Check if a page is specified in the URL
$page = isset($_GET['page']) ? $_GET['page'] : 'landing';

// Define allowed pages
$allowed_pages = [
    'landing', 'main_room', 'shop', 'cart', 'login', 'register', 'admin', 'admin_inventory',
    'room2', 'room3', 'room4', 'room5', 'room6',
    'admin_customers', 'admin_orders', 'admin_reports', 'admin_marketing', 'admin_settings',
    'account_settings', 'receipt' // Added receipt page
];

// Validate page parameter
if (!in_array($page, $allowed_pages)) {
    $page = 'landing'; // Default to landing if invalid
}



// Check if user is logged in and process user data
$isLoggedIn = isset($_SESSION['user']);
$isAdmin = false;
$userData = [];
$welcomeMessage = "";

if ($isLoggedIn) {
    $currentSessionUser = $_SESSION['user']; // Work with a copy for processing
    $processedUserData = null; 

    if (is_string($currentSessionUser)) {
        $decodedUser = json_decode($currentSessionUser, true);
        // Check if decoding was successful and resulted in an array
        if (is_array($decodedUser)) {
            $_SESSION['user'] = $decodedUser; // Normalize: store the array back into the session
            $processedUserData = $decodedUser;
        } else {
            // Invalid JSON string in session. Treat as not logged in for safety.
            unset($_SESSION['user']);
            $isLoggedIn = false; // Update $isLoggedIn status
        }
    } elseif (is_array($currentSessionUser)) {
        $processedUserData = $currentSessionUser; // It's already an array
    } else {
        // $_SESSION['user'] is set but is neither a string nor an array (e.g. number, bool).
        // This is an unexpected/corrupt state. Treat as not logged in.
        unset($_SESSION['user']);
        $isLoggedIn = false; // Update $isLoggedIn status
    }

    // If still logged in after processing and data is valid
    if ($isLoggedIn && $processedUserData !== null) {
        $userData = $processedUserData; 
        $isAdmin = isset($userData['role']) && $userData['role'] === 'Admin';
        
        // Welcome message with user's name if available
        if (isset($userData['firstName']) || isset($userData['lastName'])) {
            $welcomeMessage = "Welcome, " . ($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? '');
        }
    } else {
        // If $isLoggedIn became false due to session processing, ensure $isAdmin is false and $userData is empty
        $isAdmin = false;
        $userData = []; 
        // $welcomeMessage remains its initial ""
    }
}
// Set isAdmin as a global variable for use in components
$GLOBALS['isAdmin'] = $isAdmin;

// This is the admin authentication check, correctly placed after $isAdmin is determined and before HTML output.
// Redirect if trying to access admin pages without admin privileges
if (strpos($page, 'admin') === 0 && !$isAdmin) {
    header('Location: /?page=login');
    exit;
}

// Define flag for files included from index.php
define('INCLUDED_FROM_INDEX', true);

// Function to get image tag with WebP and fallback
function getImageTag($imagePath, $altText = '') {
    $rootDir = __DIR__;
    $pathInfo = pathinfo($imagePath);
    $extension = $pathInfo['extension'] ?? '';
    $basePath = ($pathInfo['dirname'] && $pathInfo['dirname'] !== '.')
        ? $pathInfo['dirname'] . '/' . $pathInfo['filename']
        : $pathInfo['filename'];

    if (strtolower($extension) === 'webp') {
        return '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($altText) . '">';
    }

    $webpPath = $basePath . '.webp';
    $webpAbs = $rootDir . '/' . $webpPath;
    $fallbackPath = $imagePath;

    if(file_exists($webpAbs)) {
        return '<picture><source srcset="' . htmlspecialchars($webpPath) . '" type="image/webp">'
              . '<img src="' . htmlspecialchars($fallbackPath) . '" alt="' . htmlspecialchars($altText) . '"></picture>';
    }
    // If PNG fallback doesn't exist either, use original path
    return '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($altText) . '">';
}

// Fetch product data using direct SQL queries
$categories = [];
$inventory = [];

try {
    // Create database connection using config
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Fetch items data - use items as the single source of truth (show all items, not just in-stock)
    $stmt = $pdo->query('SELECT sku, sku AS inventoryId, name AS productName, stockLevel, retailPrice, description, category AS productType FROM items');
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($products && is_array($products)) {
        foreach ($products as $product) {
            if (!isset($product['productType'])) {
                continue; // Skip rows without a category
            }
            $category = $product['productType'];
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            /* -------------------------------------------------------------------------
             * Normalise/duplicate field names so that all front-end sections can work
             * with a single data structure.
             *
             *  –  The "shop" section (sections/shop.php) expects:             
             *        productId, productName, price, description, imageUrl, stock
             *  –  The "room_*" sections expect:                    
             *        id, name, basePrice (or price), description, image
             *
             *  We use SKU as the primary identifier and map to expected field names.
             * --------------------------------------------------------------------- */

            // Use SKU as productId for compatibility
            $product['productId'] = $product['sku'];
            
            // Use retail price as the main price
            $product['price'] = $product['retailPrice'];
            $product['basePrice'] = $product['retailPrice'];
            
            // Set stock level
            $product['stock'] = (int)$product['stockLevel'];
            
            // Set image URL to empty - will be handled by SKU-based image system
            $product['imageUrl'] = '';

            // Duplicate keys for room pages compatibility
            $product['id']    = $product['sku'];  // Use SKU as ID
            $product['name']  = $product['productName'] ?? null;
            $product['image'] = '';  // Will be handled by SKU-based system

            $categories[$category][] = $product;
        }
    }
    
    // Fetch items for admin sections that still need it
    $stmt = $pdo->query('SELECT * FROM items');
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format items data to match the expected structure
    $inventory = $items;



} catch (PDOException $e) {
    // Handle database errors
    error_log('Database Error: ' . $e->getMessage());
    // You might want to show an error message to the user
}

// Set body class based on page
$bodyClass = '';
if ($page === 'landing') {
    $bodyClass = 'is-landing';
} elseif (strpos($page, 'admin') === 0) {
    $bodyClass = 'is-admin';
}

// Determine if this is a fullscreen layout page
$isFullscreenPage = in_array($page, ['landing']);
if ($isFullscreenPage) {
    $bodyClass .= ' body-fullscreen-layout';
}

// Handle cart data
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cartCount = 0;
$cartTotal = 0;

foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['quantity'];
    $cartTotal += $item['price'] * $item['quantity'];
}

// Format cart total
$formattedCartTotal = '$' . number_format($cartTotal, 2);

// Generate dynamic SEO for current page
$currentSku = $_GET['product'] ?? null; // For product-specific pages
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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="css/styles.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="css/global-modals.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Merienda:wght@400;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box; /* Apply border-box to all elements */
            margin: 0; /* Reset default margins */
            padding: 0; /* Reset default padding */
        }
        
        body, html {
            color: #222 !important;
            background: #333; /* Dark grey background */
            width: 100%; /* Ensure html and body take full width */
            height: 100%; /* Ensure full height for viewport calculations */
            overflow-x: hidden; /* Prevent horizontal scrollbar */
            overflow-y: auto; /* HTML is the primary vertical scroll container */
        }
        
        label, input, select, textarea, button, p, h1, h2, h3, h4, h5, h6, span, div {
            color: #222 !important;
        }
        
        .bg-white, .bg-gray-100, .bg-gray-200, .bg-gray-50 {
            color: #222 !important;
        }
        
        /* Remove forced white text except on dark backgrounds */
        .text-white:not([class*='bg-']) {
            color: #222 !important;
        }
        
        /* Keep white text on dark backgrounds */
        .bg-[#6B8E23] .text-white, .bg-[#556B2F] .text-white, .bg-green-700 .text-white, .bg-green-800 .text-white, .bg-black .text-white {
            color: #fff !important;
        }
        
        /* --- Admin button colour override --- */
        body.is-admin button,
        body.is-admin .button {
            background: #87ac3a !important;
            color: #ffffff !important;
            border: none !important;
        }
        body.is-admin button:hover,
        body.is-admin .button:hover {
            background: #a3cc4a !important;
        }
        
        body {
            font-family: 'Merienda', cursive;
            background-image: url('images/home_background.png?v=cb2'); /* Fallback */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100%; /* Make body fill html height */
            /* overflow-x: hidden; Already on html, body */
            /* overflow-y: auto; Removed, html handles this */
        }
        
        /* WebP support detection */
        .webp body {
            background-image: url('images/home_background.webp?v=cb2');
        }
        
        .no-webp body {
            background-image: url('images/home_background.png?v=cb2');
        }
        
        /* Non-landing pages background */
        body:not(.is-landing) {
            background-image: url('images/room_main.png?v=cb2'); /* Fallback */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        
        .webp body:not(.is-landing) {
            background-image: url('images/room_main.webp?v=cb2');
        }
        
        .no-webp body:not(.is-landing) {
            background-image: url('images/room_main.png?v=cb2');
        }
        
        /* Dynamic background classes - will be set by JavaScript */
        body.dynamic-bg-loaded {
            /* Background will be set dynamically */
        }
        
        .font-merienda {
            font-family: 'Merienda', cursive;
        }
        
        .cottage-bg {
            background: transparent;
        }
        
        .shelf {
            background-color: #D2B48C;
            border: 2px solid #8B4513;
        }
        
        .door {
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        .door:hover {
            transform: scale(1.05);
        }
        
        .shelf:hover {
            transform: translateY(-5px);
        }
        
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
        
        /* Style for the header gradient and text readability */
        nav.main-nav {
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.95), transparent); /* Even stronger gradient */
            padding-top: 5px; /* Reduced padding */
            padding-bottom: 5px; /* Reduced padding */
        }
        
        nav.main-nav a, /* Targets all links, including title and nav items */
        nav.main-nav p, /* Targets the tagline */
        nav.main-nav span { /* Targets spans like cart count/total */
            color: #87ac3a !important;
            font-size: 1.1rem; /* Bigger text but still compact */
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7); /* subtle shadow for readability */
            line-height: 1.2; /* maintain header height */
        }
        
        /* Ensure cart text also gets the shadow even if scoped styles override */
        #cartCount, #cartTotal {
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
        }
        
        nav.main-nav p.tagline { /* Specific styling for the tagline */
            color: #87ac3a !important;
            font-size: 1.3rem; /* More prominent tagline */
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
            margin-top: 0.25rem;
            line-height: 1.2;
        }
        
        nav.main-nav a:hover { /* Styling for hover state */
            color: #a3cc4a !important;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
        }
        
        /* Ensure SVGs within the nav also use green stroke and subtle shadow */
        nav.main-nav svg {
            stroke: #87ac3a !important;
            filter: drop-shadow(1px 1px 2px rgba(0,0,0,0.7));
        }
        
        /* Hide elements on landing page */
        body.is-landing a[href="/?page=shop"],
        body.is-landing a[href="/?page=cart"],
        body.is-landing a[href="/?page=login"] {
            display: none !important;
        }
        
        /* Hide "Back to Room" overlays */
        .back-to-room-overlay {
            display: none !important;
        }
        
        /* Fix popup issues */
        .popup {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }
        
        .popup.show, .product-popup.show {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        /* Room pages should have less spacing */
        .room-section {
            padding: 0.25rem !important;
            margin-top: 0 !important;
        }
        
        /* Main room specific spacing fixes */
        .room-section .main-room-container {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }
        
        /* Full-screen page styling */
        .body-fullscreen-layout {
            overflow: hidden; /* Handles structural full-screen behavior */
        }
        
        /* This rule is a fallback in case #mainContent is somehow rendered on a fullscreen page */
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
        
        /* User welcome message styling */
        .welcome-message {
            color: #87ac3a !important;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
            font-size: 0.875rem;
            margin-left: 0.5rem;
        }
        
        .room-header h1, .room-header p {
            color: #87ac3a !important;
            -webkit-text-stroke: 2px #471907;
            paint-order: stroke fill;
            text-shadow: -1px -1px 0 #471907,
                         1px -1px 0 #471907,
                        -1px  1px 0 #471907,
                         1px  1px 0 #471907,
                         0  -1px 0 #471907,
                         0   1px 0 #471907,
                        -1px  0   0 #471907,
                         1px  0   0 #471907;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen <?php echo $bodyClass; ?>">

<div id="customAlertBox" class="custom-alert">
    <p id="customAlertMessage"></p>
    <button onclick="document.getElementById('customAlertBox').style.display = 'none';" class="mt-3 px-6 py-2 bg-[#87ac3a] text-white rounded-lg hover:bg-[#a3cc4a] font-semibold border-2 border-[#87ac3a] transition-all duration-200">OK</button>
</div>

<!-- Main Navigation -->
<nav class="main-nav sticky top-0 z-50 transition-all duration-300 ease-in-out">
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between w-full py-1"> <!-- Main flex row for 3 sections -->
            <!-- Left Section: Logo and Tagline -->
            <div class="flex-none">
                <div id="nav-center-content" class="flex items-center">
                    <a href="/?page=landing" class="flex items-center text-2xl font-bold font-merienda">
                        <img src="images/sign_whimsicalfrog.webp" alt="Whimsical Frog" style="height: 60px; margin-right: 8px;" onerror="this.onerror=null; this.src='images/sign_whimsicalfrog.png';">
                    </a>
                    <div>
                        <p class="text-sm font-merienda ml-2 hidden md:block" style="color: #87ac3a !important; text-shadow: 1px 1px 2px rgba(0,0,0,0.7);">Discover unique custom crafts, made with love.</p>
                        <?php if ($isLoggedIn && !empty($welcomeMessage)): ?>
                            <p class="welcome-message">
                                <a href="/?page=account_settings" class="hover:underline text-[#87ac3a]" title="Edit your account settings"><?php echo htmlspecialchars($welcomeMessage); ?></a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Center Section: Empty space (welcome sign removed) -->
            <div class="flex-grow"></div>
            
            <!-- Right Section: Navigation Links -->
            <div class="flex-none">
                <div class="flex items-center">
                    <?php if ($isAdmin): ?>
                    <a href="/?page=admin" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium">Manage</a>
                    <?php endif; ?>
                    <a href="/?page=shop" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium">Shop</a>
                    <a href="/?page=cart" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium relative inline-flex items-center">
                        <div class="flex items-center space-x-1 md:space-x-2">
                            <span id="cartCount" class="text-sm font-medium whitespace-nowrap"><?php echo $cartCount; ?> items</span>
                            <svg class="w-5 h-5 md:w-6 md:h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span id="cartTotal" class="text-sm font-medium whitespace-nowrap hidden md:inline"><?php echo $formattedCartTotal; ?></span>
                        </div>
                    </a>
                    <?php if ($isLoggedIn): ?>
                        <a href="/logout.php" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium" onclick="logout(); return false;">Logout</a>
                    <?php else: ?>
                        <a href="/?page=login" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</nav>

<?php if ($isFullscreenPage): ?>
    <div class="fullscreen-container">
        <?php include "sections/{$page}.php"; ?>
    </div>
<?php else: ?>
    <main class="flex-grow container mx-auto p-2 md:p-4 lg:p-6 cottage-bg" id="mainContent">
        <?php 
        // Include the appropriate page content
        $pageFile = 'sections/' . $page . '.php';
        
        // Handle admin section parameter for the main admin page
        if ($page === 'admin' && isset($_GET['section'])) {
            $section = $_GET['section'];
            $sectionFile = 'sections/admin_' . $section . '.php';
            
            // Check if the section file exists
            if (file_exists($sectionFile)) {
                include $pageFile; // Include the main admin page first
                // The admin.php page will handle including the section file
            } else {
                include $pageFile; // Just include the main admin dashboard
            }
        } else if (file_exists($pageFile)) {
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

<!-- Load cart script first (only on non-admin pages) -->
<?php if ($page !== 'admin'): ?>
<script src="js/cart.js?v=<?php echo time(); ?>"></script>
<?php endif; ?>

<!-- Load global modal system -->
<script src="js/global-modals.js?v=<?php echo time(); ?>"></script>

<!-- Load analytics tracking system -->
<script src="js/analytics.js?v=<?php echo time(); ?>"></script>

<!-- WebP Support Detection -->
<script>
    // Detect WebP support
    (function(){
        var d=document.createElement('div');
        d.innerHTML='<img src="data:image/webp;base64,UklGRjIAAABXRUJQVlA4ICYAAACyAgCdASoCAAEALmk0mk0iIiIiIgBoSygABc6zbAAA/v56QAAAAA==\" onerror=\"document.documentElement.className += \' no-webp\';\" onload=\"document.documentElement.className += \' webp\';\">';
    })();
</script>

<!-- Dynamic Background Loading -->
<script>
    // Load dynamic backgrounds from database
    async function loadDynamicBackground() {
        try {
            // Determine room type based on current page
            const urlParams = new URLSearchParams(window.location.search);
            const currentPage = urlParams.get('page') || 'landing';
            const fromMain = urlParams.get('from') === 'main';
            
            let roomType = 'landing';
            
            // Check if we're coming from main room - if so, use main room background
            if (fromMain && ['room2', 'room3', 'room4', 'room5', 'room6'].includes(currentPage)) {
                roomType = 'room_main';
                
                // Apply main room background directly
                const body = document.body;
                const supportsWebP = document.documentElement.classList.contains('webp');
                const imageUrl = supportsWebP ? 'images/room_main.webp' : 'images/room_main.png';
                
                body.style.backgroundImage = `url('${imageUrl}?v=${Date.now()}')`;
                body.classList.add('dynamic-bg-loaded');
                
                console.log(`Dynamic main room background loaded (from=main): ${imageUrl}`);
                return;
            }
            
            // Map page names to room types (normal behavior)
            switch (currentPage) {
                case 'main_room':
                    roomType = 'room_main';
                    break;
                case 'room2':
                    roomType = 'room2';
                    break;
                case 'room3':
                    roomType = 'room3';
                    break;
                case 'room4':
                    roomType = 'room4';
                    break;
                case 'room5':
                    roomType = 'room5';
                    break;
                case 'room6':
                    roomType = 'room6';
                    break;
                case 'shop':
                case 'cart':
                case 'login':
                case 'admin':
                    roomType = 'room_main';
                    break;
                default:
                    roomType = 'landing';
            }
            
            // Fetch active background for this room
            const response = await fetch(`api/get_background.php?room_type=${roomType}`);
            const data = await response.json();
            
            if (data.success && data.background) {
                const background = data.background;
                const body = document.body;
                
                // Determine if WebP is supported
                const supportsWebP = document.documentElement.classList.contains('webp');
                const imageUrl = supportsWebP && background.webp_filename ? 
                    `images/${background.webp_filename}` : 
                    `images/${background.image_filename}`;
                
                // Apply the background
                body.style.backgroundImage = `url('${imageUrl}?v=${Date.now()}')`;
                body.classList.add('dynamic-bg-loaded');
                
                console.log(`Dynamic background loaded: ${background.background_name} (${imageUrl})`);
            } else {
                console.log('Using fallback background - no dynamic background found');
            }
        } catch (error) {
            console.error('Error loading dynamic background:', error);
            console.log('Using fallback background due to error');
        }
    }
    
    // Load background when DOM is ready
    document.addEventListener('DOMContentLoaded', loadDynamicBackground);
</script>

<!-- Then load other scripts -->
<script>
    // --- DOM Elements ---
    const enterShopDoor = document.getElementById('enterShopDoor');
    const loginForm = document.getElementById('loginForm');
    const cartCountEl = document.getElementById('cartCount'); // Renamed to avoid conflict with cartCount variable
    const mainContent = document.getElementById('mainContent');

    // Update cart count when cart changes
    function updateCartDisplay() { // Renamed to avoid conflict with cart.js if any
        if (typeof window.cart !== 'undefined' && cartCountEl) { // Check if cartCountEl exists
            const count = window.cart.items.reduce((total, item) => total + item.quantity, 0);
            cartCountEl.textContent = count + ' items';
            // cartCountEl.style.display = count > 0 ? 'flex' : 'none'; // This might hide the "0 items" text, consider UX
        } else if (cartCountEl) {
            // If cart is not loaded (admin pages), keep the server-side count
            // cartCountEl.textContent remains as set by PHP
        }
    }

    // Listen for cart updates
    window.addEventListener('cartUpdated', updateCartDisplay);

    // Initial cart count update
    document.addEventListener('DOMContentLoaded', function() {
        // Only initialize cart on non-admin pages to avoid conflicts
        const isAdminPage = window.location.search.includes('page=admin');
        
        if (!isAdminPage) {
            console.log('DOM loaded, checking cart initialization...');
            if (typeof window.cart === 'undefined') {
                console.error('Cart not initialized properly');
            } else {
                console.log('Cart is initialized and ready');
                updateCartDisplay();
            }
        }
        
        // Removed document-level click handler to avoid interference with back button
    });

    // --- Event Listeners ---
    if (enterShopDoor) {
        enterShopDoor.addEventListener('click', () => {
            console.log('Enter shop door clicked');
            window.location.href = '/?page=shop';
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const errorMessage = document.getElementById('errorMessage');
            
            try {
                // Direct SQL query handled by server-side code
                const response = await fetch('/process_login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ username, password })
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    throw new Error(data.error || 'Login failed');
                }
                
                // Store user data in sessionStorage
                const userObj = data.user ? data.user : data; // support both formats
                sessionStorage.setItem('user', JSON.stringify(userObj));
                
                // Check if user was trying to checkout before login
                const pendingCheckout = localStorage.getItem('pendingCheckout');
                if (pendingCheckout === 'true') {
                    localStorage.removeItem('pendingCheckout');
                    window.location.href = '/?page=cart';
                    return;
                }
                
                // Redirect based on role
                if (data.user && data.user.role === 'Admin') {
                    window.location.href = '/?page=admin';
                } else {
                    window.location.href = '/?page=shop';
                }
            } catch (error) {
                errorMessage.textContent = error.message;
                errorMessage.classList.remove('hidden');
            }
        });
    }

    function logout() {
        // Clear client-side session storage
        sessionStorage.removeItem('user');
        
        // Clear PHP session by redirecting to logout.php
        // logout.php should handle session_destroy() and redirect
        window.location.href = '/logout.php'; 
    }
</script>
</body>
</html>