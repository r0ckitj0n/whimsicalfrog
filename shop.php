<?php
// Shop page section
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /?page=shop');
    exit;
}
?>

<!-- PURE INLINE CSS SOLUTION - ALL EXTERNAL CSS/JS REMOVED -->

<style>
/* CRITICAL: Custom Scrollbar Styling - Enhanced with maximum compatibility */
html, body, * {
    scrollbar-width: thin !important;
    scrollbar-color: #87ac3a rgba(135, 172, 58, 0.3) !important;
}

/* Webkit scrollbar styling for Chrome, Safari, Edge */
html::-webkit-scrollbar, body::-webkit-scrollbar, *::-webkit-scrollbar {
    width: 16px !important;
    height: 16px !important;
    background: rgba(135, 172, 58, 0.1) !important;
}

html::-webkit-scrollbar-thumb, body::-webkit-scrollbar-thumb, *::-webkit-scrollbar-thumb {
    background: #87ac3a !important;
    border-radius: 8px !important;
    border: 2px solid rgba(135, 172, 58, 0.1) !important;
    transition: background-color 0.3s ease !important;
}

html::-webkit-scrollbar-thumb:hover, body::-webkit-scrollbar-thumb:hover, *::-webkit-scrollbar-thumb:hover {
    background: #6b8e23 !important;
}

html::-webkit-scrollbar-track, body::-webkit-scrollbar-track, *::-webkit-scrollbar-track {
    background: rgba(135, 172, 58, 0.05) !important;
    border-radius: 8px !important;
}

/* Ensure scrollbar appears on main content areas */
#shopPage, #productsGrid, .product-card {
    scrollbar-width: thin !important;
    scrollbar-color: #87ac3a rgba(135, 172, 58, 0.3) !important;
}

/* CRITICAL: Background Image Fix - Ensure single full-screen cover */
body {
    background-size: cover !important;
    background-repeat: no-repeat !important;
    background-position: center center !important;
    background-attachment: fixed !important;
    min-height: 100vh !important;
}

/* CRITICAL FIX: Override Tailwind padding on main container - Maximum Specificity */
main.md\:p-4.lg\:p-6.cottage-bg.page-content,
main.page-content,
#mainContent {
    padding: 0 !important;
    margin: 0 !important;
    margin-top: calc(80px + 10px) !important;
    width: 100% !important;
    max-width: none !important;
    box-sizing: border-box !important;
}

/* CRITICAL: Category Button Hover States */
.category-btn:hover {
    background-color: #6b8e23 !important;
    color: white !important;
}

.category-btn.active {
    background-color: white !important;
    color: #87ac3a !important;
    border: 2px solid #87ac3a !important;
}

/* CRITICAL: Product Grid - Simple Full Width Layout - HIGHEST SPECIFICITY TO OVERRIDE BUNDLE */
#shopPage #productsGrid,
body.shop-page #shopPage #productsGrid,
.shop-page #shopPage #productsGrid {
    display: grid !important;
    gap: 1rem !important;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important; /* Exactly 250px minimum */
    justify-content: start !important; /* Left-align cards in rows */
    align-items: stretch !important;
    width: 100% !important; /* Full width of parent container */
    max-width: none !important; /* Override any max-width constraints */
    margin: 0 !important; /* Remove auto centering */
    padding: 0 20px !important;
    grid-auto-rows: 1fr !important; /* Equal height rows */
    box-sizing: border-box !important;
}

/* CRITICAL: Shop page container - Minimal inline CSS, let external CSS handle viewport width */
#shopPage {
    /* Only essential properties that don't conflict with external CSS viewport breakout */
    overflow: hidden !important; /* Prevent page-level scrolling */
    height: 100vh !important; /* Full viewport height */
    display: flex !important;
    flex-direction: column !important;
    /* Remove width, position, margin properties - let external CSS handle these */
}

/* CRITICAL: Fixed Navigation Area - Stays at top, doesn't scroll */
.shop-navigation-area {
    position: sticky !important; /* Use sticky positioning to stay at top */
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    z-index: 1000 !important; /* Higher z-index to stay above content */
    background: rgba(0, 0, 0, 0.2) !important; /* Slightly more opaque for better visibility */
    backdrop-filter: blur(15px) !important; /* Stronger blur for better separation */
    padding: 15px 0 !important;
    border-bottom: 2px solid rgba(135, 172, 58, 0.3) !important; /* More visible border */
    width: 100% !important;
    flex-shrink: 0 !important; /* Don't shrink in flex container */
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1) !important; /* Add shadow for depth */
}

/* CRITICAL: Scrollable Content Area - Only this area scrolls */
.shop-content-area {
    flex: 1 !important; /* Take remaining space in flex container */
    overflow-y: auto !important;
    overflow-x: hidden !important;
    width: 100% !important;
    padding: 30px 0 !important; /* Increased top padding to prevent overlap */
    box-sizing: border-box !important;
    margin: 0 !important; /* Remove all margins */
    min-height: 0 !important; /* Allow flex item to shrink below content size */
}

