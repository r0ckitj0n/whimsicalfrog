<?php
// Load environment variables
require_once __DIR__ . '/config.php';

// Start or resume session
session_start();

// Check if a page is specified in the URL
$page = isset($_GET['page']) ? $_GET['page'] : 'landing';

// Define allowed pages
$allowed_pages = [
    'landing', 'main_room', 'shop', 'cart', 'login', 'register', 'admin', 'admin_inventory',
    'room_tshirts', 'room_tumblers', 'room_artwork', 'room_sublimation', 'room_windowwraps'
];

// Validate page parameter
if (!in_array($page, $allowed_pages)) {
    $page = 'landing'; // Default to landing if invalid
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user']);
$isAdmin = $isLoggedIn && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Admin';

// Redirect if trying to access admin pages without admin privileges
if (strpos($page, 'admin') === 0 && !$isAdmin) {
    header('Location: /?page=login');
    exit;
}

// Function to get image tag with WebP and fallback
function getImageTag($imagePath, $altText = '') {
    // Check if the path ends with .png, .jpg, etc.
    $pathInfo = pathinfo($imagePath);
    $extension = isset($pathInfo['extension']) ? $pathInfo['extension'] : '';
    $basePath = isset($pathInfo['dirname']) && $pathInfo['dirname'] !== '.' 
        ? $pathInfo['dirname'] . '/' . $pathInfo['filename']
        : $pathInfo['filename'];
    
    // If it's already a WebP image, just return the img tag
    if (strtolower($extension) === 'webp') {
        return '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($altText) . '">';
    }
    
    // Otherwise, try to use WebP with fallback
    $webpPath = $basePath . '.webp';
    $fallbackPath = $imagePath; // Original path as fallback
    
    return '<img src="' . htmlspecialchars($webpPath) . '" alt="' . htmlspecialchars($altText) . '" '
         . 'onerror="this.onerror=null; this.src=\'' . htmlspecialchars($fallbackPath) . '\';">';
}

// Fetch product data from API
$categories = [];
$inventory = [];

try {
    // Fetch products
    $productsData = file_get_contents('https://whimsicalfrog.onrender.com/api/products');
    $products = json_decode($productsData, true);
    
    if ($products && is_array($products)) {
        // Group products by category
        foreach ($products as $product) {
            $category = $product[2]; // Category is at index 2
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $product;
        }
    }
    
    // Fetch inventory
    $inventoryData = file_get_contents('https://whimsicalfrog.onrender.com/api/inventory');
    $inventory = json_decode($inventoryData, true) ?: [];
} catch (Exception $e) {
    // Handle API error
    error_log('API Error: ' . $e->getMessage());
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
            padding-top: 5px; /* Reduced padding */
            padding-bottom: 5px; /* Reduced padding */
        }
        
        nav.main-nav a, /* Targets all links, including title and nav items */
        nav.main-nav p, /* Targets the tagline */
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
        
        /* Hide the center welcome sign in the header navigation */
        nav.main-nav .flex-grow.flex.justify-center.items-center {
            display: none; /* Hide the center section */
        }
    </style>
</head>
<body class="flex flex-col min-h-screen <?php echo $bodyClass; ?>">

<div id="customAlertBox" class="custom-alert">
    <p id="customAlertMessage"></p>
    <button onclick="document.getElementById('customAlertBox').style.display = 'none';" class="mt-2 px-4 py-1 bg-red-500 text-white rounded hover:bg-red-600">OK</button>
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
                    <p class="text-sm font-merienda ml-2 hidden md:block" style="color: white !important; text-shadow: 1px 1px 3px rgba(0,0,0,0.7);">Discover unique custom crafts, made with love.</p>
                </div>
            </div>
            
            <!-- Center Section: Conditional Welcome Sign -->
            <div class="flex-grow flex justify-center items-center">
                <a href="/?page=landing" class="inline-block transform transition-transform duration-300 hover:scale-105">
                    <picture>
                        <source srcset="images/sign_main.webp" type="image/webp">
                        <img src="images/sign_main.png" alt="Return to Landing Page" style="max-height: 40px; display: block;">
                    </picture>
                </a>
            </div>
            
            <!-- Right Section: Navigation Links -->
            <div class="flex-none">
                <div class="flex items-center">
                    <a href="/?page=shop" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium">Shop</a>
                    <a href="/?page=cart" class="text-gray-700 hover:text-[#6B8E23] px-3 py-2 rounded-md text-sm font-medium relative inline-flex items-center">
                        <div class="flex items-center space-x-1 md:space-x-2">
                            <span id="cartCount" class="text-sm font-medium whitespace-nowrap">0 items</span>
                            <svg class="w-5 h-5 md:w-6 md:h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span id="cartTotal" class="text-sm font-medium whitespace-nowrap hidden md:inline">$0.00</span>
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
        <?php include "sections/{$page}.php"; ?>
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

<!-- Load cart script first -->
<script src="js/cart.js?v=<?php echo time(); ?>"></script>

<!-- WebP Support Detection -->
<script>
    // Detect WebP support
    (function(){
        var d=document.createElement('div');
        d.innerHTML='<img src="data:image/webp;base64,UklGRjIAAABXRUJQVlA4ICYAAACyAgCdASoCAAEALmk0mk0iIiIiIgBoSygABc6zbAAA/v56QAAAAA==" onerror="document.documentElement.className += \' no-webp\';" onload="document.documentElement.className += \' webp\';">';
    })();
</script>

<!-- Then load other scripts -->
<script>
    // --- DOM Elements ---
    const enterShopDoor = document.getElementById('enterShopDoor');
    const loginForm = document.getElementById('loginForm');
    const cartCount = document.getElementById('cartCount');
    const mainContent = document.getElementById('mainContent');

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
                const response = await fetch('https://whimsicalfrog.onrender.com/api/login', {
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
            return fetch('https://whimsicalfrog.onrender.com/api/logout', {
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
