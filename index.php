<?php
session_start();

// Load configuration and environment variables
require_once __DIR__ . '/config.php';

// Include Google API client
require_once __DIR__ . '/vendor/autoload.php';

// Configuration
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host;

$config = [
    'spreadsheet_id' => getenv('SPREADSHEET_ID'),
    'google_client_id' => getenv('GOOGLE_CLIENT_ID'),
    'api_url' => $baseUrl . '/api'
];

// Helper functions

/**
 * Generates an HTML <img> tag with WebP support and fallback to the original image format.
 *
 * @param string $originalPath The path to the original image (e.g., 'images/my_image.png').
 * @param string $altText The alt text for the image.
 * @param string $class Optional CSS classes for the image tag.
 * @param string $style Optional inline styles for the image tag.
 * @return string The HTML <img> tag.
 */
function getImageTag($originalPath, $altText, $class = '', $style = '') {
    if (empty($originalPath)) {
        $originalPath = 'images/placeholder.png'; // Default placeholder if path is empty
    }
    // $webpPath = str_replace(['images/', '.png', '.jpg', '.jpeg'], ['images/webp/', '.webp', '.webp', '.webp'], $originalPath);
    // Corrected WebP path generation - assumes WebP is in the same directory as original but with .webp extension
    $pathInfo = pathinfo($originalPath);
    $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
    
    $classAttr = !empty($class) ? " class='" . htmlspecialchars($class) . "'" : '';
    $styleAttr = !empty($style) ? " style='" . htmlspecialchars($style) . "'" : '';

    return "<img src='" . htmlspecialchars($webpPath) . "' alt='" . htmlspecialchars($altText) . "'" . $classAttr . $styleAttr . " onerror=\"this.onerror=null; this.src='" . htmlspecialchars($originalPath) . "';\">";
}

function fetchDataFromSheet($sheetName) {
    try {
        // Configuration
        $spreadsheetId = getenv('SPREADSHEET_ID');
        $credentialsPath = __DIR__ . '/credentials.json';
        
        if (!$spreadsheetId) {
            throw new Exception('SPREADSHEET_ID environment variable not set');
        }
        
        if (!file_exists($credentialsPath)) {
            throw new Exception('credentials.json file not found');
        }
        
        // Initialize Google Sheets client
        $client = new Google_Client();
        $client->setAuthConfig($credentialsPath);
        $client->addScope(Google_Service_Sheets::SPREADSHEETS_READONLY);
        
        $service = new Google_Service_Sheets($client);
        
        // Fetch data from specified sheet
        $range = $sheetName . '!A1:Z1000';
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();
        
        return $values ?: [];
        
    } catch (Exception $e) {
        error_log("Error fetching data from $sheetName: " . $e->getMessage());
        return [];
    }
}

function fetchData($endpoint) {
    switch ($endpoint) {
        case 'products':
            return fetchDataFromSheet('Products');
        case 'inventory':
            return fetchDataFromSheet('Inventory');
        default:
            return [];
    }
}

// Get data for the page
$products = fetchData('products');
$inventory = fetchData('inventory');
$currentYear = date('Y');

// Debug logging
error_log('Raw Products data: ' . print_r($products, true));
error_log('Raw Inventory data: ' . print_r($inventory, true));

// Group products by category
$categories = [];
if ($products) {
    $productData = array_slice($products, 1); // Skip header row
    foreach ($productData as $product) {
        $category = $product[2]; // ProductType is in third column
        if (!isset($categories[$category])) {
            $categories[$category] = [];
        }
        $categories[$category][] = $product;
    }
}

error_log('Processed Categories data: ' . print_r($categories, true));

// Determine which page to show
$currentPage = 'landing';
$user = json_decode($_SESSION['user'] ?? '{}', true);