/* CRITICAL: Scrollbar positioning - Force to browser edge */
html, body {
    overflow: hidden !important; /* Prevent body scrolling */
    height: 100vh !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100vw !important; /* Ensure full viewport width */
}

/* CRITICAL: Ensure shop content area scrollbar appears at browser edge */
.shop-content-area {
    /* Simplified approach for scrollbar at edge */
    width: 100% !important;
    margin: 0 !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

/* CRITICAL: Header Gradient - EXACT COPY from room_main page */
.site-header.universal-page-header {
    background: linear-gradient(to bottom, rgb(0 0 0 / 70%) 0%, rgb(0 0 0 / 40%) 50%, transparent 100%) !important; /* Exact room_main gradient */
    backdrop-filter: blur(5px) !important;
}

.site-header.universal-page-header * {
    color: var(--brand-primary, #87ac3a) !important; /* Brand primary color for all text */
    text-shadow: 1px 1px 2px rgb(0 0 0 / 80%) !important; /* Exact room_main text shadow */
}

/* CRITICAL: Navigation Layout - Fixed 3-Column CSS Grid Layout */
.navigation-bar {
    display: grid !important;
    grid-template-columns: 100px 1fr 100px !important; /* Left: room image, Middle: filters, Right: empty spacer */
    align-items: center !important;
    width: calc(100% - 40px) !important; /* Account for container padding */
    margin: 0 auto !important; /* Center the navigation bar */
    gap: 20px !important;
    padding: 0 20px !important;
    background: rgba(0, 0, 0, 0.2) !important; /* Slightly more opaque for better visibility */
    border-radius: 10px !important;
    backdrop-filter: blur(15px) !important; /* Stronger blur */
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1) !important; /* Add subtle shadow */
}

/* CRITICAL: Left Column - Room Main Image (left-aligned within column) */
.room-main-nav-container {
    justify-self: start !important; /* Left-align within the left column */
    height: 75px !important;
    width: auto !important; /* Let width be auto to maintain aspect ratio */
}

.room-main-nav-container img {
    height: 75px !important;
    width: auto !important;
    opacity: 0.8 !important;
    transition: opacity 0.3s ease !important;
}

/* CRITICAL: Middle Column - Category Filter Buttons (center-aligned within column) */
.category-navigation {
    justify-self: center !important; /* Center-align within the middle column */
    display: flex !important;
    flex-wrap: wrap !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 15px !important;
    width: 100% !important;
}

/* CRITICAL: Right Column - Empty spacer (same width as room image for proper centering) */
.navigation-spacer {
    width: 100px !important;
    height: 75px !important;
    justify-self: end !important;
}

/* CRITICAL: Room Main Navigation Container - Consolidated rules */

.room-main-nav-link {
    display: inline-block !important;
    text-decoration: none !important;
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
}

.room-main-nav-link picture {
    background: transparent !important;
    background-color: transparent !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
    display: block !important;
}

.room-main-nav-image {
    height: 75px !important;
    width: auto !important;
    opacity: 0.8 !important;
    transition: opacity 0.3s ease !important;
    background: transparent !important;
    background-color: transparent !important;
    border: none !important;
    box-shadow: none !important;
}

/* CRITICAL: Category Navigation Container */
.category-navigation {
    gap: 15px !important;
    width: 100% !important;
}

/* CRITICAL: Category Button Styles */
.category-btn {
    height: 75px !important;
    min-height: 75px !important;
    padding: 15px 20px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    white-space: nowrap !important;
    border-radius: 50px !important;
    transition: all 0.2s ease !important;
    box-sizing: border-box !important;
    background-color: #87ac3a !important;
    color: white !important;
    border: none !important;
    cursor: pointer !important;
}

.category-btn.active {
    background-color: white !important;
    color: #87ac3a !important;
    border: 2px solid #87ac3a !important;
}

.category-navigation {
    display: flex !important;
    flex-wrap: wrap !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 0.75rem !important; /* Increased gap for better spacing */
    flex: 1 !important;
    max-width: 100% !important;
    margin: 0 auto !important; /* Additional centering */
}

/* CRITICAL: Room Main Image Left Justification - Within grid layout */
.room-main-nav-container {
    justify-self: start !important; /* Left-align within the grid column */
    height: 75px !important;
    width: auto !important;
    /* Remove absolute positioning to work within grid */
    position: relative !important;
    z-index: 2 !important;
}

/* CRITICAL: Responsive Grid Layout - Full Viewport Width Override */
@media (max-width: 640px) {
    #shopPage #productsGrid,
    body.shop-page #shopPage #productsGrid,
    .shop-page #shopPage #productsGrid {
        grid-template-columns: minmax(250px, 1fr) !important;
        padding: 0 20px !important;
        gap: 1rem !important;
        max-width: none !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .product-card {
        min-height: 480px !important;
        max-width: 100% !important;
        min-width: 250px !important;
    }

    /* Mobile-specific description styling */
    .product-description {
        max-height: 80px !important;
        min-height: 40px !important;
    }

    .navigation-bar {
        display: flex !important; /* Switch to flex on mobile */
        flex-direction: column !important;
        gap: 15px !important;
        justify-content: center !important;
        align-items: center !important;
        grid-template-columns: none !important; /* Disable grid on mobile */
    }

    .room-main-nav-container {
        justify-self: center !important;
        order: -1 !important;
        height: 75px !important;
        width: 75px !important;
    }

    .category-navigation {
        justify-self: center !important;
        justify-content: center !important;
        width: 100% !important;
    }

    .navigation-spacer {
        display: none !important; /* Hide spacer on mobile */
    }

    .shop-navigation-area {
        position: absolute !important;
        top: 0 !important;
        height: auto !important;
        padding: 20px 0 !important;
        flex-shrink: 0 !important;
    }

    .shop-content-area {
        flex: 1 !important;
        margin-top: 0 !important;
    }

    #productsGrid {
        grid-template-columns: minmax(250px, 1fr) !important; /* Single column on mobile */
        padding: 0 15px !important;
        width: 100% !important;
    }

    .category-navigation {
        width: 100% !important;
    }

    .room-main-nav-image {
        height: 60px !important;
        width: auto !important;
    }

    .category-btn {
        height: 60px !important;
        min-height: 60px !important;
        padding: 12px 16px !important;
        font-size: 0.8125rem !important;
    }
}

