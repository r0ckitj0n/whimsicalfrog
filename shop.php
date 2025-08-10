<?php
// Shop page section
if (!defined('INCLUDED_FROM_INDEX')) {
    // Redirect to home if accessed directly
    header('Location: /');
    exit;
}
?>





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

// Categories are already loaded in index.php via shop_data_loader.php and
// are ordered by display_order. No additional ordering required.
$orderedCategories = [];
if (!empty($categories)) {
    /* Legacy room-ordering query removed */
/*
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

*/
}
?>

<section id="shopPage" class="page-content">

    <!-- Fixed Navigation Area - Stays at top, doesn't scroll -->
    <div class="shop-navigation-area">
        <!-- Navigation Bar: 3-Column CSS Grid Layout -->
    <div class="navigation-bar">
        <?php
        // Only show room main navigation image on non-room_main pages
        // The global $page variable is used here, defined in index.php
        if ($page !== 'room_main'):
        ?>
        <!-- Left Column: Room Main Navigation Image (left-aligned within column) -->
        <div class="room-main-nav-container">
            <a href="/room_main" class="room-main-nav-link" title="Go to Main Room">
                <picture>
                    <source srcset="images/signs/sign_main.webp" type="image/webp">
                    <img src="images/signs/sign_main.png" alt="Rooms" class="room-main-nav-image">
                </picture>
            </a>
        </div>
        <?php endif; ?>

        <!-- Middle Column: Category Filter Buttons (center-aligned within column) -->
        <div class="category-navigation">
            <!-- All Products button first -->
            <button class="category-btn category_btn_bg category_btn_color rounded-full border-none transition-colors active"
                    data-category="all">
                All Products
            </button>
            <!-- Then individual categories in order -->
            <?php foreach ($categories as $slug => $catData): ?>
                <button class="category-btn category_btn_bg category_btn_color category_btn_hover_bg rounded-full border-none transition-colors"
                        data-category="<?php echo htmlspecialchars($slug); ?>">
                    <?php echo htmlspecialchars($catData['label']); ?>
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
            echo '<div class="text-center p-8"><h2 class="text-brand-primary">No categories found. Database connection issue?</h2></div>';
            return;
        }
        


        // Display all products with proper text wrapping and images
        foreach ($categories as $slug => $catData):
            $categoryLabel = $catData['label'];
            $products = $catData['products'];
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
        <div class="product-card" data-category="<?php echo htmlspecialchars($slug); ?>">
            <!-- Product Image -->
            <div class="product-image-container">
                <div class="product-image-container" id="image-container-<?php echo $sku; ?>">
                    <?php if ($primaryImage && file_exists($primaryImage)): ?>
                        <img src="<?php echo htmlspecialchars($primaryImage); ?>"
                             alt="<?php echo htmlspecialchars($productName); ?>"
                             class="product-image">
                    <?php else: ?>
                        <span class="no-image-placeholder">📷 No Image</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Content - Flexible Area -->
            <div class="product-content">
                <!-- Product Title -->
                <h3 class="product-title"><?php echo $productName; ?></h3>

                <!-- Product Description - Expandable Middle Section -->
                <div class="product-description">
                    <p class="description-text"
                       title="<?php echo htmlspecialchars($description); ?>">
                        <?php echo htmlspecialchars(substr($description, 0, 100)); ?>
                        <?php if (strlen($description) > 100): ?>
                            <span class="read-more">... (more)</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Product Info - Fixed Bottom Section -->
            <div class="product-info">
                <!-- Price - Prominent Display -->
                <div class="product-price">
                    <?php echo $formattedPrice; ?>
                </div>

                <!-- Stock Level - Color Coded -->
                <div class="product-stock" data-stock="<?php echo $stock; ?>">
                    Stock: <?php echo $stock; ?>
                </div>

                <!-- Product Meta - Category and SKU -->
                <div class="product-meta">
                    <!-- Category -->
                    <div class="product-category">
                        <?php echo htmlspecialchars($categoryLabel); ?>
                    </div>

                    <!-- SKU -->
                    <div class="product-sku">
                        SKU: <?php echo $sku; ?>
                    </div>
                </div>
                </div>
            </div>

            <!-- Add to Cart Button - Always at Bottom -->
            <div class="product-button">
                <button class="add-to-cart-btn"
                        data-sku="<?php echo $sku; ?>"
                        data-name="<?php echo htmlspecialchars($productName); ?>"
                        data-price="<?php echo $price; ?>"
                        data-custom-text="<?php echo $customButtonText; ?>">
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