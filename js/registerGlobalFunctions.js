// Register global functions immediately
function registerGlobalFunctions() {
    // Main cart object
    window.cart = {
        addItem: (item) => cartMethods.addItem(item),
        removeItem: (sku) => cartMethods.removeItem(sku),
        updateItem: (sku, quantity) => cartMethods.updateItem(sku, quantity),
        clearCart: () => cartMethods.clearCart(),
        getItems: () => cartMethods.getItems(),
        getTotal: () => cartMethods.getTotal(),
        getCount: () => cartMethods.getCount(),
        getState: () => cartMethods.getState(),
        setNotifications: (enabled) => cartMethods.setNotifications(enabled),
        showCurrentCartStatus: () => cartMethods.showCurrentCartStatus(),
        showCartStatusToast: () => cartMethods.showCartStatusToast(),
        renderCart: () => cartMethods.renderCart(),
        checkout: () => cartMethods.checkout(),
        createPaymentMethodModal: () => cartMethods.createPaymentMethodModal(),
        proceedToCheckout: () => cartMethods.proceedToCheckout(),
        submitCheckout: (paymentMethod, shippingMethod) => cartMethods.submitCheckout(paymentMethod, shippingMethod),
        loadProfileAddress: () => cartMethods.loadProfileAddress(),
        toggleAddressFields: () => cartMethods.toggleAddressFields(),
        
        // Legacy methods
        items: cartState.items,
        total: cartState.total,
        count: cartState.count
    };

    // Global cart functions
    window.addToCart = (item) => cartMethods.addItem(item);
    window.removeFromCart = (sku) => cartMethods.removeItem(sku);
    window.updateCartItem = (sku, quantity) => cartMethods.updateItem(sku, quantity);
    window.clearCart = () => cartMethods.clearCart();
    window.getCartItems = () => cartMethods.getItems();
    window.getCartTotal = () => cartMethods.getTotal();
    window.getCartCount = () => cartMethods.getCount();

    // Global cart status functions (matching June 30th config)
    window.showCartStatus = () => cartMethods.showCurrentCartStatus();
    window.showCartStatusToast = () => cartMethods.showCartStatusToast();
    
    // Enhanced cart access for iframe contexts
    window.accessCart = function() {
        // Try multiple access patterns
        if (window.cart && typeof window.cart.addItem === 'function') {
            return window.cart;
        }
        
        try {
            if (window.parent && window.parent.cart && typeof window.parent.cart.addItem === 'function') {
                return window.parent.cart;
            }
        } catch (e) {
            // Cross-origin access denied
        }
        
        try {
            if (window.top && window.top.cart && typeof window.top.cart.addItem === 'function') {
                return window.top.cart;
            }
        } catch (e) {
            // Cross-origin access denied
        }
        
        return null;
    };
    
    // Expose notification system for iframe access
    window.accessNotifications = function() {
        // Try to get branded notification functions from current or parent window
        const notifications = {};
        
        try {
            // First try to access the branded notification system
            if (window.wfNotifications && typeof window.wfNotifications.success === 'function') {
                notifications.showSuccess = (message, options = {}) => window.wfNotifications.success(message, options);
                notifications.showError = (message, options = {}) => window.wfNotifications.error(message, options);
                notifications.showInfo = (message, options = {}) => window.wfNotifications.info(message, options);
                notifications.showWarning = (message, options = {}) => window.wfNotifications.warning(message, options);
            } else if (window.parent && window.parent.wfNotifications && typeof window.parent.wfNotifications.success === 'function') {
                notifications.showSuccess = (message, options = {}) => window.parent.wfNotifications.success(message, options);
                notifications.showError = (message, options = {}) => window.parent.wfNotifications.error(message, options);
                notifications.showInfo = (message, options = {}) => window.parent.wfNotifications.info(message, options);
                notifications.showWarning = (message, options = {}) => window.parent.wfNotifications.warning(message, options);
            }
            
            // Fallback to simple notification functions if branded system not available
            if (!notifications.showSuccess) {
                if (typeof window.showSuccess === 'function') {
                    notifications.showSuccess = window.showSuccess;
                    notifications.showError = window.showError || window.showNotification;
                    notifications.showInfo = window.showInfo || window.showNotification;
                    notifications.showWarning = window.showWarning || window.showNotification;
                } else if (window.parent && typeof window.parent.showSuccess === 'function') {
                    notifications.showSuccess = window.parent.showSuccess;
                    notifications.showError = window.parent.showError || window.parent.showNotification;
                    notifications.showInfo = window.parent.showInfo || window.parent.showNotification;
                    notifications.showWarning = window.parent.showWarning || window.parent.showNotification;
                }
            }
        } catch (e) {
            console.log('[Cart] Notification access failed:', e.message);
        }
        
        return notifications;
    };
    
    console.log('[Cart] Global functions registered');
    console.log('[Cart] Cart object available:', typeof window.cart);
    console.log('[Cart] Cart addItem method:', typeof window.cart.addItem);
}
