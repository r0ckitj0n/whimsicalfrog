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
        color: white;
        font-size: 10px;
        font-weight: bold;
        padding: 2px 6px;
        border-radius: 10px;
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
        border: 2px solid var(--primary-color, #87ac3a);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        max-width: 400px;
    }

    .room-title-overlay .room-title {
        color: var(--primary-color, #87ac3a);
        margin: 0 0 0.5rem 0;
        font-size: 1.75rem;
        font-weight: bold;
        text-shadow: none;
    }

    .room-title-overlay .room-description {
        color: var(--primary-color, #87ac3a);
        opacity: 0.8;
        margin: 0;
        font-size: 0.95rem;
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
                            ?>
                            <div class="product-icon <?php echo $area_class . $outOfStockClass; ?>" 
                                 data-product-id="<?php echo htmlspecialchars($item['sku']); ?>"
                                 data-stock="<?php echo $stockLevel; ?>"
                                 onmouseenter="showPopup(this, <?php echo htmlspecialchars(json_encode($item)); ?>)"
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
        <img id="popupImage" class="popup-image" src="" alt="">
        <div class="popup-details">
            <div id="popupTitle" class="popup-title"></div>
            <div id="popupCategory" class="popup-category"></div>
            <div id="popupDescription" class="popup-description"></div>
            <div id="popupPrice" class="popup-price"></div>
            <div class="popup-actions">
                <button id="popupAddBtn" class="popup-add-btn">Add to Cart</button>
                <div class="popup-hint" style="font-size: 11px; color: #888; text-align: center; margin-top: 5px;">Click anywhere to view details</div>
            </div>
        </div>
    </div>
</div>

<!-- Quantity Selection Modal -->
<div id="quantityModal" class="modal-overlay hidden" style="z-index: 9999 !important;">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md" style="z-index: 10000 !important;">
        <div class="modal-header">
            <h3 class="text-lg font-bold mb-4">Add to Cart</h3>
            <button id="closeQuantityModal" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700">&times;</button>
        </div>
        <div class="modal-body">
            <div class="product-info mb-4">
                <img id="modalProductImage" class="w-16 h-16 object-cover rounded mb-2" src="" alt="Product Image">
                <h4 id="modalProductName" class="font-semibold text-lg">Product Name</h4>
                <p id="modalProductPrice" class="text-gray-600">$0.00</p>
            </div>
            <div class="quantity-controls mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Quantity:</label>
                <div class="flex items-center space-x-3">
                    <button id="decreaseQty" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded">-</button>
                    <input id="quantityInput" type="number" min="1" max="999" value="1" class="w-20 text-center border border-gray-300 rounded px-2 py-1">
                    <button id="increaseQty" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded">+</button>
                </div>
            </div>
            <div class="order-summary bg-gray-50 p-4 rounded">
                <div class="flex justify-between mb-2">
                    <span>Unit Price:</span>
                    <span id="modalUnitPrice">$0.00</span>
                </div>
                <div class="flex justify-between mb-2">
                    <span>Quantity:</span>
                    <span id="modalQuantity">1</span>
                </div>
                <div class="flex justify-between font-bold text-lg">
                    <span>Total:</span>
                    <span id="modalTotal">$0.00</span>
                </div>
            </div>
        </div>
        <div class="modal-footer flex justify-end space-x-3 mt-6">
            <button id="cancelQuantityModal" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 rounded">Cancel</button>
            <button id="confirmAddToCart" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded font-medium" onclick="console.log('Direct onclick triggered!');">Add to Cart</button>
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
    
    // Debounce rapid calls (prevent multiple calls within 100ms)
    if (now - lastShowTime < 100) {
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
    popupPrice.textContent = '$' + (parseFloat(product.retailPrice ?? product.price ?? 0)).toFixed(2);

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

    // Reset for measurement
    popup.style.display = '';

    // Adjust if popup would go off screen
    if (left + popupWidth > containerRect.width) {
        left = rect.left - containerRect.left - popupWidth - 10;
    }
    if (top < 0) {
        top = rect.top - containerRect.top + rect.height + 10;
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

    // Add to cart functionality
    popupAddBtn.onclick = function(e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent triggering the popup content click and background
        popup.classList.remove('show');
        openQuantityModal(product);
    };
}

function hidePopup() {
    // Clear any existing timeout
    clearTimeout(popupTimeout);
    
    // Add a small delay before hiding to allow moving mouse to popup
    popupTimeout = setTimeout(() => {
        hidePopupImmediate();
    }, 200); // Increased delay for stability
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

// Quantity modal functionality
let modalProduct = null;
let quantityModal, modalProductImage, modalProductName, modalProductPrice;
let modalUnitPrice, modalQuantity, modalTotal, quantityInput;
let decreaseQtyBtn, increaseQtyBtn, closeModalBtn, cancelModalBtn, confirmAddBtn;

// Initialize modal elements when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    quantityModal = document.getElementById('quantityModal');
    modalProductImage = document.getElementById('modalProductImage');
    modalProductName = document.getElementById('modalProductName');
    modalProductPrice = document.getElementById('modalProductPrice');
    modalUnitPrice = document.getElementById('modalUnitPrice');
    modalQuantity = document.getElementById('modalQuantity');
    modalTotal = document.getElementById('modalTotal');
    quantityInput = document.getElementById('quantityInput');
    decreaseQtyBtn = document.getElementById('decreaseQty');
    increaseQtyBtn = document.getElementById('increaseQty');
    closeModalBtn = document.getElementById('closeQuantityModal');
    cancelModalBtn = document.getElementById('cancelQuantityModal');
    confirmAddBtn = document.getElementById('confirmAddToCart');

    // Set up event listeners
    if (quantityInput) {
        quantityInput.addEventListener('input', function() {
            const value = Math.max(1, Math.min(999, parseInt(this.value) || 1));
            this.value = value;
            updateTotal();
        });
    }

    if (decreaseQtyBtn) {
        decreaseQtyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const current = parseInt(quantityInput.value) || 1;
            if (current > 1) {
                quantityInput.value = current - 1;
                updateTotal();
            }
        });
    }

    if (increaseQtyBtn) {
        increaseQtyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const current = parseInt(quantityInput.value) || 1;
            if (current < 999) {
                quantityInput.value = current + 1;
                updateTotal();
            }
        });
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeQuantityModal);
    }
    
    if (cancelModalBtn) {
        cancelModalBtn.addEventListener('click', closeQuantityModal);
    }

    // Close modal when clicking outside
    if (quantityModal) {
        quantityModal.addEventListener('click', function(e) {
            if (e.target === quantityModal) {
                closeQuantityModal();
            }
        });
    }

    // Confirm add to cart
    if (confirmAddBtn) {
        confirmAddBtn.addEventListener('click', function(e) {
            console.log('Confirm Add to Cart button clicked!');
            e.preventDefault();
            e.stopPropagation();
            
            if (modalProduct) {
                const quantity = parseInt(quantityInput.value) || 1;
                const sku = modalProduct.sku ?? modalProduct.id;
                const name = modalProduct.name ?? modalProduct.productName ?? 'Item';
                const price = parseFloat(modalProduct.retailPrice ?? modalProduct.price ?? 0);
                const imageUrl = `images/items/${modalProduct.sku}A.png`;
                
                console.log('Adding to cart:', { sku, name, price, quantity, imageUrl });
                
                // Try different cart methods
                let cartAdded = false;
                
                // Try window.addToCart first
                if (typeof window.addToCart === 'function') {
                    console.log('Using window.addToCart method');
                    for (let i = 0; i < quantity; i++) {
                        window.addToCart(sku, name, price, imageUrl);
                    }
                    cartAdded = true;
                    console.log('Added to cart using window.addToCart');
                } 
                // Try window.cart.addItem
                else if (typeof window.cart !== 'undefined' && typeof window.cart.addItem === 'function') {
                    console.log('Using window.cart.addItem method');
                    window.cart.addItem({
                        sku: sku,
                        name: name,
                        price: price,
                        image: imageUrl,
                        quantity: quantity
                    });
                    cartAdded = true;
                    console.log('Added to cart using window.cart.addItem');
                }
                // Try global addToCart
                else if (typeof addToCart === 'function') {
                    console.log('Using global addToCart method');
                    for (let i = 0; i < quantity; i++) {
                        addToCart(sku, name, price, imageUrl);
                    }
                    cartAdded = true;
                    console.log('Added to cart using addToCart');
                }
                
                if (cartAdded) {
                    console.log('Cart addition successful, showing notification');
                    // Show notification
                    if (window.cart && window.cart.showNotification) {
                        window.cart.showNotification(`${name} (${quantity}) added to your cart!`);
                    } else if (typeof customAlertBox === 'function') {
                        customAlertBox(`${name} (${quantity}) added to your cart!`);
                    } else {
                        alert(`${name} (${quantity}) added to your cart!`);
                    }
                    closeQuantityModal();
                } else {
                    console.error('No cart function found');
                    console.log('Available functions:', {
                        'window.addToCart': typeof window.addToCart,
                        'window.cart': typeof window.cart,
                        'window.cart.addItem': window.cart ? typeof window.cart.addItem : 'undefined',
                        'addToCart': typeof addToCart
                    });
                    alert('Unable to add item to cart. Please refresh the page and try again.');
                }
            } else {
                console.error('No product selected');
                alert('No product selected. Please try again.');
            }
        });
    } else {
        console.error('Confirm Add to Cart button not found!');
    }
});

