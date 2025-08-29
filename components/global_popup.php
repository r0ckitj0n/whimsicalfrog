<?php
<<<<<<< HEAD
// Global Popup Component - Database-driven CSS version
=======
// Global Popup Component
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
// This component provides a unified popup system for all pages

function renderGlobalPopup() {
    ob_start();
    ?>
    
<<<<<<< HEAD
    <!-- Global Product Popup - Side-by-side layout -->
    <div id="productPopup" class="item-popup item-popup-enhanced">
        <div class="popup-content popup-content-enhanced">
            <!-- Sale Badge -->
=======
    <!- Global Item Popup ->
    <div id="itemPopup" class="item-popup">
        <div class="popup-content">
            <!- Sale Badge ->
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            <div id="popupSaleBadge" class="popup-sale-badge hidden">
                <span class="sale-badge">
                    <span id="popupSaleText">SALE</span>
                </span>
            </div>
            
<<<<<<< HEAD
            <!-- Limited Stock Badge -->
            <div id="popupStockBadge" class="out-of-stock-badge hidden">
                <span class="stock-badge">LIMITED STOCK</span>
            </div>
            
            <!-- Left Side: Sales Lingo + Image -->
            <div class="popup-left-side">
                <!-- Top Sales Lingo -->
                <div id="popupTopLingo" class="popup-top-lingo hidden">
                    <!-- Top sales message -->
                </div>
                
                <!-- Product Image -->
                <img id="popupImage" class="popup-image popup-image-enhanced" src="" alt="Product Image">
                
                <!-- Bottom Sales Lingo -->
                <div id="popupBottomLingo" class="popup-bottom-lingo hidden">
                    <!-- Bottom sales messages -->
                </div>
            </div>
            
            <!-- Right Side: Product Info -->
            <div class="popup-details popup-details-enhanced">
                <div id="popupCategory" class="popup-category popup-category-enhanced">Category</div>
                <h3 id="popupTitle" class="popup-title popup-title-enhanced">Product Name</h3>
                <div id="popupSku" class="popup-sku">SKU: </div>
                <div id="popupStock" class="popup-stock">In Stock</div>
                
                <!-- Price Section -->
                <div id="popupPriceSection" class="popup-price-section">
                    <span id="popupCurrentPrice" class="popup-price popup-price-enhanced">$0.00</span>
                    <span id="popupOriginalPrice" class="popup-original-price hidden">$0.00</span>
                    <span id="popupSavings" class="popup-savings hidden">Save $0.00</span>
                </div>
                
                <p id="popupDescription" class="popup-description popup-description-enhanced">Product description</p>
                
                <!-- Action Buttons -->
                <div class="popup-actions popup-actions-enhanced">
                    <button id="popupAddBtn" class="popup-add-btn popup-add-btn-enhanced">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13h10m-10 0v6a1 1 0 001 1h8a1 1 0 001-1v-6m-9 0h9"></path>
                        </svg>
                        Add to Cart
                    </button>
                    <button id="popupDetailsBtn" class="popup-details-btn popup-details-btn-enhanced hidden" onclick="showItemDetailsModal(window.currentPopupSku)">
                        View Details
                    </button>
                </div>
=======
            <!- Limited Stock Badge ->
            <div id="popupStockBadge" class="popup-stock-badge hidden">
                <span class="stock-badge">LIMITED STOCK</span>
            </div>
            
            <!- Product Image ->
            <img id="popupImage" class="popup-image" src="" alt="Product Image">
            
            <!- Product Info ->
            <div class="popup-info">
                <div id="popupCategory" class="popup-category">Category</div>
                <h3 id="popupTitle" class="popup-title">Product Name</h3>
                <div id="popupSku" class="popup-sku">SKU: </div>
                <div id="popupStock" class="popup-stock-info">In Stock</div>
                <p id="popupDescription" class="popup-description">Product description</p>
                
                <!- Price Section ->
                <div id="popupPriceSection" class="popup-price-section">
                    <span id="popupCurrentPrice" class="popup-price">$0.00</span>
                    <span id="popupOriginalPrice" class="popup-original-price hidden">$0.00</span>
                    <span id="popupSavings" class="popup-savings hidden">Save $0.00</span>
                </div>
            </div>
            
            <!- Action Buttons ->
            <div class="popup-actions">
                <button id="popupAddBtn" class="popup-add-btn">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13h10m-10 0v6a1 1 0 001 1h8a1 1 0 001-1v-6m-9 0h9"></path>
                    </svg>
                    Add to Cart
                </button>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}

<<<<<<< HEAD
// Function to load database-driven CSS for popups
=======
// Function to include global popup CSS
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
function renderGlobalPopupCSS() {
    ob_start();
    ?>
    
<<<<<<< HEAD
    <script>
    // Load popup CSS from database - NO FALLBACK
    fetch('/api/css_generator.php?category=popup')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(css => {
            if (!css || css.trim() === '') {
                throw new Error('Empty CSS response from database');
            }
            const style = document.createElement('style');
            style.textContent = css;
            document.head.appendChild(style);
            console.log('✅ Popup CSS loaded from database:', css.length + ' characters');
        })
        .catch(error => {
            console.error('❌ FATAL: Failed to load popup CSS from database:', error);
            // Show error to user - no fallback styling
            const errorDiv = document.createElement('div');
            errorDiv.innerHTML = `
                <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                    <strong>CSS Loading Error</strong><br>
                    Database connection failed. Please refresh the page or contact support.
                </div>
            `;
            document.body.appendChild(errorDiv);
        });
    </script>
=======
    
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    
    <?php
    return ob_get_clean();
}
<<<<<<< HEAD

// Display the popup and load CSS
echo renderGlobalPopup();
echo renderGlobalPopupCSS(); 
=======
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
?> 