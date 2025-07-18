<?php
// Cart page content - authentication is handled in index.php
?>
<section id="cartPage" class="fixed inset-0 z-50 flex items-center justify-center modal_overlay_dark">
    <!- Cart modal container ->
    <div class="bg-white rounded-lg shadow-xl overflow-hidden w-full max-w-3xl max-h-[90vh] flex flex-col">
        <!- Header with back button and title ->
        <div class="flex-shrink-0 bg-white border-b border-gray-200 cart-header">
            <div class="room-header-overlay">
                <div class="back-button-container">
                    <a href="/?page=room_main" class="back-to-main-button">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        <span>Back to Main Room</span>
                    </a>
                </div>
                <div class="room-title-overlay">
                    <h1 class="room-title">Shopping Cart</h1>
                </div>
            </div>
        </div>
        
        <!- Cart content container ->
        <div id="cartItems" class="flex-1 overflow-y-auto cart_column_layout cart_column_direction cart-scrollbar">
            <!- Cart content will be rendered here by cart.js ->
            <div class="text-center text-gray-500">Loading cart...</div>
        </div>
    </div>
</section>



<script>
    // Wait for both DOM and cart script to be loaded
    async function initializeCart() {
        if (typeof window.cart !== 'undefined' && typeof window.cart.renderCart === 'function') {
            await window.cart.renderCart();
            
            // Listen for cart updates
            window.addEventListener('cartUpdated', async function() {
                await window.cart.renderCart();
            });
        } else {
            setTimeout(initializeCart, 100);
        }
    }

    // Start initialization when DOM is loaded
    document.addEventListener('DOMContentLoaded', initializeCart);

    // Add click outside modal to close functionality
    document.addEventListener('DOMContentLoaded', function() {
        const cartOverlay = document.getElementById('cartPage');
        const cartModal = cartOverlay?.querySelector('div');
        
        if (cartOverlay) {
            cartOverlay.addEventListener('click', function(e) {
                // Only close if clicking on the overlay itself, not the modal content
                if (e.target === cartOverlay) {
                    // Navigate back to main room
                    window.location.href = '/?page=room_main';
                }
            });
            
            // Prevent modal content clicks from bubbling up to overlay
            if (cartModal) {
                cartModal.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }
        }
        
        // Add Escape key support to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.keyCode === 27) {
                // Check if cart modal is currently visible
                const cartPage = document.getElementById('cartPage');
                if (cartPage && !cartPage.classList.contains('hidden')) {
                    window.location.href = '/?page=room_main';
                }
            }
        });
    });
</script>
