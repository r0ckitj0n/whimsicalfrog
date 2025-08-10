// Initialize cart system immediately
function initializeCart() {
    console.log('[Cart] Initializing cart system...');
    
    // Load cart from localStorage
    cartMethods.loadCart();
    
    // Register global functions
    registerGlobalFunctions();
    
    // Update cart display
    cartMethods.updateCartDisplay();
    
    cartState.initialized = true;
    console.log('[Cart] Cart system initialized');
}
