<?php
// Shop page section
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /?page=shop');
    exit;
}

// Include the image carousel component and helpers
require_once __DIR__ . '/../components/image_carousel.php';
require_once __DIR__ . '/../components/detailed_item_modal.php';
require_once __DIR__ . '/../includes/image_helper.php';
require_once __DIR__ . '/../api/business_settings_helper.php';
require_once __DIR__ . '/../api/marketing_helper.php';

// Initialize marketing helper
if (!isset($GLOBALS['marketingHelper'])) {
    $GLOBALS['marketingHelper'] = new MarketingHelper();
}

// Categories are already loaded in index.php and available in $categories
?>

<style>
    /* Use the same green from the header bar */
    :root {
        --wf-green: #87ac3a;
        --wf-green-light: #a3cc4a;
    }

    #shopPage h1 {
        color: var(--wf-green) !important; /* override global reset */
    }

    /* Category buttons styling */
    .category-btn {
        background: var(--wf-green);
        color: #ffffff !important; /* override reset */
        border: none;
        transition: background 0.2s ease;
    }

    .category-btn:hover,
    .category-btn.active {
        background: var(--wf-green-light);
        color: #ffffff !important;
    }

    /* Brand button styling for shop page - using global CSS values */
    .brand-button {
        background-color: #87ac3a;
        color: #ffffff;
        border-radius: 6px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: all 0.2s ease;
        cursor: pointer;
        border: none;
    }
    
    .brand-button:hover {
        background-color: #6b8e23 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(135, 172, 58, 0.3);
    }

    /* Out of stock badge styling for shop page */
    .product-card {
        position: relative;
    }
    
    .out-of-stock-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #dc2626;
        color: black;
        font-size: 12px;
        font-weight: bold;
        padding: 6px 10px;
        border-radius: 14px;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        z-index: 10;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .product-card.out-of-stock .bg-white {
        opacity: 0.8;
        filter: grayscale(20%);
    }
    
    .product-card.out-of-stock:hover .bg-white {
        opacity: 0.9;
        filter: grayscale(10%);
    }

    /* Complete scrollbar fix for shop page and modals */
    
    /* Prevent horizontal scrolling on shop page */
    #shopPage {
        overflow-x: hidden !important;
        max-width: 100vw;
    }
    
    /* Ensure product grid doesn't cause horizontal overflow */
    #productsGrid {
        overflow-x: hidden !important;
        max-width: 100%;
    }
    
    /* Fix body scrolling when modals are open - highest priority */
    body.modal-open {
        overflow: hidden !important;
        position: fixed !important;
        width: 100% !important;
        height: 100% !important;
        top: 0 !important;
        left: 0 !important;
    }
    
    /* Ensure html doesn't scroll when modal is open */
    html.modal-open {
        overflow: hidden !important;
        position: fixed !important;
        width: 100% !important;
        height: 100% !important;
    }
    
    /* Modal overlay - prevent any scrolling */
    .modal-overlay {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        background: rgba(0, 0, 0, 0.5) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        overflow: hidden !important;
        z-index: 9999;
    }
    
    /* Modal content with proper scrolling */
    .modal-with-scrollbar {
        display: flex;
        flex-direction: column;
        max-height: 90vh;
        overflow: hidden;
    }

    .modal-content-scrollable {
        flex: 1;
        max-height: calc(90vh - 80px);
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e0 #f7fafc;
    }
    
    /* Custom scrollbar for modal content only */
    .modal-content-scrollable::-webkit-scrollbar {
        width: 8px;
    }
    
    .modal-content-scrollable::-webkit-scrollbar-track {
        background: #f7fafc;
        border-radius: 4px;
    }
    
    .modal-content-scrollable::-webkit-scrollbar-thumb {
        background: #cbd5e0;
        border-radius: 4px;
    }
    
    .modal-content-scrollable::-webkit-scrollbar-thumb:hover {
        background: #a0aec0;
    }
    
    /* Popup Options Styling */
    .popup-options-container {
        margin: 12px 0;
        padding: 8px 0;
        border-top: 1px solid #e5e7eb;
        border-bottom: 1px solid #e5e7eb;
    }

    .popup-option-group {
        margin-bottom: 8px;
    }

    .popup-option-group:last-child {
        margin-bottom: 0;
    }

    .popup-option-group label {
        display: block;
        font-size: 12px;
        font-weight: 500;
        color: #374151;
        margin-bottom: 4px;
    }

    .popup-color-select,
    .popup-size-select,
    .popup-quantity-input {
        width: 100%;
        padding: 4px 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 12px;
        background: white;
        color: #374151;
    }

    .popup-color-select:focus,
    .popup-size-select:focus,
    .popup-quantity-input:focus {
        outline: none;
        border-color: #87ac3a;
        box-shadow: 0 0 0 1px rgba(135, 172, 58, 0.2);
    }

    .popup-quantity-input {
        max-width: 80px;
    }
