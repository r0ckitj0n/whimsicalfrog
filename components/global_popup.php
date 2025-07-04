<?php
// Global Popup Component - Updated to use Monday backup CSS classes
// This component provides a unified popup system for all pages

function renderGlobalPopup() {
    ob_start();
    ?>
    
    <!-- Global Product Popup - Using Monday backup CSS classes -->
    <div id="productPopup" class="item-popup item-popup-enhanced">
        <div class="popup-content popup-content-enhanced">
            <!-- Sale Badge -->
            <div id="popupSaleBadge" class="popup-sale-badge hidden">
                <span class="sale-badge">
                    <span id="popupSaleText">SALE</span>
                </span>
            </div>
            
            <!-- Limited Stock Badge -->
            <div id="popupStockBadge" class="out-of-stock-badge hidden">
                <span class="stock-badge">LIMITED STOCK</span>
            </div>
            
            <!-- Product Image -->
            <img id="popupImage" class="popup-image popup-image-enhanced" src="" alt="Product Image">
            
            <!-- Product Info -->
            <div class="popup-details popup-details-enhanced">
                <div id="popupCategory" class="popup-category popup-category-enhanced">Category</div>
                <h3 id="popupTitle" class="popup-title popup-title-enhanced">Product Name</h3>
                <div id="popupSku" class="popup-sku">SKU: </div>
                <div id="popupStock" class="popup-stock">In Stock</div>
                <p id="popupDescription" class="popup-description popup-description-enhanced">Product description</p>
                
                <!-- Price Section -->
                <div id="popupPriceSection" class="popup-price-section">
                    <span id="popupCurrentPrice" class="popup-price popup-price-enhanced">$0.00</span>
                    <span id="popupOriginalPrice" class="popup-original-price hidden">$0.00</span>
                    <span id="popupSavings" class="popup-savings hidden">Save $0.00</span>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="popup-actions popup-actions-enhanced">
                <button id="popupAddBtn" class="popup-add-btn popup-add-btn-enhanced">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13h10m-10 0v6a1 1 0 001 1h8a1 1 0 001-1v-6m-9 0h9"></path>
                    </svg>
                    Add to Cart
                </button>
                <button id="popupDetailsBtn" class="popup-details-btn popup-details-btn-enhanced" onclick="showItemDetailsModal(window.currentPopupSku)">
                    View Details
                </button>
            </div>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}

// Function to include global popup CSS - now uses database CSS instead of inline
function renderGlobalPopupCSS() {
    ob_start();
    ?>
    
    <style>
    /* Monday Backup CSS Integration - Override any conflicts */
    
    /* Ensure the popup uses the Monday backup styles from database */
    .item-popup {
        /* Let the database CSS handle the styling */
    }
    
    .item-popup-enhanced {
        /* Enhanced styling from Monday backup */
    }
    
    /* Utility styles for popup positioning */
    #productPopup {
        position: absolute;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        transform: translateY(10px);
    }
    
    #productPopup.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    /* Ensure proper text wrapping */
    .popup-description,
    .popup-description-enhanced {
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    
    /* Badge positioning consistency */
    .popup-sale-badge {
        position: absolute;
        top: 5px;
        left: 5px;
        z-index: 10;
    }
    
    .out-of-stock-badge.hidden {
        display: none;
    }
    
    .popup-sale-badge.hidden {
        display: none;
    }
    
    /* Ensure proper display for all elements */
    .hidden {
        display: none !important;
    }
    
    /* Compatibility fixes for Monday backup integration */
    .item-popup .popup-actions-enhanced {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }
    
    .item-popup .popup-actions-enhanced button {
        flex: 1;
    }
    
    /* Store current SKU for detail modal */
    #productPopup[data-sku] {
        /* SKU stored as data attribute for modal access */
    }
    </style>
    
    <?php
    return ob_get_clean();
}
?> 