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
<section id="cartPage" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5);">
    <!-- Cart modal container -->
    <div class="bg-white rounded-lg shadow-xl overflow-hidden w-full max-w-3xl max-h-[90vh] flex flex-col">
        <!-- Header with title and back button -->
        <div class="flex-shrink-0 bg-white border-b border-gray-200 px-6 py-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-merienda" style="color:#87ac3a !important;">Shopping Cart</h1>
                <a href="/?page=room_main" class="back-button text-white text-sm" style="background:rgba(107,142,35,0.9);padding:6px 12px;border-radius:20px;text-decoration:none;font-weight:bold;transition:all 0.3s ease;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                    ← Back to Main Room
                </a>
            </div>
        </div>
        
        <!-- Cart content container -->
        <div id="cartItems" class="flex-1 overflow-hidden" style="display: flex; flex-direction: column;">
            <!-- Cart content will be rendered here by cart.js -->
            <div class="p-4 text-center text-gray-500">Loading cart...</div>
        </div>
    </div>
</section>

<style>
/* Cart modal styling */
#cartPage {
    backdrop-filter: blur(4px);
}

#cartPage > div {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

@media (max-width: 768px) {
    #cartPage {
        padding: 1rem;
    }
    
    #cartPage > div {
        max-width: calc(100vw - 2rem);
        max-height: calc(100vh - 2rem);
    }
}

@media (min-width: 769px) {
    #cartPage > div {
        min-width: 600px;
        max-width: 800px;
    }
}

/* Custom scrollbar for cart items */
#cartItems {
    scrollbar-width: thin;
    scrollbar-color: #87ac3a #f1f5f9;
}

#cartItems::-webkit-scrollbar {
    width: 8px;
}

#cartItems::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

#cartItems::-webkit-scrollbar-thumb {
    background: #87ac3a;
    border-radius: 4px;
}

#cartItems::-webkit-scrollbar-thumb:hover {
    background: #6b8e23;
}

/* Ensure modal content fits properly */
#cartPage > div {
    min-height: 400px;
    max-height: 90vh;
}
</style>

<script>
    // Wait for both DOM and cart script to be loaded
    async function initializeCart() {
        if (typeof window.cart !== 'undefined') {await window.cart.renderCart();
            
            // Listen for cart updates
            window.addEventListener('cartUpdated', async function() {await window.cart.renderCart();
            });
        } else {setTimeout(initializeCart, 100);
        }
    }

    // Start initialization when DOM is loaded
    document.addEventListener('DOMContentLoaded', initializeCart);
</script>