// Function to open quantity modal
window.openQuantityModal = function(product) {
    console.log('openQuantityModal called with product:', product);
    
    // Hide any existing popup first
    hidePopupImmediate();
    
    // Get modal elements fresh each time to avoid stale references
    const quantityModal = document.getElementById('quantityModal');
    const modalProductImage = document.getElementById('modalProductImage');
    const modalProductName = document.getElementById('modalProductName');
    const modalProductPrice = document.getElementById('modalProductPrice');
    const modalUnitPrice = document.getElementById('modalUnitPrice');
    const quantityInput = document.getElementById('quantityInput');
    
    if (!quantityModal) {
        console.error('Quantity modal not found!');
        return;
    }
    
    if (!modalProductName || !modalProductPrice || !modalUnitPrice || !quantityInput) {
        console.error('Modal elements not found:', {
            modalProductName: !!modalProductName,
            modalProductPrice: !!modalProductPrice,
            modalUnitPrice: !!modalUnitPrice,
            quantityInput: !!quantityInput
        });
        return;
    }
    
    modalProduct = product;
    
    // Set product details
    modalProductName.textContent = product.name || product.productName || 'Product';
    modalProductPrice.textContent = '$' + parseFloat(product.retailPrice ?? product.price ?? 0).toFixed(2);
    modalUnitPrice.textContent = '$' + parseFloat(product.retailPrice ?? product.price ?? 0).toFixed(2);
    
    // Set product image
    if (modalProductImage) {
        const imageUrl = `images/items/${product.sku}A.png`;
        modalProductImage.src = imageUrl;
        modalProductImage.onerror = function() {
            this.src = 'images/items/placeholder.png';
            this.onerror = null;
        };
    }
    
    // Reset quantity
    quantityInput.value = 1;
    updateTotal();
    
    // Show modal
    quantityModal.classList.remove('hidden');
    console.log('Quantity modal should now be visible');
};

