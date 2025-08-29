<<<<<<< HEAD

<?php
// Cart page content - Check authentication first
if (!$isLoggedIn) {
    // Store the cart redirect intent
    $_SESSION['redirect_after_login'] = '/?page=cart';
    
    // Redirect to login page
    header('Location: /?page=login');
    exit;
}
?>

<!-- Database-driven CSS for cart -->
<style id="cart-css">
/* CSS will be loaded from database */
</style>
<script>
    // Load CSS from database
    async function loadCartCSS() {
        try {
            const response = await fetch('/api/css_generator.php?category=cart');
            const cssText = await response.text();
            const styleElement = document.getElementById('cart-css');
            if (styleElement && cssText) {
                styleElement.textContent = cssText;
                console.log('✅ cart CSS loaded from database');
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load cart CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>cart CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadCartCSS);
</script>

<section id="cartPage" class="fixed inset-0 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5); z-index: 9999 !important;" onclick="window.location.href='/?page=room_main';">
    <!-- Cart modal container -->
    <div class="bg-white rounded-lg shadow-xl overflow-hidden w-full max-w-3xl max-h-[90vh] flex flex-col" onclick="event.stopPropagation();">
        <!-- Header with title and back button -->
        <div class="flex-shrink-0 bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-merienda" style="color: var(--primary-color);">Shopping Cart</h1>
                <a href="/?page=room_main" class="back-button text-white text-sm" style="background:rgba(107,142,35,0.9);padding:6px 12px;border-radius:20px;text-decoration:none;font-weight:bold;transition:all 0.3s ease;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    ← Back to Main Room
                </a>
            </div>
        </div>
        
        <!-- Cart content container -->
        <div id="cartItems" class="flex-1 overflow-hidden" style="display: flex; flex-direction: column;">
            <!-- Cart content will be rendered here by cart.js -->
            <div class="p-4 text-center text-gray-500">Loading cart...</div>
=======
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
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
        </div>
    </div>
</section>

<<<<<<< HEAD
<script>
    // Wait for both DOM and cart script to be loaded
    async function initializeCart() {
        if (typeof window.cart !== 'undefined') {
=======


<script>
    // Wait for both DOM and cart script to be loaded
    async function initializeCart() {
        if (typeof window.cart !== 'undefined' && typeof window.cart.renderCart === 'function') {
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
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
<<<<<<< HEAD
=======

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
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
</script>
