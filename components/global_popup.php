<?php
// Global Popup Component
// This component provides a unified popup system for all pages

function renderGlobalPopup()
{
    ob_start();
    ?>
    
    <!-- Global Item Popup -->
    <div id="itemPopup" class="item-popup" aria-hidden="true">
        <div class="popup-content">
            <!-- Sale Badge -->
            <div id="popupSaleBadge" class="popup-sale-badge hidden">
                <span class="sale-badge">
                    <span id="popupSaleText">SALE</span>
                </span>
            </div>

            <!-- Limited Stock Badge -->
            <div id="popupStockBadge" class="popup-stock-badge hidden">
                <span class="stock-badge" id="popupStockText">LIMITED STOCK</span>
            </div>
            
            <!-- Product Image -->
            <div class="popup-image-container">
                <img id="popupImage" class="popup-image" alt="Product Image" loading="lazy">
                <!-- Badge overlay container (badges positioned absolutely over the image) -->
                <div id="popupBadgeContainer" class="popup-badge-container" aria-hidden="true"></div>
            </div>
            
            <!-- Product Info -->
            <div class="popup-info">
                <h3 id="popupTitle" class="popup-title">Product Name</h3>
                <div id="popupStock" class="popup-stock-info">In Stock</div>
                <p id="popupDescription" class="popup-description">Product description</p>
                
                <!-- Price Section -->
                <div id="popupPriceSection" class="popup-price-section">
                    <span id="popupCurrentPrice" class="popup-price">$0.00</span>
                    <span id="popupOriginalPrice" class="popup-original-price hidden">$0.00</span>
                    <span id="popupSavings" class="popup-savings hidden">Save $0.00</span>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="popup-actions">
                <button id="popupAddBtn" class="popup-add-btn">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13h10m-10 0v6a1 1 0 001 1h8a1 1 0 001-1v-6m-9 0h9"></path>
                    </svg>
                    Add to Cart
                </button>
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}

// Function to include global popup CSS
function renderGlobalPopupCSS()
{
    ob_start();
    ?>
    
    
    
    <?php
    return ob_get_clean();
}
?> 