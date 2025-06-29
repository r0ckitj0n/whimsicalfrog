<?php
// Universal room template - determines room data from URL
$roomNumber = isset($_GET['page']) ? str_replace('room', '', $_GET['page']) : '2';
$roomType = "room{$roomNumber}";

// Get room-specific items from categories dynamically from database
$roomItems = [];
$roomCategoryName = '';
$roomSettings = null;
$seoData = [];

try {
    // Get the primary category for this room directly from database (avoid HTTP request loop)
    require_once __DIR__ . '/../api/config.php';
    $tempPdo = new PDO($dsn, $user, $pass, $options);
    
    $stmt = $tempPdo->prepare("
        SELECT rca.*, c.name, c.description, c.id as category_id
        FROM room_category_assignments rca 
        JOIN categories c ON rca.category_id = c.id 
        WHERE rca.room_number = ? AND rca.is_primary = 1
        LIMIT 1
    ");
    $stmt->execute([$roomNumber]);
    $primaryCategory = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($primaryCategory) {
        $roomCategoryName = $primaryCategory['name'];
        
        // Get items for this category if it exists in our loaded categories
        if (isset($categories[$roomCategoryName])) {
            $roomItems = $categories[$roomCategoryName];
        }
    }
    
    // Get room settings for SEO data
    $stmt = $tempPdo->prepare("SELECT * FROM room_settings WHERE room_number = ?");
    $stmt->execute([$roomNumber]);
    $roomSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Build SEO data
    $seoData = [
        'title' => $roomSettings ? $roomSettings['room_name'] : "Shop {$roomCategoryName}",
        'description' => $roomSettings ? $roomSettings['description'] : "Browse our collection of {$roomCategoryName} at WhimsicalFrog",
        'category' => $roomCategoryName,
        'products' => $roomItems,
        'canonical' => "/?page=room{$roomNumber}",
        'image' => "images/{$roomType}.webp"
    ];
    
} catch (Exception $e) {
    error_log("Error loading room category for room {$roomNumber}: " . $e->getMessage());
    
    // Fallback to hardcoded mapping only if database lookup fails
    $fallbackMap = [
        '2' => 'T-Shirts',
        '3' => 'Tumblers', 
        '4' => 'Artwork',
        '5' => 'Sublimation',
        '6' => 'Window Wraps'
    ];
    
    if (isset($fallbackMap[$roomNumber]) && isset($categories[$fallbackMap[$roomNumber]])) {
        $roomCategoryName = $fallbackMap[$roomNumber];
        $roomItems = $categories[$fallbackMap[$roomNumber]];
        
        // Build fallback SEO data
        $seoData = [
            'title' => "Shop {$roomCategoryName} - WhimsicalFrog",
            'description' => "Browse our collection of {$roomCategoryName} at WhimsicalFrog",
            'category' => $roomCategoryName,
            'products' => $roomItems,
            'canonical' => "/?page=room{$roomNumber}",
            'image' => "images/{$roomType}.webp"
        ];
    }
}

// Include image helpers for room pages
require_once __DIR__ . '/../includes/item_image_helpers.php';
require_once __DIR__ . '/../api/business_settings_helper.php';

// Generate structured data for SEO
function generateStructuredData($seoData) {
    $structuredData = [
        "@context" => "https://schema.org",
        "@type" => "CollectionPage",
        "name" => $seoData['title'],
        "description" => $seoData['description'],
        "url" => "https://whimsicalfrog.us" . $seoData['canonical'],
        "image" => "https://whimsicalfrog.us/" . $seoData['image'],
        "mainEntity" => [
            "@type" => "ItemList",
            "name" => $seoData['category'] . " Collection",
            "numberOfItems" => count($seoData['products']),
            "itemListElement" => []
        ]
    ];
    
    // Add products to structured data
    foreach ($seoData['products'] as $index => $product) {
        $structuredData['mainEntity']['itemListElement'][] = [
            "@type" => "ListItem",
            "position" => $index + 1,
            "item" => [
                "@type" => "Product",
                "name" => $product['productName'] ?? $product['name'],
                "sku" => $product['sku'],
                "description" => $product['description'] ?? '',
                "offers" => [
                    "@type" => "Offer",
                    "price" => $product['retailPrice'] ?? $product['price'],
                    "priceCurrency" => "USD",
                    "availability" => ($product['stockLevel'] ?? 0) > 0 ? "https://schema.org/InStock" : "https://schema.org/OutOfStock"
                ]
            ]
        ];
    }
    
    return json_encode($structuredData, JSON_UNESCAPED_SLASHES);
}
?>

<!-- SEO Meta Tags -->
<title><?php echo htmlspecialchars($seoData['title']); ?> | WhimsicalFrog</title>
<meta name="description" content="<?php echo htmlspecialchars($seoData['description']); ?>">
<meta name="keywords" content="<?php echo htmlspecialchars($seoData['category']); ?>, WhimsicalFrog, custom products, online store">
<link rel="canonical" href="https://whimsicalfrog.us<?php echo htmlspecialchars($seoData['canonical']); ?>">

<!-- Open Graph Tags -->
<meta property="og:title" content="<?php echo htmlspecialchars($seoData['title']); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($seoData['description']); ?>">
<meta property="og:image" content="https://whimsicalfrog.us/<?php echo htmlspecialchars($seoData['image']); ?>">
<meta property="og:url" content="https://whimsicalfrog.us<?php echo htmlspecialchars($seoData['canonical']); ?>">
<meta property="og:type" content="website">

<!-- Twitter Card Tags -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo htmlspecialchars($seoData['title']); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($seoData['description']); ?>">
<meta name="twitter:image" content="https://whimsicalfrog.us/<?php echo htmlspecialchars($seoData['image']); ?>">

<!-- Structured Data -->
<script type="application/ld+json">
<?php echo generateStructuredData($seoData); ?>
</script>

<!-- Include room headers and popup CSS -->
<link href="css/room-headers.css?v=<?php echo time(); ?>" rel="stylesheet">
<link href="css/room-popups.css?v=<?php echo time(); ?>" rel="stylesheet">

<!-- Load Global CSS Variables -->
<script>
// Load and inject global CSS variables
async function loadGlobalCSS() {
    try {
        const response = await fetch('/api/global_css_rules.php?action=generate_css');
        const data = await response.json();
        
        if (data.success && data.css_content) {
            // Create or update global CSS style element
            let globalStyle = document.getElementById('globalCSSVariables');
            if (!globalStyle) {
                globalStyle = document.createElement('style');
                globalStyle.id = 'globalCSSVariables';
                document.head.appendChild(globalStyle);
            }
            globalStyle.textContent = data.css_content;
        }
    } catch (error) {
        // Silently fail - CSS variables will use defaults
    }
}

// Load global CSS when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadGlobalCSS();
});
</script>

