<?php
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /');
    exit;
}

require_once dirname(__DIR__) . '/includes/auth_helper.php';
if (class_exists('AuthHelper') ? !AuthHelper::isLoggedIn() : (function_exists('isLoggedIn') && !isLoggedIn())) {
    echo '<div class="text-center"><h1 class="text-2xl font-bold text-red-600">Please login</h1></div>';
    return;
}

// Load Vite assets for help documentation
if (function_exists('vite')) {
    vite('js/help-documentation.js');
}

// Check if we're in an iframe and adjust styling accordingly
$inIframe = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false;
?>

<div class="help-documentation-container" data-page="help-documentation">
    <div class="help-header bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-lg mb-6">
        <h1 class="text-3xl font-bold mb-2">📚 Help Documentation</h1>
        <p class="text-blue-100">Guide to using your e-commerce platform</p>
    </div>

    <div class="search-container mb-6">
        <input type="text" id="helpSearch" placeholder="Search documentation..." 
               class="w-full px-4 py-3 border rounded-lg">
        <div id="searchResults" class="hidden mt-2 bg-white border rounded-lg"></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <div class="lg:col-span-1">
            <div class="bg-white border rounded-lg p-4">
                <h3 class="font-semibold mb-4">📋 Contents</h3>
                <nav id="tocNav" class="space-y-1">
                    <a href="#getting-started" class="block px-3 py-2 text-sm hover:bg-blue-50 rounded">Getting Started</a>
                    <a href="#inventory" class="block px-3 py-2 text-sm hover:bg-blue-50 rounded">Inventory</a>
                    <a href="#orders" class="block px-3 py-2 text-sm hover:bg-blue-50 rounded">Orders</a>
                    <a href="#rooms" class="block px-3 py-2 text-sm hover:bg-blue-50 rounded">Rooms</a>
                    <a href="#payments" class="block px-3 py-2 text-sm hover:bg-blue-50 rounded">Payments</a>
                </nav>
            </div>
        </div>
        <div class="lg:col-span-3" id="helpContent"></div>
    </div>
</div>
