<?php
/**
 * Reusable Quantity Modal Component
 * Used by all room pages for consistent add-to-cart functionality
 */
?>

<!- Universal Quantity Modal - Used by all rooms ->
<div id="quantityModal" class="quantity-modal modal-overlay hidden">
    <div class="room-modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add to Cart</h3>
            <button id="closeQuantityModal" class="modal-close-btn">×</button>
        </div>
        <div class="modal-body">
            <div class="product-summary">
                <img id="modalProductImage" class="modal-product-image" src="" alt="">
                <div class="product-info">
                    <h4 id="modalProductName" class="product-name">Item Name</h4>
                    <p id="modalProductPrice" class="product-price">$0.00</p>
                </div>
            </div>
            
            <!- Color and Size Options - dynamically populated by cart.js ->
            <div id="colorDropdownContainer" class="color-dropdown-container"></div>
            <div id="sizeDropdownContainer" class="size-dropdown-container"></div>
            
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
            <button id="cancelQuantityModal" class="btn btn-secondary">Cancel</button>
            <button id="confirmAddToCart" class="btn btn-primary">Add to Cart</button>
        </div>
    </div>
</div> 