// Function to update total calculation
function updateTotal() {
    // Get fresh references to avoid stale DOM elements
    const quantityInput = document.getElementById('quantityInput');
    const modalQuantity = document.getElementById('modalQuantity');
    const modalTotal = document.getElementById('modalTotal');
    
    if (!quantityInput || !modalQuantity || !modalTotal) {
        console.error('Total calculation elements not found:', {
            quantityInput: !!quantityInput,
            modalQuantity: !!modalQuantity,
            modalTotal: !!modalTotal
        });
        return;
    }
    
    const quantity = parseInt(quantityInput.value) || 1;
    const unitPrice = modalProduct ? parseFloat(modalProduct.retailPrice ?? modalProduct.price ?? 0) : 0;
    const total = quantity * unitPrice;
    
    modalQuantity.textContent = quantity;
    modalTotal.textContent = '$' + total.toFixed(2);
    
    console.log('Total updated:', { quantity, unitPrice, total });
}

// Modal close functionality
function closeQuantityModal() {
    if (quantityModal) {
        quantityModal.classList.add('hidden');
    }
    if (quantityInput) {
        quantityInput.value = 1;
    }
    modalProduct = null;
}

// Show detailed item modal
window.showItemDetails = async function() {
    if (!currentProduct) return;
    
    const sku = currentProduct.sku || currentProduct.id;
    
    try {
        const response = await fetch(`/api/get_item_details.php?sku=${sku}`);
        const data = await response.json();
        
        if (data.success && data.item) {
            // Hide any existing popup first
            hidePopupImmediate();
            
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
            showDetailedModal();
        } else {
            console.error('Failed to load product details:', data.message);
            alert('Sorry, we could not load the product details. Please try again.');
        }
    } catch (error) {
        console.error('Error loading product details:', error);
        alert('Sorry, there was an error loading the product details.');
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
    <div id="detailedProductModal" class="modal-overlay" style="display: none;">
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
                                        ${hasData(item.weight) ? `
                                        <div>
                                            <span class="font-medium text-gray-600">Weight:</span>
                                            <span class="text-gray-700">${item.weight}</span>
                                        </div>
                                        ` : ''}
                                    </div>
                                </div>
                                ` : ''}
                                
                                ${hasData(item.features) ? `
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Features</h3>
                                    <p class="text-gray-700">${item.features.replace(/\n/g, '<br>')}</p>
                                </div>
                                ` : ''}
                                
                                ${(hasData(item.color_options) || hasData(item.size_options)) ? `
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Available Options</h3>
                                    <div class="space-y-2">
                                        ${hasData(item.color_options) ? `
                                        <div>
                                            <span class="font-medium text-gray-600">Colors:</span>
                                            <span class="text-gray-700">${item.color_options}</span>
                                        </div>
                                        ` : ''}
                                        ${hasData(item.size_options) ? `
                                        <div>
                                            <span class="font-medium text-gray-600">Sizes:</span>
                                            <span class="text-gray-700">${item.size_options}</span>
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

// Show detailed modal
function showDetailedModal() {
    const modal = document.getElementById('detailedProductModal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

// Close detailed modal
function closeDetailedModal() {
    const modal = document.getElementById('detailedProductModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        modal.remove();
    }
}

// Switch main image in detailed modal
function switchDetailedImage(imagePath, thumbnailElement) {
    const mainImage = document.getElementById('detailedMainImage');
    if (mainImage) {
        mainImage.src = imagePath;
    }
    
    // Update thumbnail borders
    const thumbnails = thumbnailElement.parentElement.querySelectorAll('div');
    thumbnails.forEach(thumb => thumb.className = thumb.className.replace('border-green-500', 'border-transparent'));
    thumbnailElement.className = thumbnailElement.className.replace('border-transparent', 'border-green-500');
}

// Adjust quantity in detailed modal
function adjustDetailedQuantity(change) {
    const input = document.getElementById('detailedQuantity');
    if (input) {
        const currentValue = parseInt(input.value) || 1;
        const newValue = Math.max(1, Math.min(parseInt(input.max) || 999, currentValue + change));
        input.value = newValue;
    }
}

// Add to cart from detailed modal
function addDetailedToCart(sku) {
    const quantityInput = document.getElementById('detailedQuantity');
    const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
    
    if (window.addToCart) {
        // Get product info from the detailed modal
        const productName = document.querySelector('#detailedProductModal h2').textContent;
        const priceText = document.querySelector('#detailedProductModal .text-3xl').textContent;
        const price = parseFloat(priceText.replace('$', ''));
        const image = document.getElementById('detailedMainImage').src;
        
        // Add items to cart with proper parameters
        for (let i = 0; i < quantity; i++) {
            window.addToCart(sku, productName, price, image);
        }
        
        // Show notification
        if (window.cart && window.cart.showNotification) {
            const quantityText = quantity > 1 ? ` (${quantity})` : '';
            window.cart.showNotification(`${productName}${quantityText} added to your cart!`);
        }
        
        closeDetailedModal();
    } else {
        console.error('Cart functionality not available');
    }
}

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