</style>

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

// Scrollbar preservation override - runs after all other scripts
document.addEventListener('DOMContentLoaded', function() {
    // Store original scrollbar width
    const originalScrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    
    // Override any function that might set overflow hidden without padding
    const originalSetAttribute = Element.prototype.setAttribute;
    const originalSetProperty = CSSStyleDeclaration.prototype.setProperty;
    
    // Monitor for overflow hidden being set on body
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target === document.body && mutation.type === 'attributes' && mutation.attributeName === 'style') {
                if (document.body.style.overflow === 'hidden' && document.body.style.paddingRight !== originalScrollbarWidth + 'px') {
                    document.body.style.paddingRight = originalScrollbarWidth + 'px';
                }
            }
        });
    });
    
    observer.observe(document.body, {
        attributes: true,
        attributeFilter: ['style']
    });
    
    // Clean up observer when page unloads
    window.addEventListener('beforeunload', function() {
        observer.disconnect();
    });
});
</script>

<section id="shopPage" class="py-6">
    <h1 class="text-3xl font-merienda text-center mb-6">Welcome to Our Shop</h1>
    
    <!-- Category Navigation -->
    <div class="flex flex-wrap justify-center mb-8 gap-2">
        <?php foreach (array_keys($categories) as $category): ?>
            <button class="category-btn px-4 py-2 rounded-full"
                    data-category="<?php echo htmlspecialchars($category); ?>">
                <?php echo htmlspecialchars($category); ?>
            </button>
        <?php endforeach; ?>
        <button class="category-btn px-4 py-2 rounded-full active"
                data-category="all">
            All Products
        </button>
    </div>
    
    <!-- Products Grid -->
    <div id="productsGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php 
        // Display all products from all categories
        foreach ($categories as $category => $products): 
            foreach ($products as $product):
                // Skip products without required fields
                if (!isset($product['productName']) || !isset($product['price'])) {
                    continue;
                }
                
                // Get product details
                $productName = htmlspecialchars($product['productName'] ?? '');
                $productId = isset($product['productId']) ? htmlspecialchars($product['productId'] ?? '') : '';
                $sku = isset($product['sku']) ? htmlspecialchars($product['sku'] ?? '') : $productId;
                $price = isset($product['price']) ? htmlspecialchars($product['price'] ?? '') : '';
                $stock = isset($product['stock']) ? (int)$product['stock'] : 0;
                
                // Use enhanced marketing description if available
                $enhancedDescription = getEnhancedDescription($sku, $product['description'] ?? '');
                $description = htmlspecialchars($enhancedDescription);
                
                // Clean description for JavaScript (remove HTML entities, emojis, and limit length)
                $jsDescription = strip_tags($product['description'] ?? '');
                $jsDescription = html_entity_decode($jsDescription, ENT_QUOTES, 'UTF-8');
                // Remove emojis and special characters that break JavaScript
                $jsDescription = preg_replace('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', '', $jsDescription);
                // Remove quotes and backslashes that break JavaScript
                $jsDescription = str_replace(['\\', '"', "'", "\n", "\r", "\t"], ['', '', '', ' ', ' ', ' '], $jsDescription);
                if (strlen($jsDescription) > 100) {
                    $jsDescription = substr($jsDescription, 0, 100) . '...';
                }
                
                // Clean product name for JavaScript (remove HTML entities and emojis)
                $jsProductName = html_entity_decode($productName, ENT_QUOTES, 'UTF-8');
                // Remove emojis and special characters that break JavaScript
                $jsProductName = preg_replace('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F1E0}-\x{1F1FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', '', $jsProductName);
                // Remove quotes and backslashes that break JavaScript
                $jsProductName = str_replace(['\\', '"', "'", "\n", "\r", "\t"], ['', '', '', ' ', ' ', ' '], $jsProductName);
                
                // Create safe JSON data for JavaScript
                $productData = [
                    'sku' => $sku,
                    'name' => $jsProductName,
                    'description' => $jsDescription,
                    'category' => $category,
                    'price' => $price,
                    'retailPrice' => $price,
                    'stockLevel' => $stock
                ];
                $safeJsonData = htmlspecialchars(json_encode($productData, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                
                // Get selling points for this product
                $sellingPoints = getSellingPoints($sku);
                $callToActions = getCallToActions($sku);
                
                // Format price
                $formattedPrice = '$' . number_format((float)$price, 2);
                
                // Get primary image using database-driven system
                $primaryImageData = getPrimaryImageBySku($sku);
                $imageUrl = ($primaryImageData && !empty($primaryImageData['image_path'])) ? htmlspecialchars($primaryImageData['image_path'] ?? '') : null;
        ?>
        <div class="product-card<?php echo ($stock <= 0) ? ' out-of-stock' : ''; ?>" data-category="<?php echo htmlspecialchars($category); ?>" data-stock="<?php echo $stock; ?>" data-sku="<?php echo $sku; ?>">
            <?php if ($stock <= 0): ?>
                <div class="out-of-stock-badge">Out of Stock</div>
            <?php endif; ?>
            <!-- Sale badge will be added dynamically by JavaScript -->
            <div class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-300 flex flex-col h-full cursor-pointer" 
                 onclick="showProductDetails('<?php echo $sku; ?>')"
                 data-product-data="<?php echo $safeJsonData; ?>">
                <?php 
                // Display product images using database-driven system
                if ($primaryImageData && !empty($primaryImageData['image_path'])) {
                    echo '<div class="product-image-container" style="height: 192px; display: flex; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px; overflow: hidden;">';
                    echo '<img src="' . htmlspecialchars($primaryImageData['image_path'] ?? '') . '" alt="' . htmlspecialchars($primaryImageData['alt_text'] ?: $productName) . '" style="max-width: 100%; max-height: 100%; object-fit: contain;" onerror="this.style.display=\'none\'; this.parentElement.innerHTML = \'<div style=\\\'width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#f8f9fa;color:#6c757d;\\\'><div style=\\\'font-size:3rem;margin-bottom:0.5rem;opacity:0.7;\\\'>📷</div><div style=\\\'font-size:0.9rem;font-weight:500;\\\'>Image Not Found</div></div>\';">';
                    echo '</div>';
                } else {
                    // Show CSS-only fallback if no images
                    echo '<div class="product-image-placeholder" style="height: 192px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: #f8f9fa; border-radius: 8px; color: #6c757d;">';
                    echo '<div style="font-size: 3rem; margin-bottom: 0.5rem; opacity: 0.7;">📷</div>';
                    echo '<div style="font-size: 0.9rem; font-weight: 500;">No Image Available</div>';
                    echo '</div>';
                }
                ?>
                <div class="p-4 flex flex-col flex-grow">
                    <h3 class="font-merienda text-lg text-[#87ac3a] mb-1 line-clamp-2"><?php echo $productName; ?></h3>
                    <div class="text-xs text-gray-500 mb-1"><?php echo htmlspecialchars($category); ?></div>
                    <p class="text-gray-600 mb-2 text-sm line-clamp-2 flex-grow-0">
                        <?php echo $description; ?>
                    </p>
                    
                    <?php if (!empty($sellingPoints) && count($sellingPoints) > 0): ?>
                    <div class="mb-2">
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (array_slice($sellingPoints, 0, 2) as $point): ?>
                                <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                                    ✓ <?php echo htmlspecialchars($point); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-2 text-sm <?php echo $stock>0 ? 'text-gray-600' : 'text-red-600'; ?>">In stock: <?php echo $stock; ?></div>
                    <div class="flex justify-between items-center mt-auto">
                        <span class="product-price font-bold text-black text-xl px-3 py-1" data-sku="<?php echo $sku; ?>" data-original-price="<?php echo $price; ?>"><?php echo $formattedPrice; ?></span>
                        <button class="<?php echo $stock>0 ? 'brand-button text-xs leading-tight' : 'bg-gray-400 cursor-not-allowed text-white text-xs'; ?> px-3 py-2 rounded-lg font-semibold transition-colors shadow-md hover:shadow-lg min-w-[80px] max-w-[90px]"
                                <?php if($stock==0) echo 'disabled'; ?>
                                onclick="event.stopPropagation(); showProductDetails('<?php echo $sku; ?>')"
                                data-product-id="<?php echo $productId; ?>"
                                data-product-name="<?php echo $productName; ?>"
                                data-product-price="<?php echo $price; ?>"
                                data-product-image="<?php echo $imageUrl; ?>">
                            <?php 
                            if ($stock == 0) {
                                echo 'Out of Stock';
                            } else {
                                // Use random cart button text if no specific call to action is set
                                if (!empty($callToActions)) {
                                    $buttonText = htmlspecialchars($callToActions[0]);
                                } else {
                                    $buttonText = htmlspecialchars(getRandomCartButtonText());
                                }
                                
                                // Smart text wrapping - split at better points
                                if (strlen($buttonText) > 8) {
                                    // Common button text patterns and their optimal breaks
                                    $betterBreaks = [
                                        'Add to Cart' => 'Add to<br>Cart',
                                        'Buy Now' => 'Buy<br>Now',
                                        'Order Now' => 'Order<br>Now',
                                        'Get Yours Today' => 'Get Yours<br>Today',
                                        'Shop Now' => 'Shop<br>Now',
                                        'Purchase' => 'Purchase',
                                        'Buy Today' => 'Buy<br>Today',
                                        'Order Today' => 'Order<br>Today'
                                    ];
                                    
                                    if (isset($betterBreaks[$buttonText])) {
                                        echo $betterBreaks[$buttonText];
                                    } else {
                                        // Fallback: split longer text at better points
                                        $words = explode(' ', $buttonText);
                                        if (count($words) >= 2) {
                                            $midpoint = ceil(count($words) / 2);
                                            $firstHalf = implode(' ', array_slice($words, 0, $midpoint));
                                            $secondHalf = implode(' ', array_slice($words, $midpoint));
                                            echo $firstHalf . '<br>' . $secondHalf;
                                        } else {
                                            echo $buttonText;
                                        }
                                    }
                                } else {
                                    echo $buttonText;
                                }
                            }
                            ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php 
            endforeach;
        endforeach; 
        ?>
    </div>
</section>

<?php
// Shop page uses global item modal system - no quantity modal needed
?>



<!-- Container for global item modal -->
<div id="globalModalContainer"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Category filtering
    const categoryButtons = document.querySelectorAll('.category-btn');
    const productCards = document.querySelectorAll('.product-card');
    
    categoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            
            // Update active button (CSS handles color changes)
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Filter products
            productCards.forEach(card => {
                if (category === 'all' || card.getAttribute('data-category') === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
    
    // Modal functionality now handled by global functions in cart.js
    
    // Use global add to cart function (defined in cart.js)
    function openQuickAddModal(sku, name, price, image) {
        if (typeof window.addToCartWithModal === 'function') {
            window.addToCartWithModal(sku, name, price, image);
        } else {
            console.error('Global addToCartWithModal function not available');
        }
    }
    
    // Make functions globally available
    window.openQuickAddModal = openQuickAddModal;
    
    // Shop page now uses detailed modal directly - no quantity modal needed
        
    // Check for sales on all products when page loads
    const productPriceElements = document.querySelectorAll('.product-price');
    
    productPriceElements.forEach(async (priceElement) => {
        const sku = priceElement.getAttribute('data-sku');
        const originalPrice = parseFloat(priceElement.getAttribute('data-original-price'));
        
        if (sku && originalPrice) {
            // Create a product object for the sales checker
            const product = {
                sku: sku,
                price: originalPrice,
                retailPrice: originalPrice
            };
            
            // Check for sales and update price display
            await checkAndDisplaySalePrice(product, priceElement, null, 'card');
            
            // Also add sale badge to the product card if item is on sale
            const productCard = priceElement.closest('.product-card');
            if (productCard) {
                await addSaleBadgeToCard(sku, productCard);
            }
        }
    });
});
</script>