@media (min-width: 641px) and (max-width: 1024px) {
    #shopPage #productsGrid,
    body.shop-page #shopPage #productsGrid,
    .shop-page #shopPage #productsGrid {
        grid-template-columns: repeat(2, minmax(250px, 1fr)) !important;
        gap: 1rem !important;
        padding: 0 25px !important;
        max-width: none !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .product-card {
        min-height: 500px !important;
        min-width: 250px !important;
    }

    /* Tablet-specific description styling */
    .product-description {
        max-height: 100px !important;
        min-height: 50px !important;
    }
}

@media (min-width: 1025px) and (max-width: 1399px) {
    #shopPage #productsGrid,
    body.shop-page #shopPage #productsGrid,
    .shop-page #shopPage #productsGrid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 300px)) !important;
        gap: 1rem !important;
        padding: 0 30px !important;
        max-width: none !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .product-card {
        min-height: 520px !important;
        min-width: 250px !important;
    }

    /* Desktop-specific description styling */
    .product-description {
        max-height: 110px !important;
        min-height: 60px !important;
    }
}

@media (min-width: 1400px) {
    #productsGrid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 280px)) !important;
        max-width: none !important;
        margin: 0 !important;
        gap: 1.25rem !important;
        padding: 0 30px !important;
        width: 100% !important;
        box-sizing: border-box !important;
    }

    .product-card {
        min-height: 540px !important;
        max-width: 280px !important; /* Slightly smaller for more cards per row */
        min-width: 250px !important;
    }
}

/* Enhanced ultra-wide screen support (1600px+) */
@media (min-width: 1600px) {
    #productsGrid {
        grid-template-columns: repeat(auto-fit, minmax(240px, 260px)) !important;
        gap: 1.5rem !important;
        padding: 0 40px !important;
        width: 100% !important;
        max-width: none !important;
        box-sizing: border-box !important;
    }

    .product-card {
        min-height: 560px !important;
        max-width: 260px !important;
        min-width: 240px !important;
    }
}

/* CRITICAL: Additional responsive fixes for full-screen layout */
@media (min-width: 768px) {
    .navigation-bar {
        justify-content: center !important;
        align-items: center !important;
        gap: 15px !important;
    }

    .room-main-nav-container {
        left: 20px !important; /* Add some padding from edge on larger screens */
    }

    .category-navigation {
        justify-content: center !important;
        max-width: none !important;
    }
}

/* Maximum ultra-wide screen support (2000px+) */
@media (min-width: 2000px) {
    #productsGrid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 240px)) !important;
        gap: 1.75rem !important;
        padding: 0 50px !important;
        width: 100% !important;
        max-width: none !important;
        box-sizing: border-box !important;
    }

    .product-card {
        min-height: 580px !important;
        max-width: 240px !important;
        min-width: 220px !important;
    }
}

/* Custom scrollbar styling for shop page */
body, html {
    scrollbar-width: thin !important;
    scrollbar-color: #87ac3a rgba(135, 172, 58, 0.3) !important;
}

