<?php
// Global Popup Component
// This component provides a unified popup system for all pages

function renderGlobalPopup() {
    ob_start();
    ?>
    
    <!-- Global Product Popup -->
    <div id="productPopup" class="product-popup">
        <div class="popup-content">
            <!-- Sale Badge -->
            <div id="popupSaleBadge" class="popup-sale-badge hidden">
                <span class="sale-badge">
                    <span id="popupSaleText">SALE</span>
                </span>
            </div>
            
            <!-- Limited Stock Badge -->
            <div id="popupStockBadge" class="popup-stock-badge hidden">
                <span class="stock-badge">LIMITED STOCK</span>
            </div>
            
            <!-- Product Image -->
            <img id="popupImage" class="popup-image" src="" alt="Product Image">
            
            <!-- Product Info -->
            <div class="popup-info">
                <div id="popupCategory" class="popup-category">Category</div>
                <h3 id="popupTitle" class="popup-title">Product Name</h3>
                <div id="popupSku" class="popup-sku">SKU: </div>
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
function renderGlobalPopupCSS() {
    ob_start();
    ?>
    
    <style>
    /* Global Popup Styles */
    .product-popup {
        position: absolute;
        background: white;
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border: 1px solid #e5e7eb;
        min-width: 280px;
        max-width: 320px;
        width: auto;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        transform: translateY(8px);
    }
    
    .product-popup.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    
    .popup-content {
        position: relative;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .popup-content:hover {
        transform: scale(1.01);
        background: rgba(135, 172, 58, 0.03);
        border-radius: 10px;
        padding: 3px;
        margin: -3px;
    }
    
    /* Badges */
    .popup-sale-badge, .popup-stock-badge {
        position: absolute;
        top: 8px;
        z-index: 10;
    }
    
    .popup-sale-badge {
        left: 8px;
    }
    
    .popup-stock-badge {
        right: 8px;
    }
    
    .sale-badge {
        background: #ef4444;
        color: white;
        padding: 4px 8px;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: bold;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    .stock-badge {
        background: #f97316;
        color: white;
        padding: 4px 8px;
        border-radius: 9999px;
        font-size: 10px;
        font-weight: bold;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
    
    /* Image */
    .popup-image {
        width: 100%;
        max-width: 100%;
        height: auto;
        max-height: 200px;
        object-fit: contain;
        border-radius: 8px;
        margin-bottom: 12px;
        background: #f8f9fa;
    }
    
    /* Text Elements */
    .popup-category {
        font-size: 11px;
        color: #6b7280;
        margin-bottom: 2px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .popup-title {
        font-size: 16px;
        font-weight: bold;
        color: #111827;
        margin-bottom: 4px;
        line-height: 1.2;
    }
    
    .popup-sku {
        font-size: 10px;
        color: #9ca3af;
        margin-bottom: 4px;
    }
    
    .popup-stock-info {
        font-size: 11px;
        margin-bottom: 8px;
        padding: 2px 6px;
        border-radius: 4px;
        display: inline-block;
    }
    
    .popup-stock-info.in-stock {
        background: #dcfce7;
        color: #166534;
    }
    
    .popup-stock-info.out-of-stock {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .popup-stock-info.limited-stock {
        background: #fed7aa;
        color: #9a3412;
    }
    
    .popup-description {
        font-size: 12px;
        color: #4b5563;
        margin-bottom: 12px;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    /* Price Section */
    .popup-price-section {
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .popup-price {
        font-size: 18px;
        font-weight: bold;
        color: #059669;
    }
    
    .popup-original-price {
        font-size: 14px;
        color: #9ca3af;
        text-decoration: line-through;
    }
    
    .popup-savings {
        font-size: 10px;
        background: #fee2e2;
        color: #991b1b;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 600;
    }
    
    /* Action Buttons */
    .popup-actions {
        margin-top: 12px;
    }
    
    .popup-add-btn {
        width: 100%;
        padding: 10px 16px;
        background: #87ac3a;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    
    .popup-add-btn:hover {
        background: #6b8e23;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(135, 172, 58, 0.3);
    }
    
    .popup-add-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
        background: #9ca3af;
    }
    
    .popup-add-btn:disabled:hover {
        transform: none;
        box-shadow: none;
        background: #9ca3af;
    }
    
    /* Responsive */
    @media (max-width: 640px) {
        .product-popup {
            min-width: 240px;
            max-width: 280px;
            padding: 12px;
        }
        
        .popup-image {
            max-height: 150px;
        }
        
        .popup-title {
            font-size: 14px;
        }
        
        .popup-price {
            font-size: 16px;
        }
    }
    </style>
    
    <?php
    return ob_get_clean();
}
?> 