// Handle page access and redirects
if (isset($_GET['page'])) {
    $requestedPage = $_GET['page'];
    
    // Handle login redirects
    if ($requestedPage === 'login' && isset($_SESSION['user'])) {
        if ($user['role'] === 'Admin') {
            header('Location: /?page=admin');
            exit;
        } else {
            header('Location: /?page=shop');
            exit;
        }
    }
    
    // Check admin access for admin pages
    if (($requestedPage === 'admin' || $requestedPage === 'admin_inventory') && (!isset($user['role']) || $user['role'] !== 'Admin')) {
        header('Location: /?page=login');
        exit;
    }

    // Cart is accessible to everyone - login only required at checkout
    
    $currentPage = $requestedPage;
}

// Handle logout
if ($currentPage === 'login' && isset($_SESSION['user'])) {
    session_destroy();
    $_SESSION = array();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whimsical Frog - Custom Crafts</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Merienda:wght@400;700&display=swap" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
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
        .bg-[#6B8E23] .text-white,
        .bg-[#556B2F] .text-white,
        .bg-green-700 .text-white,
        .bg-green-800 .text-white,
        .bg-black .text-white {
            color: #fff !important;
        }
        body {
            font-family: 'Merienda', cursive;
            background-image: url('images/home_background.png?v=cb2'); /* Fallback */
            background-image: url('images/home_background.webp?v=cb2'); /* Main */
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
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            z-index: 200;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        /* Style for the header gradient and text readability */
        nav.main-nav {
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.95), transparent); /* Even stronger gradient */
            padding-top: 10px; 
            padding-bottom: 10px; 
        }
        nav.main-nav a, /* Targets all links, including title and nav items */
        nav.main-nav p,  /* Targets the tagline */
        nav.main-nav span { /* Targets spans like cart count/total */
            color: white !important; 
            text-shadow: 1px 1px 3px rgba(0,0,0,0.7); /* Add a subtle shadow for better readability */
        }
        nav.main-nav p.tagline { /* Specific styling for the tagline */
            color: white !important; 
            text-shadow: 1px 1px 3px rgba(0,0,0,0.7);
            font-size: 0.875rem; /* Tailwind's text-sm */
            margin-top: 0.25rem; /* Add a little space above the tagline */
        }
        nav.main-nav a:hover { /* Styling for hover state */
            color: #CBD5E0 !important; /* A lighter gray for hover to distinguish from normal state */
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        /* Ensure SVGs within the nav also use white stroke */
        nav.main-nav svg {
            stroke: white !important; 
        }
    </style>
</head>
<body class="text-gray-800">
    <div id="customAlertBox" class="custom-alert">
        <p id="customAlertMessage"></p>
        <button onclick="document.getElementById('customAlertBox').style.display = 'none';" class="mt-2 px-4 py-1 bg-red-500 text-white rounded hover:bg-red-600">OK</button>
    </div>

    <!-- Main Navigation -->
    <nav class="main-nav sticky top-0 z-50 transition-all duration-300 ease-in-out">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col items-center justify-between py-3 md:flex-row">
                <div class="flex items-center">
                    <a href="/?page=landing" class="flex items-center text-2xl font-bold font-merienda">
                        <img src="images/sign_whimsicalfrog.webp" alt="Whimsical Frog" style="height: 80px; margin-right: 10px;"
                             onerror="this.onerror=null; this.src='images/sign_whimsicalfrog.png';">
                    </a>
                    <p class="text-sm font-merienda ml-2" style="color: white !important; text-shadow: 1px 1px 3px rgba(0,0,0,0.7);">Discover unique custom crafts, made with love.</p>
                </div>
                <div class="flex items-center mt-4 md:mt-0">
                    <?php if (isset($_SESSION['user'])): ?>
                        <?php if ($user['role'] === 'Admin'): ?>
                            <a href="/?page=admin" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium">Admin Dashboard</a>
                            <a href="/?page=admin_inventory" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium">Manage Inventory</a>
                        <?php endif; ?>
                        <a href="/?page=shop" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium">View All Items</a>
                    <?php else: ?>
                        <a href="/?page=shop" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium">View All Items</a>
                    <?php endif; ?>
                    <a href="/?page=cart" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium relative inline-flex items-center">
                        <div class="flex items-center space-x-2">
                            <span id="cartCount" class="text-sm font-medium whitespace-nowrap">0 items</span>
                            <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span id="cartTotal" class="text-sm font-medium whitespace-nowrap">$0.00</span>
                        </div>
                    </a>
                    <?php if (isset($_SESSION['user'])): ?>
                        <a href="#" onclick="logout()" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium">Logout</a>
                    <?php else: ?>
                        <a href="/?page=login" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-2 sm:px-2 lg:px-4">
        <?php
        switch ($currentPage) {
            case 'login':
                include 'sections/login.php';
                break;
            case 'register':
                include 'sections/register.php';
                break;
            case 'admin':
                if ($user['role'] === 'Admin') {
                    include 'sections/admin.php';
                } else {
                    header('Location: /?page=login');
                    exit;
                }
                break;
            case 'admin_inventory':
                if ($user['role'] === 'Admin') {
                    include 'sections/admin_inventory.php';
                } else {
                    header('Location: /?page=login');
                    exit;
                }
                break;
            case 'shop':
                include 'sections/shop.php';
                break;
            case 'cart':
                include 'sections/cart.php';
                break;
            case 'main_room':
                include 'sections/main_room.php';
                break;
            case 'room_artwork':
                include 'sections/room_artwork.php';
                break;
            case 'room_tshirts':
                include 'sections/room_tshirts.php';
                break;
            case 'room_tumblers':
                include 'sections/room_tumblers.php';
                break;
            case 'room_sublimation':
                include 'sections/room_sublimation.php';
                break;
            case 'room_windowwraps':
                include 'sections/room_windowwraps.php';
                break;
            default:
                include 'sections/landing.php';
                break;
        }
        ?>
    </main>

    <!-- Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeProductModal()">&times;</span>
            <div id="modalContent" class="p-4">
                <!-- Content will be dynamically inserted here -->
            </div>
        </div>
    </div>

    <!-- Load cart script first -->
    <script src="/js/cart.js"></script>
    
    <!-- WebP Support Detection -->
    <script>
        // Detect WebP support
        (function(){
            var d=document.createElement('div');
            d.innerHTML='<img src="" onerror="document.documentElement.className += \' no-webp\';" onload="document.documentElement.className += \' webp\';">';
        })();
    </script>
    
    <!-- Then load other scripts -->
    <script>
        // --- DOM Elements ---
        const enterShopDoor = document.getElementById('enterShopDoor');
        const loginForm = document.getElementById('loginForm');
        const cartCount = document.getElementById('cartCount');

        // Update cart count when cart changes
        function updateCartCount() {
            if (typeof window.cart !== 'undefined') {
                const count = window.cart.items.reduce((total, item) => total + item.quantity, 0);
                cartCount.textContent = count + ' items';
                cartCount.style.display = count > 0 ? 'flex' : 'none';
            }
        }

        // Listen for cart updates
        window.addEventListener('cartUpdated', updateCartCount);

        // Initial cart count update
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, checking cart initialization...');
            if (typeof window.cart === 'undefined') {
                console.error('Cart not initialized properly');
            } else {
                console.log('Cart is initialized and ready');
                updateCartCount();
            }
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
                    const response = await fetch('http://localhost:3000/api/login', {
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
                    
                    // Store user data in both session storage and PHP session
                    sessionStorage.setItem('user', JSON.stringify(data));
                    
                    // Store in PHP session via AJAX
                    await fetch('/set_session.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(data)
                    });
                    
                    // Redirect based on role
                    if (data.role === 'Admin') {
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
            
            // First clear PHP session
            fetch('/set_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ clear: true })
            })
            .then(() => {
                // Then clear Node.js session
                return fetch('http://localhost:3000/api/logout', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include'
                });
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Logout failed');
                }
                // Force reload the page to clear any cached state
                window.location.href = '/?page=login';
            })
            .catch(error => {
                console.error('Error:', error);
                // Still redirect to login even if there's an error
                window.location.href = '/?page=login';
            });
        }
    </script>
</body>
</html>