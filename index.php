<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user']);
}

// Function to check if user is admin
function isAdmin() {
    if (!isLoggedIn()) return false;
    $user = json_decode($_SESSION['user'], true);
    return isset($user['role']) && $user['role'] === 'Admin';
}

// Get the requested page
$page = $_GET['page'] ?? 'home';

// Define a whitelist of allowed pages
$allowedPages = ['home', 'login', 'register', 'main_room', 'products_room', 'checkout', 'admin', 'admin_inventory', 'admin_customers', 'admin_orders'];

// Validate the page
if (!in_array($page, $allowedPages)) {
    $page = 'home';
}

// Check if user is trying to access admin pages without admin privileges
if (strpos($page, 'admin') === 0 && !isAdmin()) {
    $page = 'login';
}

// Helper function to fetch data from an API endpoint
function fetchFromAPI($endpoint, $method = 'GET', $data = null) {
    $url = "/api/" . $endpoint . ".php";
    
    $options = [
        'http' => [
            'method' => $method,
            'header' => 'Content-Type: application/json',
        ]
    ];
    
    if ($data !== null) {
        $options['http']['content'] = json_encode($data);
    }
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return json_decode($result, true);
}

// Load the appropriate page content
$content = '';
switch ($page) {
    case 'login':
        include 'sections/login.php';
        break;
    case 'register':
        include 'sections/register.php';
        break;
    case 'main_room':
        include 'sections/main_room.php';
        break;
    case 'products_room':
        include 'sections/products_room.php';
        break;
    case 'checkout':
        include 'sections/checkout.php';
        break;
    case 'admin':
        include 'sections/admin.php';
        break;
    case 'admin_inventory':
        include 'sections/admin_inventory.php';
        break;
    case 'admin_customers':
        include 'sections/admin_customers.php';
        break;
    case 'admin_orders':
        include 'sections/admin_orders.php';
        break;
    default:
        include 'sections/home.php';
        break;
}

// Get user data if logged in
$userData = null;
if (isLoggedIn()) {
    $userData = json_decode($_SESSION['user'], true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whimsical Frog</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <script>
        // Use the current domain for API calls in both local and production environments
        const apiBase = window.location.origin;
        
        // Function to handle API requests
        async function apiRequest(endpoint, method = 'GET', data = null) {
            const url = `${apiBase}/api/${endpoint}.php`;
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };
            
            if (data) {
                options.body = JSON.stringify(data);
            }
            
            const response = await fetch(url, options);
            return response.json();
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <!-- Header -->
    <header class="bg-green-600 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <a href="/" class="text-2xl font-bold">Whimsical Frog</a>
            <nav>
                <ul class="flex space-x-4">
                    <li><a href="/?page=main_room" class="hover:underline">Shop</a></li>
                    <?php if (isLoggedIn()): ?>
                        <?php if (isAdmin()): ?>
                            <li><a href="/?page=admin" class="hover:underline">Manage</a></li>
                        <?php endif; ?>
                        <li>
                            <span class="mr-2">Hello, <?php echo htmlspecialchars($userData['firstName'] ?? $userData['username']); ?></span>
                            <a href="/logout.php" class="hover:underline">Logout</a>
                        </li>
                    <?php else: ?>
                        <li><a href="/?page=login" class="hover:underline">Login</a></li>
                        <li><a href="/?page=register" class="hover:underline">Register</a></li>
                    <?php endif; ?>
                    <li>
                        <a href="/?page=checkout" class="hover:underline flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span id="cart-count">0</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto p-4">
        <?php echo $content; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-green-800 text-white p-4">
        <div class="container mx-auto text-center">
            <p>&copy; <?php echo date('Y'); ?> Whimsical Frog. All rights reserved.</p>
        </div>
    </footer>

    <!-- Cart Script -->
    <script src="js/cart.js"></script>
</body>
</html>
