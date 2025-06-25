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
            console.log('Global CSS variables loaded successfully');
        }
    } catch (error) {
        console.warn('Failed to load global CSS variables:', error);
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
    
    .product-icon {
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
    
    .product-icon:hover {
        transform: scale(1.1);
        z-index: 100;
    }
    
    .product-icon img {
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
    
    .product-icon.out-of-stock {
        opacity: 0.7;
        filter: grayscale(30%);
    }
    
    .product-icon.out-of-stock:hover {
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
                            <div class="product-icon <?php echo $area_class . $outOfStockClass; ?>" 
                                 data-product-id="<?php echo htmlspecialchars($item['sku']); ?>"
                                 data-stock="<?php echo $stockLevel; ?>"
                                 onmouseenter="showPopup(this, <?php echo htmlspecialchars(json_encode($itemWithImage)); ?>)"
                                 onmouseleave="hidePopup()"
                                 onclick="showProductDetails('<?php echo htmlspecialchars($item['sku']); ?>')"
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

<!-- Product popup template -->
<div id="productPopup" class="product-popup">
    <div class="popup-content">
        <img class="popup-image" src="" alt="">
        <div class="popup-details">
            <div class="popup-name"></div>
            <div class="popup-category"></div>
            <div class="popup-description"></div>
            <div class="popup-price"></div>
            <div class="popup-actions">
                <button class="popup-add-btn">Add to Cart</button>
                <div class="popup-hint" style="font-size: 11px; color: #888; text-align: center; margin-top: 5px;">Click anywhere to view details</div>
            </div>
        </div>
    </div>
</div>

<!-- Quantity Modal -->
<div id="quantityModal" class="modal-overlay hidden">
    <div class="room-modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add to Cart</h3>
            <button id="closeQuantityModal" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="product-summary">
                <img id="modalProductImage" class="modal-product-image" src="" alt="">
                <div class="product-info">
                    <h4 id="modalProductName" class="product-name">Product Name</h4>
                    <p id="modalProductPrice" class="product-price">$0.00</p>
                </div>
            </div>
            <div class="quantity-selector">
                <label for="quantityInput" class="quantity-label">Quantity:</label>
                <div class="quantity-controls">
                    <input type="number" id="quantityInput" class="qty-input" value="1" min="1" max="999">
                </div>
            </div>
            <div class="order-summary">
                <div class="summary-row">
                    <span>Unit Price:</span>
                    <span id="modalUnitPrice">$0.00</span>
                </div>
                <div class="summary-row">
                    <span>Quantity:</span>
                    <span id="modalQuantity">1</span>
                </div>
                <div class="summary-row total">
                    <span>Total:</span>
                    <span id="modalTotal">$0.00</span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button id="cancelQuantityModal" class="btn-secondary">Cancel</button>
            <button id="confirmAddToCart" class="btn-primary">Add to Cart</button>
        </div>
    </div>
</div>

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

function showPopup(element, product) {
    const now = Date.now();
    
    // Reduce debounce time for better responsiveness
    if (now - lastShowTime < 50) {
        return;
    }
    lastShowTime = now;
    
    console.log('showPopup called with:', element, product);
    
    // Prevent rapid re-triggering of same popup (anti-flashing protection)
    if (currentProduct && currentProduct.sku === product.sku && isShowingPopup) {
        clearTimeout(popupTimeout);
        return;
    }
    
    clearTimeout(popupTimeout);
    currentProduct = product;
    isShowingPopup = true;
    popupOpen = true;

    const popup = document.getElementById('productPopup');
    const popupImage = document.getElementById('popupImage');
    const popupCategory = document.getElementById('popupCategory');
    const popupTitle = document.getElementById('popupTitle');
    const popupDescription = document.getElementById('popupDescription');
    const popupPrice = document.getElementById('popupPrice');
    const popupAddBtn = document.getElementById('popupAddBtn');

    // Get the image URL - use SKU-based system
    const imageUrl = `images/items/${product.sku}A.png`;

    // Populate popup content
    popupImage.src = imageUrl;
    popupImage.onerror = function() {
        this.src = 'images/items/placeholder.png';
        this.onerror = null;
    };
    popupCategory.textContent = product.category ?? 'Category';
    popupTitle.textContent = product.name ?? product.productName ?? 'Item Name';
    popupDescription.textContent = product.description ?? 'No description available';
    
    // Check for sales and update price display
    checkAndDisplaySalePrice(product, popupPrice, null, 'popup');

    // Better positioning relative to the element
    const rect = element.getBoundingClientRect();
    const roomContainer = element.closest('.room-container');
    const containerRect = roomContainer.getBoundingClientRect();

    let left = rect.left - containerRect.left + rect.width + 10;
    let top = rect.top - containerRect.top - 50;

    // Show popup temporarily to get actual dimensions
    popup.style.display = 'block';
    popup.style.opacity = '';
    popup.classList.add('show');

    const popupRect = popup.getBoundingClientRect();
    const popupWidth = popupRect.width;
    const popupHeight = popupRect.height;

    // Reset for measurement
    popup.style.display = '';

    // Adjust if popup would go off screen horizontally
    if (left + popupWidth > containerRect.width) {
        left = rect.left - containerRect.left - popupWidth - 10;
    }
    
    // Adjust if popup would go off screen vertically (top)
    if (top < 0) {
        top = rect.top - containerRect.top + rect.height + 10;
    }
    
    // Adjust if popup would go off screen vertically (bottom) - PREVENT DOUBLE SCROLLBAR
    if (top + popupHeight > containerRect.height) {
        // Try positioning above the element first
        const topAbove = rect.top - containerRect.top - popupHeight - 10;
        if (topAbove >= 0) {
            top = topAbove;
        } else {
            // If still doesn't fit, position at bottom of container with padding
            top = containerRect.height - popupHeight - 20;
            // Ensure it doesn't go above the top
            if (top < 0) {
                top = 10;
            }
        }
    }

    popup.style.left = left + 'px';
    popup.style.top = top + 'px';

    // Clear any inline styles that might interfere and show the popup
    popup.style.opacity = '';
    popup.classList.add('show');

    // Make popup content clickable for product details
    const popupContent = popup.querySelector('.popup-content');
    popupContent.onclick = function(e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent bubbling to background click handler
        popup.classList.remove('show');
        showProductDetails(product.sku);
    };

    // Add to cart functionality using global function
    popupAddBtn.onclick = function(e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent triggering the popup content click and background
        popup.classList.remove('show');
        
        const sku = product.sku;
        const name = product.name;
        const price = parseFloat(product.retailPrice);
        const image = `images/items/${product.sku}A.png`;
        
        if (typeof window.addToCartWithModal === 'function') {
            window.addToCartWithModal(sku, name, price, image);
        } else {
            console.error('Global addToCartWithModal function not available');
        }
    };
}

function hidePopup() {
    // Clear any existing timeout
    clearTimeout(popupTimeout);
    
    // Reduce delay for faster hiding when appropriate
    popupTimeout = setTimeout(() => {
        hidePopupImmediate();
    }, 150);
}

function hidePopupImmediate() {
    const popup = document.getElementById('productPopup');
    if (popup && popup.classList.contains('show')) {
        popup.classList.remove('show');
        currentProduct = null;
        popupOpen = false;
        isShowingPopup = false;
    }
}

// Make functions globally available
window.showPopup = showPopup;
window.hidePopup = hidePopup;
window.hidePopupImmediate = hidePopupImmediate;

// Keep popup visible when hovering over it
document.getElementById('productPopup').addEventListener('mouseenter', () => {
    clearTimeout(popupTimeout);
    // Ensure popup stays visible while hovering
    isShowingPopup = true;
    popupOpen = true;
});

document.getElementById('productPopup').addEventListener('mouseleave', () => {
    hidePopup();
});

// Simple document click listener for popup closing
document.addEventListener('click', function(e) {
    const popup = document.getElementById('productPopup');
    
    // Close popup if it's open and click is outside it
    if (popup && popup.classList.contains('show') && !popup.contains(e.target) && !e.target.closest('.product-icon')) {
        hidePopupImmediate();
    }
});

// Quantity modal functionality - now handled by global functions in cart.js
// Local event listeners removed to prevent conflicts with global handlers

// Function to open quantity modal
window.openQuantityModal = function(product) {
    // Hide any existing popup first
    hidePopupImmediate();
    
    // Use global modal function if available
    if (typeof window.addToCartWithModal === 'function') {
        const sku = product.sku;
        const name = product.name || product.productName;
        const price = parseFloat(product.retailPrice || product.price);
        const image = `images/items/${product.sku}A.png`;
        
        window.addToCartWithModal(sku, name, price, image);
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
        this.src = 'images/items/placeholder.png';
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
            const existingModal = document.getElementById('detailedProductModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Create and append new detailed modal
            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = await generateDetailedModal(data.item, data.images);
            document.body.appendChild(modalContainer.firstElementChild);
            
            // Show the modal
            const detailedModal = document.getElementById('detailedProductModal');
            if (detailedModal) {
                detailedModal.classList.remove('hidden');
            }
        } else {
            console.error('Failed to load item details:', data.error);
            alert('Unable to load item details. Please try again.');
        }
    } catch (error) {
        console.error('Error loading item details:', error);
        alert('Unable to load item details. Please try again.');
    }
};

// Click-outside room functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up click-outside functionality');
    
    // Handle clicks on document body for background detection
    document.body.addEventListener('click', function(e) {
        console.log('Body clicked:', e.target);
        
        // Skip if click is on or inside room container or any UI elements
        const roomContainer = document.querySelector('#universalRoomPage .room-container');
        const backButton = document.querySelector('.back-button');
        const searchInput = document.getElementById('headerSearchInput');
        const searchModal = document.getElementById('searchModal');
        const navElement = document.querySelector('nav');
        
        // If back button was clicked, let it handle navigation
        if (e.target === backButton || (backButton && backButton.contains(e.target))) {
            console.log('Back button clicked, allowing default navigation');
            return true; // Let the link handle navigation
        }
        
        // If search input or search modal was clicked, don't redirect
        if (e.target === searchInput || (searchInput && searchInput.contains(e.target)) ||
            e.target === searchModal || (searchModal && searchModal.contains(e.target)) ||
            e.target === navElement || (navElement && navElement.contains(e.target))) {
            console.log('Search input, modal, or navigation clicked, not redirecting');
            return;
        }
        
        // If popup is open, don't handle background clicks
        const popup = document.getElementById('productPopup');
        if (popup && popup.classList.contains('show')) {
            console.log('Popup is open, not handling background click');
            return;
        }
        
        // If click is not on room container or its children, navigate to main room
        if (roomContainer && !roomContainer.contains(e.target)) {
            console.log('Click outside room container, navigating to main room');
            window.location.href = '/?page=main_room';
        }
    });
    
    // Ensure back button works
    const backButton = document.querySelector('.back-button');
    if (backButton) {
        console.log('Back button found, ensuring it works');
        
        // Remove any existing click listeners that might interfere
        const newBackButton = backButton.cloneNode(true);
        backButton.parentNode.replaceChild(newBackButton, backButton);
        
        // Add a clean click listener
        newBackButton.addEventListener('click', function(e) {
            console.log('Back button clicked via event listener');
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
                console.log(`Loaded ${ROOM_TYPE} coordinates from database:`, data.map_name);
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

<!-- Load dynamic background script -->
<script src="js/dynamic_backgrounds.js?v=<?php echo time(); ?>"></script>

<!-- Load dynamic room settings -->
<script>
// Load room settings for dynamic title and description
async function loadRoomSettings() {
    try {
        const response = await fetch(`/api/room_settings.php?action=get_room&room_number=${ROOM_NUMBER}`);
        const data = await response.json();
        
        if (data.success && data.room) {
            const room = data.room;
            // Update both SEO header and visible overlay
            document.getElementById('roomTitle').textContent = room.room_name;
            document.getElementById('roomDescription').textContent = room.description;
            document.getElementById('roomTitleOverlay').textContent = room.room_name;
            document.getElementById('roomDescriptionOverlay').textContent = room.description;
            console.log(`Loaded room settings for room ${ROOM_NUMBER}:`, room.room_name);
        } else {
            console.warn('Failed to load room settings, using defaults');
        }
    } catch (error) {
        console.error('Error loading room settings:', error);
    }
}

// Load room settings when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadRoomSettings();
});
</script>
</rewritten_file> 