*::-webkit-scrollbar {
    width: 16px !important;
    background: rgba(135, 172, 58, 0.1) !important;
}

*::-webkit-scrollbar-thumb {
    background: #87ac3a !important;
    border-radius: 8px !important;
    border: 2px solid rgba(135, 172, 58, 0.3) !important;
    transition: background-color 0.3s ease !important;
}

*::-webkit-scrollbar-thumb:hover {
    background: #6b8e23 !important;
}

*::-webkit-scrollbar-track {
    background: rgba(135, 172, 58, 0.1) !important;
    border-radius: 8px !important;
}

/* Enhanced scrollbar styling for product description areas */
.product-description::-webkit-scrollbar {
    width: 8px !important;
    background: rgba(135, 172, 58, 0.1) !important;
}

.product-description::-webkit-scrollbar-thumb {
    background: #87ac3a !important;
    border-radius: 4px !important;
    border: 1px solid rgba(135, 172, 58, 0.3) !important;
    transition: background-color 0.3s ease !important;
}

.product-description::-webkit-scrollbar-thumb:hover {
    background: #6b8e23 !important;
}

.product-description::-webkit-scrollbar-track {
    background: rgba(135, 172, 58, 0.1) !important;
    border-radius: 4px !important;
}
</style>









<?php

// Include the image carousel component and helpers
require_once __DIR__ . '/components/image_carousel.php';
require_once __DIR__ . '/components/detailed_item_modal.php';
require_once __DIR__ . '/includes/image_helper.php';
require_once __DIR__ . '/api/business_settings_helper.php';
require_once __DIR__ . '/api/marketing_helper.php';

// Initialize marketing helper
if (!isset($GLOBALS['marketingHelper'])) {
    $GLOBALS['marketingHelper'] = new MarketingHelper();
}

