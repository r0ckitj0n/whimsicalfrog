<?php
// Shop page section
if (!defined('INCLUDED_FROM_INDEX')) {
    header('Location: /?page=shop');
    exit;
}

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

// Anti-ellipsis code removed as requested
</script>

<section id="shopPage" class="">
    <!- Category Navigation ->
    <div class="flex flex-wrap justify-center gap-2">
        <!- All Products button first ->
        <button class="category-btn category_btn_bg category_btn_color rounded-full border-none transition-colors active"
                data-category="all">
            All Products
        </button>
        <!- Then individual categories in order ->
        <?php foreach (array_keys($categories) as $category): ?>
            <button class="category-btn category_btn_bg category_btn_color category_btn_hover_bg rounded-full border-none transition-colors"
                    data-category="<?php echo htmlspecialchars($category); ?>">
                <?php echo htmlspecialchars($category); ?>
            </button>
        <?php endforeach; ?>
    </div>
    
    <!- Products Grid ->
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
        <div class="product-card<?php echo ($stock <= 0) ? ' out-of-stock out_of_stock_card_opacity out_of_stock_card_filter' : ''; ?>" data-category="<?php echo htmlspecialchars($category); ?>" data-stock="<?php echo $stock; ?>" data-sku="<?php echo $sku; ?>">
            <?php if ($stock <= 0): ?>
                <div class="out-of-stock-badge out_of_stock_badge position_absolute top_10 right_10 color_white font_size_12 font_weight_bold padding_6_10 border_radius_14 border_2_solid_white box_shadow_sm z_index_10 text_transform_uppercase letter_spacing_0_5">Out of Stock</div>
            <?php endif; ?>
            <!- Sale badge will be added dynamically by JavaScript ->
            <div class="bg-white rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-shadow duration-300 flex flex-col h-full cursor-pointer" 
                 onclick="showProductDetails('<?php echo $sku; ?>')"
                 data-product-data="<?php echo $safeJsonData; ?>">
                <?php
                        // Display product images using database-driven system
                        if ($primaryImageData && !empty($primaryImageData['image_path'])) {
                            echo '<div class="product-image-container product_image_container display_flex align_center justify_center bg_f8f9fa border_radius_normal overflow_hidden">';
                            echo '<img src="' . htmlspecialchars($primaryImageData['image_path'] ?? '') . '" alt="' . htmlspecialchars($primaryImageData['alt_text'] ?: $productName) . '" class="product-image max_width_100 max_height_100 object_fit_contain" onerror="this.classList.add(\'display_none\'); this.parentElement.innerHTML = \'<div class=\\\'width_100 height_100 display_flex flex_col align_center justify_center bg_f8f9fa color_6b7280\\\'><div class=\\\'font_size_3rem margin_bottom_10 opacity_07\\\'>📷</div><div class=\\\'font_size_0_9 font_weight_500\\\'>Image Not Found</div></div>\';">';
                            echo '</div>';
                        } else {
                            // Show CSS-only fallback if no images
                            echo '<div class="product-image-placeholder product_image_placeholder display_flex flex_col align_center justify_center bg_f8f9fa border_radius_normal color_6b7280">';
                            echo '<div class="product_image_placeholder_icon font_size_3rem margin_bottom_10 opacity_07">📷</div>';
                            echo '<div class="product_image_placeholder_text font_size_0_9 font_weight_500">No Image Available</div>';
                            echo '</div>';
                        }
                ?>
                <div class="flex flex-col">
                    <h3 class="font-merienda text-lg text-[#87ac3a] line-clamp-2"><?php echo $productName; ?></h3>
                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($category); ?></div>
                    <p class="text-gray-600 text-sm line-clamp-2 flex-grow-0">
                        <?php echo $description; ?>
                    </p>
                    
                    <?php if (!empty($sellingPoints) && count($sellingPoints) > 0): ?>
                    <div class="">
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (array_slice($sellingPoints, 0, 2) as $point): ?>
                                <span class="inline-block bg-green-100 text-green-800 text-xs rounded-full">
                                    ✓ <?php echo htmlspecialchars($point); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="text-sm <?php echo $stock > 0 ? 'text-gray-600' : 'text-red-600'; ?>">In stock: <?php echo $stock; ?></div>
                    <div class="flex justify-between items-center mt-auto">
                        <span class="product-price font-bold text-[#87ac3a]" data-sku="<?php echo $sku; ?>" data-original-price="<?php echo $price; ?>"><?php echo $formattedPrice; ?></span>
                        <button class="add-to-cart-btn <?php echo $stock > 0 ? 'add_to_cart_btn_bg add_to_cart_btn_hover_bg' : 'add_to_cart_btn_disabled_bg cursor-not-allowed'; ?> text-white rounded-lg text-sm font-semibold transition-colors shadow-md hover:shadow-lg border-none"
                                <?php if ($stock == 0) {
                                    echo 'disabled';
                                } ?>
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
                                    echo htmlspecialchars($callToActions[0]);
                                } else {
                                    echo htmlspecialchars(getRandomCartButtonText());
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

<!- Container for global item modal ->
<div id="globalModalContainer"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Category filtering
    const categoryButtons = document.querySelectorAll('.category-btn');
    const productCards = document.querySelectorAll('.product-card');
    
    categoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            
            // Update active button using CSS classes
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            // Filter products using CSS classes
            productCards.forEach(card => {
                if (category === 'all' || card.getAttribute('data-category') === category) {
                    card.classList.remove('display-none');
                } else {
                    card.classList.add('display-none');
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
    
    // Wait for sales checker functions to be available
    function waitForSalesChecker() {
        return new Promise((resolve) => {
            if (typeof window.checkAndDisplaySalePrice === 'function' && typeof window.addSaleBadgeToCard === 'function') {
                resolve();
            } else {
                setTimeout(() => {
                    waitForSalesChecker().then(resolve);
                }, 100);
            }
        });
    }
    
    waitForSalesChecker().then(() => {
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
});
</script>