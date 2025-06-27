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
require_once __DIR__ . '/../api/marketing_helper.php';

// Initialize marketing helper
if (!isset($GLOBALS['marketingHelper'])) {
    $GLOBALS['marketingHelper'] = new MarketingHelper();
}

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

// Hover functions are now handled by js/sales-checker.js for consistency
// This ensures all rooms use the same standard hover implementation

// Simple document click listener for popup closing
document.addEventListener('click', function(e) {
    const popup = document.getElementById('productPopup');
    
    // Close popup if it's open and click is outside it
    if (popup && popup.classList.contains('show') && !popup.contains(e.target) && !e.target.closest('.product-icon')) {
        hidePopupImmediate();
    }
});

// Product details functionality
function showProductDetails(sku) {
    // Try to find the product in the room items
    const product = <?php echo json_encode($roomItems); ?>.find(item => item.sku === sku);
    
    if (!product) {
        console.error('Product not found:', sku);
        return;
    }

    // Use the detailed product modal component
    const modalHTML = generateDetailedProductModal(product);
    
    // Insert modal into page
    const existingModal = document.getElementById('detailedProductModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Show the modal
    showDetailedModal();
}

// Generate detailed product modal HTML
function generateDetailedProductModal(item) {
    function hasData(value) {
        return value && value.trim() !== '' && value.toLowerCase() !== 'n/a' && value !== 'null';
    }

    return `
    <div id="detailedProductModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="flex flex-col md:flex-row h-full">
                <!-- Image Section -->
                <div class="md:w-1/2 p-6">
                    <div class="relative">
                        <img id="detailedMainImage" src="images/items/${item.sku}A.webp" alt="${item.name}" 
                             class="w-full h-64 md:h-80 object-contain rounded-lg" 
                             onerror="this.onerror=null; this.src='images/items/${item.sku}A.png'; this.onerror=function(){this.src='images/items/placeholder.webp'; this.onerror=function(){this.src='images/items/placeholder.png'; this.onerror=null;};}">
                        <button onclick="closeDetailedModal()" 
                                class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-8 h-8 flex items-center justify-center hover:bg-red-600">
                            ×
                        </button>
                    </div>
                    
                    <!-- Thumbnail gallery would go here if multiple images exist -->
                    <div class="flex mt-4 space-x-2">
                        <div class="w-16 h-16 border-2 border-green-500 rounded cursor-pointer"
                             onclick="switchDetailedImage('images/items/${item.sku}A.webp', this)">
                            <img src="images/items/${item.sku}A.webp" alt="Thumbnail" class="w-full h-full object-contain rounded"
                                 onerror="this.onerror=null; this.src='images/items/${item.sku}A.png'; this.onerror=function(){this.src='images/items/placeholder.webp'; this.onerror=function(){this.src='images/items/placeholder.png'; this.onerror=null;};}">
                        </div>
                    </div>
                </div>
                
                <!-- Details Section -->
                <div class="md:w-1/2 p-6 overflow-y-auto">
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-3xl font-bold text-gray-900">${item.name}</h2>
                            <p class="text-xl text-gray-600">${item.category || 'Product'}</p>
                        </div>
                        
                        <div>
                            <span class="text-3xl font-bold text-green-600">$${parseFloat(item.retailPrice || item.price || 0).toFixed(2)}</span>
                            ${item.stockLevel !== undefined ? `<p class="text-sm text-gray-500 mt-1">Stock: ${item.stockLevel} available</p>` : ''}
                        </div>
                        
                        <!-- Quantity Selection -->
                        <div class="flex items-center space-x-4">
                            <label class="text-sm font-medium text-gray-700">Quantity:</label>
                            <div class="flex items-center space-x-2">
                                <button onclick="adjustDetailedQuantity(-1)" 
                                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded">-</button>
                                <input id="detailedQuantity" type="number" value="1" min="1" max="${item.stockLevel || 999}" 
                                       class="w-16 text-center border border-gray-300 rounded px-2 py-1">
                                <button onclick="adjustDetailedQuantity(1)" 
                                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-3 py-1 rounded">+</button>
                            </div>
                        </div>
                        
                        <!-- Add to Cart Button -->
                        <button onclick="addDetailedToCart('${item.sku}')" 
                                class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-lg transition duration-200">
                            Add to Cart
                        </button>
                        
                        <!-- Product Details -->
                        <div class="space-y-4 border-t pt-4">
                            ${hasData(item.description) ? `
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Description</h3>
                                <p class="text-gray-700">${item.description.replace(/\n/g, '<br>')}</p>
                            </div>
                            ` : ''}
                            
                            ${hasData(item.specifications) ? `
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Specifications</h3>
                                <div class="bg-gray-50 p-3 rounded">
                                    <p class="text-gray-700">${item.specifications.replace(/\n/g, '<br>')}</p>
                                </div>
                            </div>
                            ` : ''}
                            
                            ${(hasData(item.material) || hasData(item.dimensions) || hasData(item.weight)) ? `
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Product Information</h3>
                                <div class="grid grid-cols-1 gap-2">
                                    ${hasData(item.material) ? `
                                    <div class="flex justify-between">
                                        <span class="font-medium text-gray-600">Material:</span>
                                        <span class="text-gray-700">${item.material}</span>
                                    </div>
                                    ` : ''}
                                    ${hasData(item.dimensions) ? `
                                    <div class="flex justify-between">
                                        <span class="font-medium text-gray-600">Dimensions:</span>
                                        <span class="text-gray-700">${item.dimensions}</span>
                                    </div>
                                    ` : ''}
                                    ${hasData(item.weight) ? `
                                    <div class="flex justify-between">
                                        <span class="font-medium text-gray-600">Weight:</span>
                                        <span class="text-gray-700">${item.weight}</span>
                                    </div>
                                    ` : ''}
                                </div>
                            </div>
                            ` : ''}
                            
                            ${hasData(item.care_instructions) ? `
                            <div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Care Instructions</h3>
                                <div class="bg-blue-50 border-l-4 border-blue-400 p-3 rounded">
                                    <p class="text-gray-700">${item.care_instructions.replace(/\n/g, '<br>')}</p>
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
        
        // If popup is open or click is on/inside popup, don't redirect
        const popup = document.getElementById('productPopup');
        const quantityModal = document.getElementById('quantityModal');
        
        // Check if popup is visible and click is on or inside popup
        if (popup && popup.classList.contains('show')) {
            if (e.target === popup || popup.contains(e.target)) {
                console.log('Popup clicked, not redirecting');
                return;
            }
        }
        
        // Check if quantity modal is visible and click is on or inside modal
        if (quantityModal && !quantityModal.classList.contains('hidden')) {
            if (e.target === quantityModal || quantityModal.contains(e.target)) {
                console.log('Quantity modal clicked, not redirecting');
                return;
            }
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