// Categories are already loaded in index.php and available in $categories
// Now order them by room number instead of alphabetically
$orderedCategories = [];
if (!empty($categories)) {
    try {
        // Get database connection
        require_once __DIR__ . '/api/config.php';
        $pdo = Database::getInstance();

        // Get categories ordered by room number
        $stmt = $pdo->prepare("
            SELECT c.name as category_name, rca.room_number, rca.display_order
            FROM room_category_assignments rca 
            JOIN categories c ON rca.category_id = c.id 
            WHERE rca.is_primary = 1
            ORDER BY rca.room_number ASC
        ");
        $stmt->execute();
        $roomCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Build ordered categories array based on room numbers
        foreach ($roomCategories as $roomCategory) {
            $categoryName = $roomCategory['category_name'];
            if (isset($categories[$categoryName])) {
                $orderedCategories[$categoryName] = $categories[$categoryName];
            }
        }

        // Add any remaining categories that weren't found in room assignments
        foreach ($categories as $categoryName => $categoryProducts) {
            if (!isset($orderedCategories[$categoryName])) {
                $orderedCategories[$categoryName] = $categoryProducts;
            }
        }

        // Use ordered categories if we successfully built the array
        if (!empty($orderedCategories)) {
            $categories = $orderedCategories;
        }

    } catch (Exception $e) {
        // If there's an error, just use the original categories order
        error_log("Error ordering categories by room: " . $e->getMessage());
    }
}
?>

<script>
// Shop page now uses the global item modal system
// All modal functionality is handled by js/global-item-modal.js

// Show product details using global modal system with robust retry mechanism
function showProductDetails(sku) {
    if (typeof window.showGlobalItemModal === 'function') {
        window.showGlobalItemModal(sku);
    } else {
        console.warn('Global item modal system not yet loaded, retrying...');
        // Retry with progressive delays
        let retryCount = 0;
        const maxRetries = 5;
        
        function attemptRetry() {
            retryCount++;
            if (typeof window.showGlobalItemModal === 'function') {
                console.log('Global item modal system loaded after', retryCount, 'retries');
                window.showGlobalItemModal(sku);
            } else if (retryCount < maxRetries) {
                console.warn('Retry attempt', retryCount, 'of', maxRetries);
                setTimeout(attemptRetry, 100 * retryCount); // Progressive delay
            } else {
                console.error('Global item modal system failed to load after', maxRetries, 'retries');
                if (window.showError) {
                window.showError('Unable to load item details. Please refresh the page and try again.');
            } else {
                alert('Unable to load item details. Please refresh the page and try again.');
            }
            }
        }
        
        setTimeout(attemptRetry, 100);
    }
}

// Make function globally available
window.showProductDetails = showProductDetails;

// Add to Cart functionality for shop page
function handleAddToCart(sku, name, price) {
    try {
        // Check if global addToCart function exists
        if (typeof window.addToCart === 'function') {
            // Use existing cart system
            window.addToCart({
                sku: sku,
                name: name,
                price: parseFloat(price),
                quantity: 1
            });
        } else if (typeof window.cart === 'object' && typeof window.cart.addItem === 'function') {
            // Use cart object method
            window.cart.addItem({
                sku: sku,
                name: name,
                price: parseFloat(price),
                quantity: 1
            });
        } else {
            // Fallback: show success message and log to console
            console.log('Added to cart:', { sku, name, price });
            if (window.showSuccess) {
                window.showSuccess(`Added ${name} to cart!`);
            } else {
                alert(`Added ${name} to cart!`);
            }
        }
    } catch (error) {
        console.error('Error adding item to cart:', error);
        if (window.showError) {
            window.showError('Failed to add item to cart. Please try again.');
        } else {
            alert('Failed to add item to cart. Please try again.');
        }
    }
}

// Make function globally available
window.handleAddToCart = handleAddToCart;

// Anti-ellipsis code removed as requested
</script>

<section id="shopPage" class="page-content">

    <!-- Fixed Navigation Area - Stays at top, doesn't scroll -->
    <div class="shop-navigation-area">
        <!-- Navigation Bar: 3-Column CSS Grid Layout -->
    <div class="navigation-bar">
        <?php
        // Only show room main navigation image on non-room_main pages
        $current_page = $_GET['page'] ?? 'landing';
        if ($current_page !== 'room_main'):
        ?>
        <!-- Left Column: Room Main Navigation Image (left-aligned within column) -->
        <div class="room-main-nav-container">
            <a href="/?page=room_main" class="room-main-nav-link" title="Go to Main Room">
                <picture>
                    <source srcset="images/signs/sign_main.webp" type="image/webp">
                    <img src="images/signs/sign_main.png" alt="Rooms" class="room-main-nav-image">
                </picture>
            </a>
        </div>
        <?php endif; ?>

        <!-- Middle Column: Category Filter Buttons (center-aligned within column) -->
        <div class="category-navigation"
             style="
                 justify-self: center !important;
                 display: flex !important;
                 flex-wrap: wrap !important;
                 justify-content: center !important;
                 align-items: center !important;
                 gap: 15px !important;
                 width: 100% !important;
             ">
            <!-- All Products button first -->
            <button class="category-btn category_btn_bg category_btn_color rounded-full border-none transition-colors active"
                    data-category="all">
                All Products
            </button>
            <!-- Then individual categories in order -->
            <?php foreach (array_keys($categories) as $category): ?>
                <button class="category-btn category_btn_bg category_btn_color category_btn_hover_bg rounded-full border-none transition-colors"
                        data-category="<?php echo htmlspecialchars($category); ?>">
                    <?php echo htmlspecialchars($category); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Right Column: Empty spacer (same width as room image for proper centering) -->
        <div class="navigation-spacer"></div>
    </div>
    </div> <!-- End shop-navigation-area -->

    <!-- Scrollable Content Area - Only this area scrolls -->
    <div class="shop-content-area">
        <!-- Products Grid - CSS Grid Layout for Equal Heights -->
    <div id="productsGrid">
        <?php
        // Check if categories exist
        if (empty($categories)) {
            echo '<div class="text-center p-8"><h2 class="text-xl text-red-600">No categories found. Database connection issue?</h2></div>';
        }

        // Display all products with proper text wrapping and images
        foreach ($categories as $category => $products):
            foreach ($products as $product):
                // Skip products without required fields
                if (!isset($product['productName']) || !isset($product['price'])) {
                    continue;
                }

                // Simple product details without complex processing
                $productName = htmlspecialchars($product['productName'] ?? 'Unknown Product');
                $sku = htmlspecialchars($product['sku'] ?? 'NO-SKU');
                $price = htmlspecialchars($product['price'] ?? '0.00');
                $stock = (int)($product['stock'] ?? 0);
                $description = htmlspecialchars(substr($product['description'] ?? 'No description available', 0, 100));

                // Get custom button text or use default
                $customButtonText = !empty($product['custom_button_text']) ?
                    htmlspecialchars($product['custom_button_text']) : 'Add to Cart';

                // Get primary image
                $primaryImageData = getPrimaryImageBySku($sku);
                $primaryImage = $primaryImageData ? $primaryImageData['image_path'] : null;

                // Simple formatting
                $formattedPrice = '$' . number_format((float)$price, 2);
                ?>
        <div class="product-card bg-white rounded-lg p-4 shadow-lg" data-category="<?php echo htmlspecialchars($category); ?>"
             style="
                 display: flex !important;
                 flex-direction: column !important;
                 height: 100% !important;
                 min-height: 450px !important;
                 max-width: 320px !important;
                 background-color: white !important;
                 border-radius: 8px !important;
                 padding: 1rem !important;
                 box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
                 overflow: hidden !important;
                 box-sizing: border-box !important;
                 margin: 0 !important;
             ">
            <!-- Product Image -->
            <div class="product-image-container">
                <div class="bg-gray-200 h-32 w-full rounded mb-4 flex items-center justify-center overflow-hidden" id="image-container-<?php echo $sku; ?>">
                    <?php if ($primaryImage && file_exists($primaryImage)): ?>
                        <img src="<?php echo htmlspecialchars($primaryImage); ?>"
                             alt="<?php echo htmlspecialchars($productName); ?>"
                             class="w-full h-full object-cover rounded"
                             style="max-width: 150px; max-height: 100%; width: auto; height: auto; object-fit: cover;"
                             onerror="this.parentElement.innerHTML='<span class=\'text-gray-500\'>📷 No Image</span>';">
                    <?php else: ?>
                        <span class="text-gray-500">📷 No Image</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Content - Flexible Area -->
            <div class="product-content"
                 style="
                     flex: 1 !important;
                     display: flex !important;
                     flex-direction: column !important;
                     margin-bottom: 15px !important;
                 ">
                <!-- Product Title -->
                <h3 class="product-title"
                    style="
                        font-size: 1.125rem !important;
                        font-weight: 700 !important;
                        color: #16a34a !important;
                        margin-bottom: 12px !important;
                        word-wrap: break-word !important;
                        overflow-wrap: break-word !important;
                        hyphens: auto !important;
                        line-height: 1.3 !important;
                        text-align: center !important;
                        display: block !important;
                    "><?php echo $productName; ?></h3>

                <!-- Product Description - Expandable Middle Section -->
                <div class="product-description"
                     style="
                         flex: 1 !important;
                         margin-bottom: 10px !important;
                         min-height: 60px !important;
                         max-height: 120px !important;
                         overflow-y: auto !important;
                         padding: 8px !important;
                         background-color: #f8f9fa !important;
                         border-radius: 4px !important;
                         border: 1px solid #e9ecef !important;
                         scrollbar-width: thin !important;
                         scrollbar-color: #87ac3a rgba(135, 172, 58, 0.3) !important;
                     ">
                    <p class="description-text"
                       style="
                           font-size: 0.875rem !important;
                           color: #6b7280 !important;
                           line-height: 1.4 !important;
                           word-wrap: break-word !important;
                           overflow-wrap: break-word !important;
                           white-space: normal !important;
                           text-align: center !important;
                           margin: 0 !important;
                           display: block !important;
                       "><?php echo $description; ?></p>
                </div>
            </div>

            <!-- Product Info - Fixed Bottom Section -->
            <div class="product-info"
                 style="
                     flex-shrink: 0 !important;
                     margin-bottom: 15px !important;
                 ">
                <!-- Price - Prominent Display -->
                <div class="product-price"
                     style="
                         font-size: 1.5rem !important;
                         font-weight: 800 !important;
                         color: #15803d !important;
                         text-align: center !important;
                         margin-bottom: 12px !important;
                         line-height: 1.2 !important;
                         letter-spacing: 0.025em !important;
                         display: block !important;
                     ">
                    <?php echo $formattedPrice; ?>
                </div>

                <!-- Stock Level - Color Coded -->
                <div class="product-stock" data-stock="<?php echo $stock; ?>"
                     style="
                         font-size: 0.875rem !important;
                         font-weight: 600 !important;
                         text-align: center !important;
                         margin-bottom: 8px !important;
                         padding: 4px 8px !important;
                         border-radius: 4px !important;
                         background-color: #f9fafb !important;
                         <?php
                         if ($stock == 0) {
                             echo 'color: #991b1b !important; background-color: #fef2f2 !important; border: 1px solid #f87171 !important; font-weight: 700 !important;';
                         } elseif ($stock <= 4) {
                             echo 'color: #dc2626 !important; background-color: #fef2f2 !important; border: 1px solid #fecaca !important;';
                         } elseif ($stock <= 9) {
                             echo 'color: #d97706 !important; background-color: #fffbeb !important; border: 1px solid #fed7aa !important;';
                         } else {
                             echo 'color: #16a34a !important; background-color: #f0fdf4 !important; border: 1px solid #bbf7d0 !important;';
                         }
                         ?>
                     ">
                    Stock: <?php echo $stock; ?>
                </div>

                <!-- Category and SKU Container - Bottom Information -->
                <div class="product-meta"
                     style="
                         display: flex !important;
                         flex-direction: column !important;
                         gap: 6px !important;
                         margin-bottom: 12px !important;
                         align-items: center !important;
                     ">
                    <!-- Category -->
                    <div class="product-category"
                         style="
                             font-size: 0.75rem !important;
                             color: #87ac3a !important;
                             text-align: center !important;
                             padding: 4px 12px !important;
                             background-color: rgba(135, 172, 58, 0.1) !important;
                             border: 1px solid #87ac3a !important;
                             border-radius: 12px !important;
                             display: inline-block !important;
                             width: fit-content !important;
                             font-weight: 600 !important;
                             text-transform: uppercase !important;
                             letter-spacing: 0.025em !important;
                         ">
                        <?php echo htmlspecialchars($category); ?>
                    </div>

                    <!-- SKU -->
                    <div class="product-sku"
                         style="
                             font-size: 0.7rem !important;
                             color: #9ca3af !important;
                             text-align: center !important;
                             font-family: 'Courier New', monospace !important;
                             font-weight: 400 !important;
                             letter-spacing: 0.05em !important;
                             opacity: 0.8 !important;
                             background-color: #f8f9fa !important;
                             padding: 2px 8px !important;
                             border-radius: 4px !important;
                             border: 1px solid #e9ecef !important;
                         ">
                        SKU: <?php echo $sku; ?>
                    </div>
                </div>
                </div>
            </div>

            <!-- Add to Cart Button - Always at Bottom -->
            <div class="product-button"
                 style="
                     flex-shrink: 0 !important;
                     margin-top: auto !important;
                 ">
                <button class="add-to-cart-btn"
                        data-sku="<?php echo $sku; ?>"
                        data-name="<?php echo htmlspecialchars($productName); ?>"
                        data-price="<?php echo $price; ?>"
                        data-custom-text="<?php echo $customButtonText; ?>"
                        onclick="handleAddToCart('<?php echo $sku; ?>', '<?php echo htmlspecialchars($productName, ENT_QUOTES); ?>', '<?php echo $price; ?>')"
                        style="
                            width: 100% !important;
                            background-color: #87ac3a !important;
                            color: white !important;
                            border: none !important;
                            padding: 12px 16px !important;
                            border-radius: 6px !important;
                            font-size: 0.875rem !important;
                            font-weight: 600 !important;
                            cursor: pointer !important;
                            transition: all 0.2s ease !important;
                            text-transform: none !important;
                            letter-spacing: 0.025em !important;
                            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
                        "
                        onmouseover="this.style.backgroundColor='#6b8e23'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 6px rgba(0, 0, 0, 0.15)';"
                        onmouseout="this.style.backgroundColor='#87ac3a'; this.style.transform='translateY(0)'; this.style.boxShadow='0 1px 3px rgba(0, 0, 0, 0.1)';"
                        onmousedown="this.style.transform='translateY(0)'; this.style.boxShadow='0 1px 3px rgba(0, 0, 0, 0.1)';"
                        onmouseup="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 6px rgba(0, 0, 0, 0.15)';">
                    <?php echo $customButtonText; ?>
                </button>
            </div>
        </div>
        <?php
            endforeach;
        endforeach;
        ?>
    </div>
    </div> <!-- End shop-content-area -->
</section>

<?php
// Shop page uses global item modal system - no quantity modal needed
?>

<!-- Container for global item modal -->
<div id="globalModalContainer"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Shop page loaded successfully');

    // Debug layout information
    const shopPage = document.getElementById('shopPage');
    const navArea = document.querySelector('.shop-navigation-area');
    const contentArea = document.querySelector('.shop-content-area');
    const productsGrid = document.getElementById('productsGrid');

    console.log('Layout Debug Info:');
    console.log('Shop Page:', shopPage ? 'Found' : 'Missing');
    console.log('Navigation Area:', navArea ? 'Found' : 'Missing');
    console.log('Content Area:', contentArea ? 'Found' : 'Missing');
    console.log('Products Grid:', productsGrid ? 'Found' : 'Missing');

    if (shopPage) {
        console.log('Shop Page Height:', shopPage.offsetHeight + 'px');
        console.log('Shop Page Display:', getComputedStyle(shopPage).display);
        console.log('Shop Page Flex Direction:', getComputedStyle(shopPage).flexDirection);
    }

    if (contentArea) {
        console.log('Content Area Height:', contentArea.offsetHeight + 'px');
        console.log('Content Area Overflow Y:', getComputedStyle(contentArea).overflowY);
        console.log('Content Area Flex:', getComputedStyle(contentArea).flex);
    }

    if (productsGrid) {
        const gridStyles = getComputedStyle(productsGrid);
        const viewportWidth = window.innerWidth;
        const gridWidth = productsGrid.offsetWidth;
        const gridMaxWidth = gridStyles.maxWidth;
        const gridPosition = gridStyles.position;
        const gridLeft = gridStyles.left;
        const gridMarginLeft = gridStyles.marginLeft;

        console.log('Viewport Width Debug:');
        console.log('Browser Viewport Width:', viewportWidth + 'px');
        console.log('Products Grid Width:', gridWidth + 'px');
        console.log('Grid Max-Width:', gridMaxWidth);
        console.log('Grid Position:', gridPosition);
        console.log('Grid Left:', gridLeft);
        console.log('Grid Margin Left:', gridMarginLeft);
        console.log('Full Width Utilization:', (gridWidth >= viewportWidth * 0.95) ? 'YES' : 'NO');

        // Check if external CSS viewport breakout is working
        const shopPageWidth = shopPage ? shopPage.offsetWidth : 0;
        const shopPageStyles = shopPage ? getComputedStyle(shopPage) : null;

        console.log('External CSS Breakout Check:');
        console.log('Shop Page Width:', shopPageWidth + 'px');
        console.log('Shop Page Position:', shopPageStyles ? shopPageStyles.position : 'unknown');
        console.log('Shop Page Left:', shopPageStyles ? shopPageStyles.left : 'unknown');
        console.log('Shop Page Margin Left:', shopPageStyles ? shopPageStyles.marginLeft : 'unknown');

        if (shopPageWidth >= viewportWidth * 0.95) {
            console.log('✅ SUCCESS: External CSS viewport breakout is working!');
            console.log('Shop page is using', Math.round((shopPageWidth / viewportWidth) * 100) + '% of viewport width');
        } else {
            console.warn('⚠️ VIEWPORT WIDTH ISSUE: External CSS breakout not working!');
            console.warn('Expected minimum width:', Math.floor(viewportWidth * 0.95) + 'px');
            console.warn('Actual shop page width:', shopPageWidth + 'px');
        }
    }

    // Simple category filtering for the simplified cards
    const categoryButtons = document.querySelectorAll('.category-btn');
    const productCards = document.querySelectorAll('[data-category]');

    categoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const category = this.getAttribute('data-category');

            // Update active button with inline styles
            categoryButtons.forEach(btn => {
                btn.classList.remove('active');
                // Reset to default state
                btn.style.backgroundColor = '#87ac3a';
                btn.style.color = 'white';
                btn.style.border = 'none';
            });

            this.classList.add('active');
            // Apply active state styles
            this.style.backgroundColor = 'white';
            this.style.color = '#87ac3a';
            this.style.border = '2px solid #87ac3a';

            // Filter products
            productCards.forEach(card => {
                if (category === 'all' || card.getAttribute('data-category') === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            // Recalculate card heights after filtering
            setTimeout(equalizeCardHeights, 100);
        });
    });

    // Enhanced function to equalize ALL card heights to the tallest card
    function equalizeCardHeights() {
        const grid = document.getElementById('productsGrid');
        if (!grid) return;

        const cards = Array.from(grid.querySelectorAll('.product-card')).filter(card =>
            card.style.display !== 'none' && getComputedStyle(card).display !== 'none'
        );

        if (cards.length === 0) return;

        // Reset all card heights first to get natural heights
        cards.forEach(card => {
            card.style.height = 'auto';
            card.style.minHeight = 'auto';
        });

        // Allow a brief moment for the DOM to update with natural heights
        setTimeout(() => {
            // Find the tallest card among ALL visible cards with enhanced measurement
            let maxHeight = 0;
            cards.forEach(card => {
                // Get the full height including padding, border, and content
                const computedStyle = getComputedStyle(card);
                const paddingTop = parseFloat(computedStyle.paddingTop) || 0;
                const paddingBottom = parseFloat(computedStyle.paddingBottom) || 0;
                const borderTop = parseFloat(computedStyle.borderTopWidth) || 0;
                const borderBottom = parseFloat(computedStyle.borderBottomWidth) || 0;

                const contentHeight = card.scrollHeight;
                const totalHeight = Math.max(card.offsetHeight, contentHeight + paddingTop + paddingBottom + borderTop + borderBottom);

                if (totalHeight > maxHeight) {
                    maxHeight = totalHeight;
                }
            });

            // Add buffer for ultra-wide screens
            const screenWidth = window.innerWidth;
            if (screenWidth >= 2000) {
                maxHeight += 20; // Extra buffer for very wide screens
            } else if (screenWidth >= 1600) {
                maxHeight += 15; // Medium buffer for wide screens
            } else if (screenWidth >= 1400) {
                maxHeight += 10; // Small buffer for ultra-wide screens
            }

            // Set ALL cards to the height of the tallest card
            cards.forEach(card => {
                card.style.height = maxHeight + 'px';
                card.style.minHeight = maxHeight + 'px';
            });

            console.log(`Equalized ${cards.length} cards to height: ${maxHeight}px (screen width: ${screenWidth}px)`);
        }, 10);
    }

    // Enhanced window resize handler for ultra-wide screens
    let resizeTimeout;
    function handleResize() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            console.log('Window resized, recalculating card heights...');
            equalizeCardHeights();
        }, 250); // Debounce resize events
    }

    // Add resize event listener
    window.addEventListener('resize', handleResize);

    // Initial equalization
    equalizeCardHeights();

    // Recalculate on window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(equalizeCardHeights, 250);
    });
});
</script>