<style>
    .room-container {
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        position: relative;
        border-radius: 15px;
        overflow: hidden;
    }
    
    /* Modal-specific styles */
    <?php if (isset($_GET['modal'])): ?>
    body {
        background: none !important;
        margin: 0;
        padding: 0;
    }
    
    .room-container {
        margin: 0;
        border-radius: 0;
        height: 100vh;
    }
    
    .room-overlay-wrapper {
        border-radius: 0;
        height: 100%;
        padding-top: 0;
    }
    
    .room-overlay-content {
        padding-top: 60px; /* Account for modal header */
    }
    <?php endif; ?>
    
    .room-overlay-wrapper {
        width: 100%;
        padding-top: 70%; /* 1280x896 aspect ratio (896/1280 * 100) */
        position: relative;
        background-image: url('images/<?php echo $roomType; ?>.webp?v=cb2');
        background-size: contain;
        background-position: center;
        background-repeat: no-repeat;
        border-radius: 15px;
        overflow: hidden;
    }

    .no-webp .room-overlay-wrapper {
        background-image: url('images/<?php echo $roomType; ?>.png?v=cb2');
    }

    .room-overlay-content {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .shelf-area {
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
    }
    
    .item-icon {
        position: absolute;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        z-index: 10;
        pointer-events: auto;
    }
    
    .item-icon:hover {
        transform: scale(1.1);
        z-index: 100;
    }
    
    .item-icon img {
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    /* Out of stock badge styling */
    .out-of-stock-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #dc2626;
        color: black;
        font-size: 12px;
        font-weight: bold;
        padding: 4px 8px;
        border-radius: 12px;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        z-index: 10;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .item-icon.out-of-stock {
        opacity: 0.7;
        filter: grayscale(30%);
    }
    
    .item-icon.out-of-stock:hover {
        opacity: 0.9;
        filter: grayscale(10%);
    }

    /* Modal Add to Cart button styling */
    div #confirmAddToCart,
    #confirmAddToCart {
        background-color: #87ac3a !important;
        color: #ffffff !important;
        border: none !important;
        padding: 8px 16px !important;
        border-radius: 6px !important;
        font-weight: 500 !important;
        transition: all 0.2s ease !important;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    }

    div #confirmAddToCart:hover,
    #confirmAddToCart:hover {
        background-color: #6b8e23 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    }

    /* Room Header Overlay Styling */
    .room-header-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        z-index: 100;
        pointer-events: none; /* Allow clicks to pass through to room elements */
    }

    .back-button-container {
        position: absolute;
        top: 1rem;
        left: 1rem;
        pointer-events: auto; /* Enable clicks on the button */
    }

    .back-to-main-button {
        background: var(--back-button-bg-color, rgba(107, 142, 35, 0.9));
        color: var(--back-button-text-color, #ffffff) !important;
        font-weight: 600;
        padding: 12px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        white-space: nowrap;
        min-width: 220px;
        justify-content: center;
    }
    
    .back-to-main-button:hover {
        background: var(--back-button-hover-bg, rgba(107, 142, 35, 1)) !important;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        color: var(--back-button-text-color, #ffffff) !important;
    }
    
    .back-to-main-button svg {
        color: var(--back-button-text-color, #ffffff) !important;
        stroke: var(--back-button-text-color, #ffffff) !important;
    }
    
    .back-to-main-button span {
        color: var(--back-button-text-color, #ffffff) !important;
    }

    .room-title-overlay {
        position: absolute;
        top: 1rem;
        right: 1rem;
        text-align: right;
        pointer-events: auto; /* Enable text selection */
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 0.75rem;
        border: 2px solid var(--room-title-color, #87ac3a);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        max-width: 400px;
    }

    .room-title-overlay .room-title {
        color: var(--room-title-color, #87ac3a);
        margin: 0 0 0.5rem 0;
        font-size: var(--room-title-font-size, 1.75rem);
        font-weight: bold;
        text-shadow: none;
        font-family: var(--room-title-font-family, 'Merienda', cursive);
    }

    .room-title-overlay .room-description {
        color: var(--room-description-color, #87ac3a);
        opacity: 0.8;
        margin: 0;
        font-size: var(--room-description-font-size, 0.95rem);
        line-height: 1.4;
        text-shadow: none;
    }
</style>

<!-- Room Header with Dynamic Content and SEO Structure (Hidden, for SEO only) -->
<header class="room-header" role="banner" style="display: none;">
    <h1 id="roomTitle" class="room-title"><?php echo htmlspecialchars($seoData['title']); ?></h1>
    <p id="roomDescription" class="room-description"><?php echo htmlspecialchars($seoData['description']); ?></p>
</header>

<!-- Universal Room Section with Semantic HTML -->
<main id="universalRoomPage" class="p-2" role="main">
    <section class="product-collection" aria-labelledby="roomTitle">
    <div class="room-container">
        <!-- Room Header Inside Container -->
        <div class="room-header-overlay">
            <div class="back-button-container">
                <a href="/?page=main_room" class="back-to-main-button">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    <span>Back to Main Room</span>
                </a>
            </div>
            <div class="room-title-overlay">
                <h1 id="roomTitleOverlay" class="room-title"><?php echo htmlspecialchars($seoData['title']); ?></h1>
                <p id="roomDescriptionOverlay" class="room-description"><?php echo htmlspecialchars($seoData['description']); ?></p>
            </div>
        </div>
        
        <div class="room-overlay-wrapper">
            <div class="room-overlay-content">
                <div class="shelf-area">
                    <!-- Product icons will be dynamically positioned here -->
                    <?php if (!empty($roomItems)): ?>
                        <?php foreach ($roomItems as $index => $item): ?>
                            <?php
                            $area_class = 'area-' . ($index + 1);
                            $stockLevel = isset($item['stockLevel']) ? (int)$item['stockLevel'] : 0;
                            $isOutOfStock = $stockLevel <= 0;
                            $outOfStockClass = $isOutOfStock ? ' out-of-stock' : '';
                            
                            // Get primary image using helper function
                            $primaryImageUrl = getImageWithFallback($item['sku']);
                            
                            // Add image information to item data for popup
                            $itemWithImage = $item;
                            $itemWithImage['primaryImageUrl'] = $primaryImageUrl;
                            ?>
                            <div class="item-icon <?php echo $area_class . $outOfStockClass; ?>" 
                                 data-product-id="<?php echo htmlspecialchars($item['sku']); ?>"
                                 data-stock="<?php echo $stockLevel; ?>"
                                 onmouseenter="showGlobalPopup(this, <?php echo htmlspecialchars(json_encode($itemWithImage)); ?>)"
                                 onmouseleave="hideGlobalPopup()"
                                 onclick="openQuantityModal(<?php echo htmlspecialchars(json_encode($item)); ?>)"
                                 style="cursor: pointer;">
                                <img src="<?php echo htmlspecialchars($primaryImageUrl); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name'] ?? 'Product'); ?>" 
                                     loading="lazy">
                                <?php if ($isOutOfStock): ?>
                                    <div class="out-of-stock-badge">Out of Stock</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    </section>
</main>

<?php 
require_once __DIR__ . '/../components/global_popup.php';
echo renderGlobalPopup();
echo renderGlobalPopupCSS();
?>

<!-- Quantity Modal - Compact with Large Image -->
<div id="quantityModal" class="modal-overlay hidden">
    <div class="room-modal-content">
        <div class="modal-header" style="margin-bottom: 15px;">
            <h3 class="modal-title" style="font-size: 1.1rem;">Add to Cart</h3>
            <button id="closeQuantityModal" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Large Product Image -->
            <div style="text-align: center; margin-bottom: 15px;">
                <img id="modalProductImage" class="modal-product-image" src="" alt="" 
                     style="width: 120px; height: 120px; object-fit: contain; border-radius: 8px; background: #f8f9fa;">
            </div>
            
            <!-- Compact Product Info -->
            <div style="text-align: center; margin-bottom: 15px;">
                <h4 id="modalProductName" class="product-name" style="font-size: 1rem; margin: 0 0 5px 0; color: #333;">Product Name</h4>
                <p id="modalProductPrice" class="product-price" style="font-size: 1.1rem; font-weight: bold; margin: 0; color: #87ac3a;">$0.00</p>
            </div>
            
            <!-- Compact Quantity Selector -->
            <div class="quantity-selector">
                <label for="quantityInput" class="quantity-label">Quantity:</label>
                <div class="quantity-controls">
                    <input type="number" id="quantityInput" class="qty-input" value="1" min="1" max="999">
                </div>
            </div>
            
            <!-- Compact Order Summary -->
            <div class="order-summary" style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                <div class="summary-row total" style="display: flex; justify-content: space-between; align-items: center; font-size: 1.1rem; font-weight: bold;">
                    <span>Total:</span>
                    <span id="modalTotal" style="color: #87ac3a;">$0.00</span>
                </div>
                <div style="display: flex; justify-content: center; margin-top: 5px; font-size: 0.85rem; color: #666;">
                    <span id="modalUnitPrice">$0.00</span> × <span id="modalQuantity">1</span>
                </div>
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 10px;">
            <button id="cancelQuantityModal" class="btn-secondary" style="flex: 1; padding: 10px; border: 1px solid #ddd; background: #f8f9fa; border-radius: 6px; cursor: pointer;">Cancel</button>
            <button id="confirmAddToCart" class="btn-primary" style="flex: 1; padding: 10px; background: #87ac3a; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Add to Cart</button>
        </div>
    </div>
</div>

<!-- Container for global item modal -->
<div id="globalModalContainer"></div>

<script>
// Universal room functionality
const ROOM_NUMBER = <?php echo json_encode($roomNumber); ?>;
const ROOM_TYPE = <?php echo json_encode($roomType); ?>;

// Popup system variables
let currentProduct = null;
let popupTimeout = null;
let popupOpen = false;
let isShowingPopup = false;
let lastShowTime = 0;

// Popup functions now use the global system
function showPopup(element, product) {
    if (typeof window.showGlobalPopup === 'function') {
        window.showGlobalPopup(element, product);
    } else {
        console.error('Global popup system not available');
    }
}

function hidePopup() {
    if (typeof window.hideGlobalPopup === 'function') {
        window.hideGlobalPopup();
    }
}

function hidePopupImmediate() {
    if (typeof window.hideGlobalPopupImmediate === 'function') {
        window.hideGlobalPopupImmediate();
    }
}

// Make functions globally available for backward compatibility
window.showPopup = showPopup;
window.hidePopup = hidePopup;
window.hidePopupImmediate = hidePopupImmediate;

// Quantity modal functionality - now handled by global functions in cart.js
// Local event listeners removed to prevent conflicts with global handlers

// Function to open quantity modal
window.openQuantityModal = function(product) {
    // Hide any existing popup first
    hidePopupImmediate();
    
    // Use the global modal system
    if (typeof window.showGlobalItemModal === 'function') {
        window.showGlobalItemModal(product.sku);
        return;
    }
    
    // Fallback to local modal if global not available
    const quantityModal = document.getElementById('quantityModal');
    const modalProductImage = document.getElementById('modalProductImage');
    const modalProductName = document.getElementById('modalProductName');
    const modalProductPrice = document.getElementById('modalProductPrice');
    const modalUnitPrice = document.getElementById('modalUnitPrice');
    const modalQuantity = document.getElementById('modalQuantity');
    const modalTotal = document.getElementById('modalTotal');
    const quantityInput = document.getElementById('quantityInput');
    
    if (!quantityModal) {
        console.error('Quantity modal not found!');
        return;
    }
    
    // Store product for later use
    window.currentModalProduct = product;
    
    // Set product details
    modalProductName.textContent = product.name || product.productName || 'Product';
    
    // Check for sales and update pricing in modal
    checkAndDisplaySalePrice(product, modalProductPrice, modalUnitPrice, 'modal');
    
    // Set product image
    const imageUrl = `images/items/${product.sku}A.png`;
    modalProductImage.src = imageUrl;
    modalProductImage.onerror = function() {
        this.src = 'images/items/placeholder.webp';
        this.onerror = null;
    };
    
    // Reset quantity
    quantityInput.value = 1;
    updateTotal();
    
    // Show modal
    quantityModal.classList.remove('hidden');
};

// Function to update total calculation
function updateTotal() {
    const quantityInput = document.getElementById('quantityInput');
    const modalUnitPrice = document.getElementById('modalUnitPrice');
    const modalQuantity = document.getElementById('modalQuantity');
    const modalTotal = document.getElementById('modalTotal');
    
    const quantity = parseInt(quantityInput.value) || 1;
    
    // Get the current unit price from the modal (which may be on sale)
    let unitPrice = 0;
    if (modalUnitPrice && modalUnitPrice.textContent) {
        const priceText = modalUnitPrice.textContent.replace('$', '');
        unitPrice = parseFloat(priceText) || 0;
    } else if (window.currentModalProduct) {
        unitPrice = parseFloat(window.currentModalProduct.retailPrice ?? window.currentModalProduct.price ?? 0);
    }
    
    const total = quantity * unitPrice;
    
    modalQuantity.textContent = quantity;
    modalTotal.textContent = '$' + total.toFixed(2);
}

// Show detailed item modal
window.showItemDetails = async function() {
    if (!currentProduct) return;
    
    const sku = currentProduct.sku || currentProduct.id;
    
    try {
        const response = await fetch(`/api/get_item_details.php?sku=${sku}`);
        const data = await response.json();
        
        if (data.success && data.item) {
            // Hide the popup first
            hidePopup();
            
            // Remove any existing detailed modal
            const existingModal = document.getElementById('detailedItemModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Create and append new detailed modal
            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = await generateDetailedModal(data.item, data.images);
            document.body.appendChild(modalContainer.firstElementChild);
            
            // Show the modal
            const detailedModal = document.getElementById('detailedItemModal');
            if (detailedModal) {
                detailedModal.classList.remove('hidden');
            }
        } else {
            console.error('Failed to load item details:', data.error);
            showError('Unable to load item details. Please try again.');
        }
    } catch (error) {
        console.error('Error loading item details:', error);
                    showError('Unable to load item details. Please try again.');
    }
};

// Click-outside room functionality
document.addEventListener('DOMContentLoaded', function() {

    
    // Handle clicks on document body for background detection
    document.body.addEventListener('click', function(e) {

        
        // Skip if click is on or inside room container or any UI elements
        const roomContainer = document.querySelector('#universalRoomPage .room-container');
        const backButton = document.querySelector('.back-button');
        
        // If back button was clicked, let it handle navigation
        if (e.target === backButton || (backButton && backButton.contains(e.target))) {

            return true; // Let the link handle navigation
        }
        
        // If popup is open, don't handle background clicks
        const popup = document.getElementById('productPopup');
        if (popup && popup.classList.contains('show')) {

            return;
        }
        
        // If click is not on room container or its children, navigate to main room
        if (roomContainer && !roomContainer.contains(e.target)) {

            window.location.href = '/?page=main_room';
        }
    });
    
    // Ensure back button works
    const backButton = document.querySelector('.back-button');
    if (backButton) {

        
        // Remove any existing click listeners that might interfere
        const newBackButton = backButton.cloneNode(true);
        backButton.parentNode.replaceChild(newBackButton, backButton);
        
        // Add a clean click listener
        newBackButton.addEventListener('click', function(e) {

            // Let the default link behavior happen
        });
    }
});

// Script to dynamically scale product icon areas
document.addEventListener('DOMContentLoaded', function() {
    const originalImageWidth = 1280;
    const originalImageHeight = 896;
    const roomOverlayWrapper = document.querySelector('#universalRoomPage .room-overlay-wrapper');

    // Room coordinates loaded from database (database-only system)
    let baseAreas = []; // Will be loaded from database

    function updateAreaCoordinates() {
        if (!roomOverlayWrapper) {
            console.error('Room overlay wrapper not found for scaling.');
            return;
        }

        const wrapperWidth = roomOverlayWrapper.offsetWidth;
        const wrapperHeight = roomOverlayWrapper.offsetHeight;

        const wrapperAspectRatio = wrapperWidth / wrapperHeight;
        const imageAspectRatio = originalImageWidth / originalImageHeight;

        let renderedImageWidth, renderedImageHeight;
        let offsetX = 0;
        let offsetY = 0;

        if (wrapperAspectRatio > imageAspectRatio) {
            renderedImageHeight = wrapperHeight;
            renderedImageWidth = renderedImageHeight * imageAspectRatio;
            offsetX = (wrapperWidth - renderedImageWidth) / 2;
        } else {
            renderedImageWidth = wrapperWidth;
            renderedImageHeight = renderedImageWidth / imageAspectRatio;
            offsetY = (wrapperHeight - renderedImageHeight) / 2;
        }

        const scaleX = renderedImageWidth / originalImageWidth;
        const scaleY = renderedImageHeight / originalImageHeight;

        baseAreas.forEach(areaData => {
            const areaElement = roomOverlayWrapper.querySelector(areaData.selector);
            if (areaElement) {
                areaElement.style.top = (areaData.top * scaleY + offsetY) + 'px';
                areaElement.style.left = (areaData.left * scaleX + offsetX) + 'px';
                areaElement.style.width = (areaData.width * scaleX) + 'px';
                areaElement.style.height = (areaData.height * scaleY) + 'px';
            }
        });
    }

    // Load coordinates from database first, then initialize
    loadRoomCoordinatesFromDatabase();
    
    async function loadRoomCoordinatesFromDatabase() {
        try {
            const response = await fetch(`api/get_room_coordinates.php?room_type=${ROOM_TYPE}`);
            
            // Check if the response is ok (not 500 error)
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: Database not available`);
            }
            
            const data = await response.json();
            
            if (data.success && data.coordinates && data.coordinates.length > 0) {
                baseAreas = data.coordinates;

            } else {
                console.error(`No active room map found in database for ${ROOM_TYPE}`);
                return; // Don't initialize if no coordinates available
            }
        } catch (error) {
            console.error(`Error loading ${ROOM_TYPE} coordinates from database:`, error);
            return; // Don't initialize if database error
        }
        
        // Initialize coordinates after loading
        updateAreaCoordinates();
    }

    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(updateAreaCoordinates, 100);
    });
});

</script>

<!-- Load global popup system -->
<script src="js/global-popup.js"></script>

<script>
// Show product details in large modal (like shop page)
async function showProductDetails(sku) {
    try {
        const response = await fetch(`/api/get_item_details.php?sku=${sku}`);
        const data = await response.json();
        
        if (data.success && data.item) {
            // Hide any existing popup first
            hidePopupImmediate();
            
            // Remove any existing detailed modal
            const existingModal = document.getElementById('detailedItemModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Create and append new detailed modal
            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = await generateDetailedModal(data.item, data.images);
            document.body.appendChild(modalContainer.firstElementChild);
            
            // Show the modal
            showDetailedModal();
        } else {
            console.error('Failed to load product details:', data.message);
            showError('Sorry, we could not load the product details. Please try again.');
        }
    } catch (error) {
        console.error('Error loading product details:', error);
                    showError('Sorry, there was an error loading the product details.');
    }
}

// Generate detailed modal HTML
async function generateDetailedModal(item, images) {
    const primaryImage = images.length > 0 ? images[0] : null;
    
    // Helper function to check if field has data
    function hasData(value) {
        return value && value.trim() !== '';
    }
    
    return `
    <!-- Detailed Product Modal -->
    <div id="detailedItemModal" class="modal-overlay" style="display: none;">
        <div class="bg-white rounded-lg max-w-6xl w-full max-h-[95vh] overflow-y-auto shadow-2xl">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center z-10">
                <h2 class="text-2xl font-bold text-gray-800">${item.name}</h2>
                <button onclick="closeDetailedModal()" class="text-gray-500 hover:text-gray-700 text-3xl font-bold">
                    &times;
                </button>
            </div>
            
            <!-- Modal Content -->
            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left Column - Images -->
                    <div class="space-y-4">
                        <!-- Main Image -->
                        <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden">
                            ${primaryImage ? 
                                `<img id="detailedMainImage" 
                                     src="${primaryImage.image_path}" 
                                     alt="${item.name}"
                                     class="w-full h-full object-cover">` :
                                `<div class="w-full h-full flex items-center justify-center text-gray-400">
                                    <span>No image available</span>
                                </div>`
                            }
                        </div>
                        
                        <!-- Thumbnail Gallery -->
                        ${images.length > 1 ? `
                        <div class="grid grid-cols-4 gap-2">
                            ${images.map((image, index) => `
                            <div class="aspect-square bg-gray-100 rounded cursor-pointer overflow-hidden border-2 ${index === 0 ? 'border-green-500' : 'border-transparent hover:border-gray-300'}"
                                 onclick="switchDetailedImage('${image.image_path}', this)">
                                <img src="${image.image_path}" 
                                     alt="${item.name} - View ${index + 1}"
                                     class="w-full h-full object-cover">
                            </div>
                            `).join('')}
                        </div>
                        ` : ''}
                    </div>
                    
                    <!-- Right Column - Product Details -->
                    <div class="space-y-6">
                        <!-- Basic Info -->
                        <div>
                            <div class="text-3xl font-bold text-green-600 mb-2">
                                $${parseFloat(item.retailPrice).toFixed(2)}
                            </div>
                            ${hasData(item.description) ? `
                            <p class="text-gray-700 text-lg leading-relaxed">
                                ${item.description.replace(/\n/g, '<br>')}
                            </p>
                            ` : ''}
                        </div>
                        
                        <!-- Stock Status -->
                        <div class="flex items-center space-x-2">
                            ${item.stockLevel > 0 ? 
                                `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    ✓ In Stock (${item.stockLevel} available)
                                </span>` :
                                `<span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                    ✗ Out of Stock
                                </span>`
                            }
                        </div>
                        
                        <!-- Add to Cart Section -->
                        <div class="border-t pt-4">
                            <div class="flex items-center space-x-4 mb-4">
                                <label class="text-sm font-medium text-gray-700">Quantity:</label>
                                <div class="flex items-center border rounded-md">
                                    <button onclick="adjustDetailedQuantity(-1)" class="px-3 py-1 text-gray-600 hover:text-gray-800">-</button>
                                    <input type="number" id="detailedQuantity" value="1" min="1" max="${item.stockLevel}" 
                                           class="w-16 text-center border-0 focus:ring-0">
                                    <button onclick="adjustDetailedQuantity(1)" class="px-3 py-1 text-gray-600 hover:text-gray-800">+</button>
                                </div>
                            </div>
                            
                            ${item.stockLevel > 0 ? `
                            <button onclick="addDetailedToCart('${item.sku}')" 
                                    class="w-full bg-green-600 hover:bg-green-700 text-white py-3 px-6 rounded-lg font-medium text-lg transition-colors">
                                Add to Cart
                            </button>
                            ` : `
                            <button disabled class="w-full bg-gray-400 text-white py-3 px-6 rounded-lg font-medium text-lg cursor-not-allowed">
                                Out of Stock
                            </button>
                            `}
                        </div>
                        
                        <!-- Detailed Information -->
                        <div class="border-t pt-6">
                            <div class="space-y-4">
                                ${hasData(item.materials) ? `
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Materials</h3>
                                    <p class="text-gray-700">${item.materials.replace(/\n/g, '<br>')}</p>
                                </div>
                                ` : ''}
                                
                                ${(hasData(item.dimensions) || hasData(item.weight)) ? `
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Specifications</h3>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        ${hasData(item.dimensions) ? `
                                        <div>
                                            <span class="font-medium text-gray-600">Dimensions:</span>
                                            <span class="text-gray-700">${item.dimensions}</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `;
}

// Additional modal functionality functions would go here...

</script>