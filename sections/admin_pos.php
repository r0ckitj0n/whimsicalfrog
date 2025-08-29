<?php
// Admin POS - Point of Sale System
require_once __DIR__ . '/../includes/functions.php';

// Get database instance
$db = Database::getInstance();

// Initialize database
try {
    
    
    // Get all items for searching
    $stmt = $db->query("SELECT i.sku, i.name, i.retailPrice, COALESCE(img.image_path, i.imageUrl) as imageUrl FROM items i LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1 ORDER BY i.name");
    $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    Logger::error('Admin POS data loading failed', ['error' => $e->getMessage()]);
    $allItems = [];
}
?>



<div class="pos-register">
<<<<<<< HEAD
    <!-- Header -->
=======
    <!- Header ->
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    <div class="pos-header">
        <h1 class="pos-title">🛒 WhimsicalFrog Point of Sale</h1>
        <div class="pos-header-buttons">
            <button class="pos-fullscreen-btn" onclick="toggleFullscreen()">
                📺 Full Screen
            </button>
            <button class="pos-exit-btn" onclick="window.location.href='/?page=admin'">
                ✖ Exit POS
            </button>
        </div>
    </div>

<<<<<<< HEAD
    <!-- Main Content -->
    <div class="pos-main">
        <!-- Left Panel - Search & Items -->
        <div class="pos-left-panel">
            <!-- Search Section -->
=======
    <!- Main Content ->
    <div class="pos-main">
        <!- Left Panel - Search & Items ->
        <div class="pos-left-panel">
            <!- Search Section ->
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            <div class="pos-search-section">
                <h2 class="pos-search-title">📦 Add Items</h2>
                <div class="pos-search-methods">
                    <input type="text" 
                           id="skuSearch" 
                           class="pos-search-input" 
                           placeholder="🔍 Scan or enter SKU..."
                           autofocus>
                    <button class="pos-browse-btn" onclick="showAllItems()">
                        🛍️ Browse Items
                    </button>
                </div>
            </div>

<<<<<<< HEAD
            <!-- Items Grid -->
            <div class="pos-items-grid">
                <div class="items-grid" id="itemsGrid">
                    <!-- Items will be populated here -->
=======
            <!- Items Grid ->
            <div class="pos-items-grid">
                <div class="items-grid" id="itemsGrid">
                    <!- Items will be populated here ->
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                </div>
            </div>
        </div>

<<<<<<< HEAD
        <!-- Right Panel - Cart -->
=======
        <!- Right Panel - Cart ->
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
        <div class="pos-cart">
            <div class="cart-header">
                <h2 class="cart-title">🛒 Cart</h2>
            </div>
            
            <div class="cart-summary">
                <div class="cart-total">
                    <span>Total:</span>
<<<<<<< HEAD
                    <span id="posCartTotal" class="pos-cart-total">$0.00</span>
=======
                    <span id="posCartTotal">$0.00</span>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                </div>
                <button class="checkout-btn" id="checkoutBtn" onclick="processCheckout()" disabled>
                    💳 Complete Sale
                </button>
            </div>
            
            <div class="cart-items" id="cartItems">
                <div class="empty-cart">
                    Cart is empty<br>
                    <small>Scan or search for items to add them</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
        // Load global CSS variables for POS
async function loadPOSCSSVariables() {
    try {
<<<<<<< HEAD
        const response = await fetch('/api/global_css_rules.php?action=generate_css');
=======
                        // Database CSS loading removed - using static CSS files only
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
        const data = await response.json();
        
        if (data.success && data.css_content) {
            // Create and inject CSS
            const styleElement = document.createElement('style');
            styleElement.textContent = data.css_content;
            document.head.appendChild(styleElement);}
    } catch (error) {
        console.error('Failed to load POS CSS variables:', error);
    }
}

// Load CSS variables immediately
loadPOSCSSVariables();

// POS System JavaScript
let cart = [];
let allItems = <?= json_encode($allItems) ?>;

// Fullscreen functionality
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        // Enter fullscreen
        document.documentElement.requestFullscreen().then(() => {updateFullscreenButton(true);
        }).catch(err => {
            console.error('Error entering fullscreen:', err);
            if (window.showWarning) {
            window.showWarning('Could not enter fullscreen mode');
        } else {
            alert('Could not enter fullscreen mode');
        }
        });
    } else {
        // Exit fullscreen
        document.exitFullscreen().then(() => {updateFullscreenButton(false);
        }).catch(err => {
            console.error('Error exiting fullscreen:', err);
        });
    }
}

// Update fullscreen button text based on state
function updateFullscreenButton(isFullscreen) {
    const btn = document.querySelector('.pos-fullscreen-btn');
    if (btn) {
        if (isFullscreen) {
            btn.innerHTML = '🪟 Exit Full Screen';
        } else {
            btn.innerHTML = '📺 Full Screen';
        }
    }
}

// Listen for fullscreen changes (user can exit with ESC key)
document.addEventListener('fullscreenchange', function() {
    updateFullscreenButton(!!document.fullscreenElement);
});

// Initialize the POS system
document.addEventListener('DOMContentLoaded', function() {showAllItems();
    
    // Setup SKU search
    const skuSearch = document.getElementById('skuSearch');
    skuSearch.addEventListener('input', function() {
        const sku = this.value.trim().toUpperCase();
        if (sku.length >= 3) {
            searchItemsBySku(sku);
        } else {
            showAllItems();
        }
    });
    
    // Setup Enter key for SKU search
    skuSearch.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const sku = this.value.trim().toUpperCase();
            const item = allItems.find(item => item.sku.toUpperCase() === sku);
            if (item) {
                addToCart(item);
                this.value = '';
                showAllItems();
            } else {
                if (window.showError) {
            window.showError('Item not found: ' + sku);
        } else {
            alert('Item not found: ' + sku);
        }
            }
        }
    });
    
    // Verify cards are clickable
    setTimeout(() => {
        const cardCount = document.querySelectorAll('.item-card').length;// Debug cart elements
        const cartItems = document.getElementById('cartItems');
        const cartTotal = document.getElementById('posCartTotal');
        const checkoutBtn = document.getElementById('checkoutBtn');if (cartItems) {}
        
        // Cart system ready}, 500);
});

// Show all items in the grid
function showAllItems() {
    const grid = document.getElementById('itemsGrid');
    
    if (!grid) {
        console.error('itemsGrid element not found');
        return;
    }
    
    grid.innerHTML = '';
    
    allItems.forEach((item) => {
        const itemCard = createItemCard(item);
        grid.appendChild(itemCard);
    });}

// Simple test cart display function
function simpleCartDisplay() {const cartItems = document.getElementById('cartItems');
    if (cartItems) {
<<<<<<< HEAD
                        cartItems.innerHTML = '<div class="debug-info">🧪 SIMPLE TEST: Cart has ' + cart.length + ' items</div>';} else {
=======
        cartItems.innerHTML = '<div >🧪 SIMPLE TEST: Cart has ' + cart.length + ' items</div>';} else {
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
        console.error('🧪 cartItems element not found');
    }
}

// Debug function removed

// Search items by SKU
function searchItemsBySku(searchTerm) {
    const filtered = allItems.filter(item => 
        item.sku.toUpperCase().includes(searchTerm) ||
        item.name.toUpperCase().includes(searchTerm)
    );
    
    const grid = document.getElementById('itemsGrid');
    grid.innerHTML = '';
    
    filtered.forEach(item => {
        const itemCard = createItemCard(item);
        grid.appendChild(itemCard);
    });
}

// Create item card element
function createItemCard(item) {
    const card = document.createElement('div');
    card.className = 'item-card';
    
    // Single event handler - no double firing
    card.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        addToCart(item);
    });
    
    const imageUrl = item.imageUrl || '/images/items/placeholder.webp';
    
    card.innerHTML = `
        <img src="${imageUrl}" alt="${item.name}" class="item-image" onerror="this.src='/images/items/placeholder.webp'">
        <div class="item-name">${item.name}</div>
        <div class="item-sku">${item.sku}</div>
        <div class="item-price">$${parseFloat(item.retailPrice || 0).toFixed(2)}</div>
    `;
    
    return card;
}

// Add item to cart
// addToCart function moved to js/cart.js for centralization

// Update cart display
function updateCartDisplay() {
    
    const cartItems = document.getElementById('cartItems');
    const cartTotal = document.getElementById('posCartTotal');
    const checkoutBtn = document.getElementById('checkoutBtn');
    
    if (!cartItems || !cartTotal || !checkoutBtn) {
        console.error('❌ Missing DOM elements for cart display');
        return;
    }
    
    if (cart.length === 0) {
        cartItems.innerHTML = `
            <div class="empty-cart">
                Cart is empty<br>
                <small>Scan or search for items to add them</small>
            </div>
        `;
        cartTotal.textContent = '$0.00';
        checkoutBtn.disabled = true;return;
    }
    
    // Build cart HTML
    let cartHTML = '';
    let subtotalDisplay = 0;
    
    cart.forEach((item, index) => {
        const itemTotal = item.price * item.quantity;
        subtotalDisplay += itemTotal;
        
        cartHTML += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-sku">${item.sku}</div>
                </div>
                <div class="cart-item-controls">
                    <button class="qty-btn" onclick="updateQuantity(${index}, -1)">-</button>
                    <span class="qty-display">${item.quantity}</span>
                    <button class="qty-btn" onclick="updateQuantity(${index}, 1)">+</button>
                    <span class="cart-item-price">$${itemTotal.toFixed(2)}</span>
                </div>
            </div>
        `;
    });
    
    // Update display
    cartItems.innerHTML = cartHTML;
    
    // Calculate total with tax for display (matches checkout)  
    const TAX_RATE_DISPLAY = 0.0825; // 8.25% sales tax
    const taxAmountDisplay = subtotalDisplay * TAX_RATE_DISPLAY;
    const totalWithTax = subtotalDisplay + taxAmountDisplay;
    
    // Update cart total with enhanced debugging and retry mechanism
    const totalString = '$' + totalWithTax.toFixed(2);
    console.log('💰 Updating cart total to:', totalString, 'from subtotal:', subtotalDisplay.toFixed(2), 'tax:', taxAmountDisplay.toFixed(2));
    
    // Clear any existing content and force update
    cartTotal.textContent = '';
    cartTotal.innerHTML = '';
    
    // Force immediate update with multiple methods
    cartTotal.textContent = totalString;
    cartTotal.innerHTML = totalString;
    cartTotal.setAttribute('data-total', totalString);
    cartTotal.title = `Cart Total: ${totalString}`;
    
    // Visual flash effect to indicate update
    cartTotal.style.background = 'yellow';
    cartTotal.style.animation = 'none';
    setTimeout(() => {
        cartTotal.style.background = 'rgba(255,255,255,0.1)';
        cartTotal.style.animation = 'pulse 0.5s ease-in-out';
    }, 200);
    
    // Enhanced verification and retry mechanism with longer timeout
    setTimeout(() => {
        const currentDisplay = cartTotal.textContent || cartTotal.innerHTML;
        console.log('🔍 Cart total verification:', {
            expected: totalString,
            textContent: cartTotal.textContent,
            innerHTML: cartTotal.innerHTML,
            getAttribute: cartTotal.getAttribute('data-total'),
            title: cartTotal.title,
            visible: cartTotal.offsetWidth > 0 && cartTotal.offsetHeight > 0,
            computed: window.getComputedStyle(cartTotal).display
        });
        
        if (currentDisplay !== totalString) {
            console.warn('⚠️ Cart total update failed. Expected:', totalString, 'Got:', currentDisplay);
            console.warn('⚠️ Forcing cart total update with aggressive methods...');
            
            // Aggressive retry with DOM manipulation
            cartTotal.textContent = totalString;
            cartTotal.innerHTML = totalString;
            cartTotal.innerText = totalString;
            cartTotal.setAttribute('data-amount', totalString);
            
            // Force visibility and styling
            cartTotal.style.cssText = 'color: #dc3545 !important; font-size: 2.5rem !important; font-weight: 700 !important; text-shadow: 3px 3px 6px rgba(0,0,0,0.3) !important; display: inline-block !important; visibility: visible !important; opacity: 1 !important; background: rgba(255,255,255,0.1) !important; padding: 4px 8px !important; border-radius: 4px !important; border: 2px solid #dc3545 !important; min-width: 80px !important; text-align: center !important;';
            
            // Final verification with more detailed logging
            setTimeout(() => {
                const finalDisplay = cartTotal.textContent || cartTotal.innerHTML;
                console.log('🔍 Final cart total check:', {
                    finalDisplay,
                    expected: totalString,
                    success: finalDisplay === totalString,
                    elementRect: cartTotal.getBoundingClientRect(),
                    computedStyle: {
                        display: window.getComputedStyle(cartTotal).display,
                        visibility: window.getComputedStyle(cartTotal).visibility,
                        opacity: window.getComputedStyle(cartTotal).opacity,
                        fontSize: window.getComputedStyle(cartTotal).fontSize,
                        color: window.getComputedStyle(cartTotal).color
                    }
                });
                
                if (finalDisplay !== totalString) {
                    console.error('❌ Cart total update FAILED after aggressive retry');
                    // Last resort: create new element
                    const newTotal = document.createElement('span');
                    newTotal.id = 'posCartTotal';
                    newTotal.textContent = totalString;
                    newTotal.style.cssText = cartTotal.style.cssText;
                    cartTotal.parentNode.replaceChild(newTotal, cartTotal);} else {}
            }, 100);
        } else {}
    }, 100);
    
    checkoutBtn.disabled = false;
    
    console.log('✅ Cart display updated:', cart.length, 'items, calculated total:', totalWithTax.toFixed(2), 'display total:', totalString);
}

// Update item quantity in cart
function updateQuantity(index, change) {
    cart[index].quantity += change;
    
    if (cart[index].quantity <= 0) {
        cart.splice(index, 1);
    }
    
    updateCartDisplay();
}

// POS Modal System
function showPOSModal(title, message, type = 'info', autoClose = false) {
    // Remove existing modal if any
    hidePOSModal();
    
    const modal = document.createElement('div');
    modal.id = 'posModal';
    modal.className = 'pos-modal-overlay';
    
    const iconMap = {
        'info': 'ℹ️',
        'success': '✅',
        'warning': '⚠️',
        'error': '❌',
        'processing': '⏳'
    };
    
    const colorMap = {
        'info': '#2196F3',
        'success': '#4CAF50',
        'warning': '#FF9800',
        'error': '#f44336',
        'processing': '#9C27B0'
    };
    
<<<<<<< HEAD
            modal.innerHTML = `
            <div class="pos-modal-content">
                <div class="pos-modal-header" style="background: ${colorMap[type]};">
                    <h3 class="pos-modal-header">
                        <span style="font-size: 1.5rem;">${iconMap[type]}</span>
                        ${title}
                    </h3>
                </div>
=======
    modal.innerHTML = `
        <div class="pos-modal-content">
            <div class="pos-modal-header pos-modal-header-${type.toLowerCase().replace(/ /g, '-')}">
                <h3 class="pos-modal-title">
                    <span class="pos-modal-icon">${iconMap[type]}</span>
                    ${title}
                </h3>
            </div>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            <div class="pos-modal-body">
                ${message}
            </div>
            ${!autoClose ? `
                <div class="pos-modal-footer">
<<<<<<< HEAD
                    <button class="pos-modal-btn pos-modal-btn-close" onclick="hidePOSModal()">OK</button>
=======
                    <button class="btn btn-light" onclick="hidePOSModal()">OK</button>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                </div>
            ` : ''}
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Auto close processing modals after 30 seconds
    if (type === 'processing') {
        setTimeout(() => {
            if (document.getElementById('posModal')) {
                hidePOSModal();
            }
        }, 30000);
    }
}

function showPOSConfirm(title, message, confirmText = 'Confirm', cancelText = 'Cancel') {
    return new Promise((resolve) => {
        // Remove existing modal if any
        hidePOSModal();
        
        const modal = document.createElement('div');
        modal.id = 'posModal';
        modal.className = 'pos-modal-overlay';
        
        modal.innerHTML = `
            <div class="pos-modal-content">
<<<<<<< HEAD
                <div class="pos-modal-header" style="background: #FF9800;">
                    <h3 class="pos-modal-header">
                        <span style="font-size: 1.5rem;">❓</span>
=======
                <div class="pos-modal-header pos-modal-header-warning">
                    <h3 class="pos-modal-title">
                        <span class="pos-modal-icon">❓</span>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                        ${title}
                    </h3>
                </div>
                <div class="pos-modal-body">
                    ${message}
                </div>
                <div class="pos-modal-footer">
<<<<<<< HEAD
                    <button class="pos-modal-btn pos-modal-btn-cancel" onclick="resolvePOSConfirm(false)">${cancelText}</button>
                    <button class="pos-modal-btn pos-modal-btn-confirm" onclick="resolvePOSConfirm(true)">${confirmText}</button>
=======
                    <button class="btn btn-light" onclick="resolvePOSConfirm(false)">${cancelText}</button>
                    <button class="btn btn-primary" onclick="resolvePOSConfirm(true)">${confirmText}</button>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Store resolve function globally
        window.posModalResolve = resolve;
    });
}

function resolvePOSConfirm(result) {
    if (window.posModalResolve) {
        window.posModalResolve(result);
        delete window.posModalResolve;
    }
    hidePOSModal();
}

function hidePOSModal() {
    const modal = document.getElementById('posModal');
    if (modal) {
        modal.remove();
    }
}

// Payment Method Selector
function showPaymentMethodSelector(total) {
    return new Promise((resolve) => {
        hidePOSModal();
        
        const modal = document.createElement('div');
        modal.id = 'posModal';
        modal.className = 'pos-modal-overlay';
        
        modal.innerHTML = `
<<<<<<< HEAD
            <div class="pos-modal-content" style="max-width: 500px;">
                <div class="pos-modal-header" style="background: #4CAF50;">
                    <h3 style="margin: 0; color: white; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.5rem;">💳</span>
                        Select Payment Method
                    </h3>
                </div>
                <div class="pos-modal-body" style="padding: 2rem;">
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <div style="font-size: 1.5rem; font-weight: bold; color: #333;">
=======
            <div class="pos-modal-content pos-modal-small">
                <div class="pos-modal-header pos-modal-header-success">
                    <h3 class="pos-modal-title">
                        <span class="pos-modal-icon">💳</span>
                        Select Payment Method
                    </h3>
                </div>
                <div class="pos-modal-body">
                    <div >
                        <div >
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                            Total: $${total.toFixed(2)}
                        </div>
                    </div>
                    
<<<<<<< HEAD
                    <div class="payment-methods" style="display: grid; gap: 1rem; margin-bottom: 2rem;">
                        <button class="payment-method-btn pos-payment-method pos-payment-cash" data-method="Cash">
                            <span style="font-size: 1.5rem;">💵</span>
                            <div style="text-align: left;">
                                <div style="font-weight: bold;">Cash</div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">Includes change calculator</div>
                            </div>
                        </button>
                        
                        <button class="payment-method-btn pos-payment-method pos-payment-credit" data-method="Credit Card">
                            <span style="font-size: 1.5rem;">💳</span>
                            <div style="text-align: left;">
                                <div style="font-weight: bold;">Credit Card</div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">Visa, MasterCard, etc.</div>
                            </div>
                        </button>
                        
                        <button class="payment-method-btn pos-payment-method pos-payment-debit" data-method="Debit Card">
                            <span style="font-size: 1.5rem;">💳</span>
                            <div style="text-align: left;">
                                <div style="font-weight: bold;">Debit Card</div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">PIN required</div>
                            </div>
                        </button>
                        
                        <button class="payment-method-btn pos-payment-method pos-payment-check" data-method="Check">
                            <span style="font-size: 1.5rem;">📝</span>
                            <div style="text-align: left;">
                                <div style="font-weight: bold;">Check</div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">Personal or business</div>
=======
                    <div class="payment-methods">
                        <button class="payment-method-btn" data-method="Cash">
                            <span class="pos-modal-icon">💵</span>
                            <div >
                                <div >Cash</div>
                                <div >Includes change calculator</div>
                            </div>
                        </button>
                        
                        <button class="payment-method-btn" data-method="Credit Card">
                            <span class="pos-modal-icon">💳</span>
                            <div >
                                <div >Credit Card</div>
                                <div >Visa, MasterCard, etc.</div>
                            </div>
                        </button>
                        
                        <button class="payment-method-btn" data-method="Debit Card">
                            <span class="pos-modal-icon">💳</span>
                            <div >
                                <div >Debit Card</div>
                                <div >PIN required</div>
                            </div>
                        </button>
                        
                        <button class="payment-method-btn" data-method="Check">
                            <span class="pos-modal-icon">📝</span>
                            <div >
                                <div >Check</div>
                                <div >Personal or business</div>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                            </div>
                        </button>
                    </div>
                </div>
                <div class="pos-modal-footer">
<<<<<<< HEAD
                    <button class="pos-modal-btn pos-modal-btn-cancel" onclick="resolvePaymentMethod(null)">Cancel</button>
=======
                    <button class="btn btn-light" onclick="resolvePaymentMethod(null)">Cancel</button>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Add click handlers for payment methods
        modal.querySelectorAll('.payment-method-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const method = btn.getAttribute('data-method');
                resolvePaymentMethod(method);
            });
            
            // Hover effects
            btn.addEventListener('mouseenter', () => {
                btn.style.transform = 'translateY(-2px)';
                btn.style.boxShadow = '0 4px 12px rgba(0,0,0,0.2)';
            });
            
            btn.addEventListener('mouseleave', () => {
                btn.style.transform = 'translateY(0)';
                btn.style.boxShadow = 'none';
            });
        });
        
        // Store resolve function globally
        window.paymentMethodResolve = resolve;
    });
}

function resolvePaymentMethod(method) {
    if (window.paymentMethodResolve) {
        window.paymentMethodResolve(method);
        delete window.paymentMethodResolve;
    }
    hidePOSModal();
}

// Cash Calculator
function showCashCalculator(total) {
    return new Promise((resolve) => {
        hidePOSModal();
        
        const modal = document.createElement('div');
        modal.id = 'posModal';
        modal.className = 'pos-modal-overlay';
        
        modal.innerHTML = `
<<<<<<< HEAD
            <div class="pos-modal-content" style="max-width: 600px;">
                <div class="pos-modal-header" style="background: #4CAF50;">
                    <h3 style="margin: 0; color: white; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.5rem;">🧮</span>
                        Cash Calculator
                    </h3>
                </div>
                <div class="pos-modal-body" style="padding: 2rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                        <div style="text-align: center; padding: 1rem; background: #f5f5f5; border-radius: 8px;">
                            <div style="font-size: 1rem; color: #666; margin-bottom: 0.5rem;">Total Due</div>
                            <div style="font-size: 2rem; font-weight: bold; color: #dc3545;">$${total.toFixed(2)}</div>
                        </div>
                        <div style="text-align: center; padding: 1rem; background: #e8f5e8; border-radius: 8px;">
                            <div style="font-size: 1rem; color: #666; margin-bottom: 0.5rem;">Change Due</div>
                            <div id="changeDue" style="font-size: 2rem; font-weight: bold; color: #4CAF50;">$0.00</div>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 2rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Cash Received:</label>
                        <input type="number" id="cashReceived" step="0.01" min="0" placeholder="0.00" 
                               style="width: 100%; padding: 1rem; font-size: 1.5rem; border: 2px solid #ddd; border-radius: 8px; text-align: center;"
                               oninput="calculateChange(${total})">
                    </div>
                    
                    <div class="quick-amounts" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; margin-bottom: 2rem;" id="quickAmountButtons">
                        <!-- Quick amount buttons will be generated by JavaScript -->
                    </div>
                    
                    <div id="insufficientFunds" style="display: none; background: #ffebee; color: #c62828; padding: 1rem; border-radius: 8px; text-align: center; margin-bottom: 1rem;">
=======
            <div class="pos-modal-content pos-modal-medium">
                <div class="pos-modal-header pos-modal-header-success">
                    <h3 class="pos-modal-title">
                        <span class="pos-modal-icon">🧮</span>
                        Cash Calculator
                    </h3>
                </div>
                <div class="pos-modal-body">
                    <div >
                        <div >
                            <div >Total Due</div>
                            <div >$${total.toFixed(2)}</div>
                        </div>
                        <div >
                            <div >Change Due</div>
                            <div id="changeDue" >$0.00</div>
                        </div>
                    </div>
                    
                    <div >
                        <label >Cash Received:</label>
                        <input type="number" id="cashReceived" step="0.01" min="0" placeholder="0.00" 
                               
                               oninput="calculateChange(${total})">
                    </div>
                    
                    <div class="quick-amounts" id="quickAmountButtons">
                        <!- Quick amount buttons will be generated by JavaScript ->
                    </div>
                    
                    <div id="insufficientFunds" class="hidden">
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                        ⚠️ Insufficient funds - please collect more cash
                    </div>
                </div>
                <div class="pos-modal-footer">
<<<<<<< HEAD
                    <button class="pos-modal-btn pos-modal-btn-cancel" onclick="resolveCashCalculator(null)">Cancel</button>
                    <button id="acceptCashBtn" class="pos-modal-btn pos-modal-btn-confirm" onclick="acceptCashPayment(${total})" disabled>Accept Payment</button>
=======
                    <button class="btn btn-light" onclick="resolveCashCalculator(null)">Cancel</button>
                    <button id="acceptCashBtn" class="btn btn-primary" onclick="acceptCashPayment(${total})" disabled>Accept Payment</button>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Generate smart quick amount buttons
        generateQuickAmountButtons(total);
        
        // Focus on cash input
        setTimeout(() => {
            document.getElementById('cashReceived').focus();
        }, 100);
        
        // Hover effects are now handled in generateQuickAmountButtons()
        
        // Store resolve function globally
        window.cashCalculatorResolve = resolve;
    });
}

function generateQuickAmountButtons(total) {
    const container = document.getElementById('quickAmountButtons');
    if (!container) return;
    
    // Generate smart suggestions based on realistic bill denominations
    const suggestions = [];
    
    // Always include exact amount
    suggestions.push({
        label: 'Exact',
        amount: total,
        style: 'background: #e3f2fd; color: #1976d2;' // Light blue for exact
    });
    
    // Smart bill denomination logic
    const nextDollar = Math.ceil(total);
    
    // For amounts under $20, suggest common small bills
    if (total <= 20) {
        if (nextDollar !== total) suggestions.push({ label: `$${nextDollar}`, amount: nextDollar });
        if (total <= 10 && nextDollar < 20) suggestions.push({ label: '$20', amount: 20 });
        if (total <= 5 && nextDollar < 10) suggestions.push({ label: '$10', amount: 10 });
    }
    // For amounts $20-$50, suggest $20 increments and $50
    else if (total <= 50) {
        if (nextDollar !== total) suggestions.push({ label: `$${nextDollar}`, amount: nextDollar });
        
        // Find next $20 increment
        const next20 = Math.ceil(total / 20) * 20;
        if (next20 > nextDollar && next20 <= 100) {
            suggestions.push({ label: `$${next20}`, amount: next20 });
        }
        
        // Always suggest $50 if total is under $50
        if (total < 50) {
            suggestions.push({ label: '$50', amount: 50 });
        }
    }
    // For amounts over $50, suggest logical bill combinations
    else {
        if (nextDollar !== total) suggestions.push({ label: `$${nextDollar}`, amount: nextDollar });
        
        // Find next logical bill denomination
        if (total <= 100) {
            const next20 = Math.ceil(total / 20) * 20;
            if (next20 > nextDollar && next20 < 100) {
                suggestions.push({ label: `$${next20}`, amount: next20 });
            }
            suggestions.push({ label: '$100', amount: 100 });
        } else {
            // For amounts over $100, suggest $50 and $100 increments
            const next50 = Math.ceil(total / 50) * 50;
            const next100 = Math.ceil(total / 100) * 100;
            
            if (next50 > nextDollar && next50 < next100) {
                suggestions.push({ label: `$${next50}`, amount: next50 });
            }
            suggestions.push({ label: `$${next100}`, amount: next100 });
        }
    }
    
    // Remove duplicates and limit to 4 suggestions
    const uniqueSuggestions = [];
    const seenAmounts = new Set();
    
    for (const suggestion of suggestions) {
        if (!seenAmounts.has(suggestion.amount) && uniqueSuggestions.length < 4) {
            seenAmounts.add(suggestion.amount);
            uniqueSuggestions.push(suggestion);
        }
    }
    
    // If we have less than 4, add some higher denominations
    while (uniqueSuggestions.length < 4) {
        const lastAmount = uniqueSuggestions[uniqueSuggestions.length - 1].amount;
        let nextAmount;
        
        if (lastAmount < 20) nextAmount = 20;
        else if (lastAmount < 50) nextAmount = 50;
        else if (lastAmount < 100) nextAmount = 100;
        else nextAmount = Math.ceil(lastAmount / 100) * 100 + 100;
        
        if (!seenAmounts.has(nextAmount)) {
            uniqueSuggestions.push({ label: `$${nextAmount}`, amount: nextAmount });
            seenAmounts.add(nextAmount);
        } else {
            break; // Avoid infinite loop
        }
    }
    
    // Generate HTML for buttons
    container.innerHTML = uniqueSuggestions.map(suggestion => `
        <button class="quick-amount-btn" 
                onclick="setCashAmount(${suggestion.amount})" 
<<<<<<< HEAD
                style="background: #f0f0f0; border: none; padding: 0.75rem; border-radius: 6px; cursor: pointer; font-size: 1rem; ${suggestion.style || ''}"
=======
                
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                title="Suggest ${suggestion.label === 'Exact' ? 'exact change' : suggestion.label + ' bill'}">
            ${suggestion.label}
        </button>
    `).join('');
    
    // Add hover effects
    container.querySelectorAll('.quick-amount-btn').forEach(btn => {
        btn.addEventListener('mouseenter', () => {
            if (btn.style.background.includes('#e3f2fd')) {
                btn.style.background = '#bbdefb'; // Darker blue for exact button
            } else {
                btn.style.background = '#e0e0e0';
            }
        });
        btn.addEventListener('mouseleave', () => {
            if (btn.title.includes('exact change')) {
                btn.style.background = '#e3f2fd'; // Light blue for exact
            } else {
                btn.style.background = '#f0f0f0';
            }
        });
    });
}

function calculateChange(total) {
    const cashReceived = parseFloat(document.getElementById('cashReceived').value) || 0;
    const change = cashReceived - total;
    
    document.getElementById('changeDue').textContent = `$${Math.max(0, change).toFixed(2)}`;
    
    const insufficientDiv = document.getElementById('insufficientFunds');
    const acceptBtn = document.getElementById('acceptCashBtn');
    
    if (cashReceived < total) {
        insufficientDiv.style.display = 'block';
        acceptBtn.disabled = true;
        acceptBtn.style.opacity = '0.5';
    } else {
        insufficientDiv.style.display = 'none';
        acceptBtn.disabled = false;
        acceptBtn.style.opacity = '1';
    }
}

function setCashAmount(amount) {
    const cashInput = document.getElementById('cashReceived');
    cashInput.value = amount.toFixed(2);
    
    // Extract total from the modal display
    const totalElement = document.querySelector('.pos-modal-body').textContent.match(/Total Due[^$]*\$(\d+\.\d+)/);
    const total = totalElement ? parseFloat(totalElement[1]) : amount;
    
    calculateChange(total);
}

function acceptCashPayment(total) {
    const cashReceived = parseFloat(document.getElementById('cashReceived').value);
    const change = cashReceived - total;
    
    if (cashReceived >= total) {
        resolveCashCalculator({
            received: cashReceived,
            change: change
        });
    }
}

function resolveCashCalculator(result) {
    if (window.cashCalculatorResolve) {
        window.cashCalculatorResolve(result);
        delete window.cashCalculatorResolve;
    }
    hidePOSModal();
}

// Process checkout
async function processCheckout() {
    if (cart.length === 0) {
        showPOSModal('Empty Cart', 'Please add items to the cart before completing a sale.', 'warning');
        return;
    }
    
    // Calculate subtotal and tax
    const TAX_RATE = 0.0825; // 8.25% sales tax - could be made configurable
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const taxAmount = subtotal * TAX_RATE;
    const total = subtotal + taxAmount;
    
    // Step 1: Select payment method
    const paymentMethod = await showPaymentMethodSelector(total);
    if (!paymentMethod) {
        return; // User cancelled
    }
    
    // Step 2: Handle cash calculator if needed
    let cashReceived = 0;
    let changeAmount = 0;
    
    if (paymentMethod === 'Cash') {
        const cashResult = await showCashCalculator(total);
        if (!cashResult) {
            return; // User cancelled cash calculator
        }
        cashReceived = cashResult.received;
        changeAmount = cashResult.change;
    }
    
    // Step 3: Final confirmation with tax breakdown
<<<<<<< HEAD
    let confirmMessage = `<div style="text-align: left; line-height: 1.6;">
        <div style="text-align: center; font-size: 1.2rem; margin-bottom: 1rem; font-weight: bold;">Complete Sale</div>
        
        <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Subtotal:</span>
                <span><strong>$${subtotal.toFixed(2)}</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: #666;">
                <span>Sales Tax (${(TAX_RATE * 100).toFixed(2)}%):</span>
                <span>$${taxAmount.toFixed(2)}</span>
            </div>
            <div style="border-top: 2px solid #dc3545; padding-top: 0.5rem; margin-top: 0.5rem;">
                <div style="display: flex; justify-content: space-between; font-size: 1.25rem; font-weight: bold; color: #dc3545;">
=======
    let confirmMessage = `<div >
        <div >Complete Sale</div>
        
        <div >
            <div >
                <span>Subtotal:</span>
                <span><strong>$${subtotal.toFixed(2)}</strong></span>
            </div>
            <div >
                <span>Sales Tax (${(TAX_RATE * 100).toFixed(2)}%):</span>
                <span>$${taxAmount.toFixed(2)}</span>
            </div>
            <div >
                <div >
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    <span>Total:</span>
                    <span>$${total.toFixed(2)}</span>
                </div>
            </div>
        </div>
        
<<<<<<< HEAD
        <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px;">
            <div style="font-weight: bold; margin-bottom: 0.5rem;">Payment Details:</div>
=======
        <div >
            <div >Payment Details:</div>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            <div><strong>Method:</strong> ${paymentMethod}</div>`;
    
    if (paymentMethod === 'Cash') {
        confirmMessage += `
            <div><strong>Cash Received:</strong> $${cashReceived.toFixed(2)}</div>
<<<<<<< HEAD
            <div style="font-weight: bold; color: #4CAF50;"><strong>Change Due:</strong> $${changeAmount.toFixed(2)}</div>`;
=======
            <div ><strong>Change Due:</strong> $${changeAmount.toFixed(2)}</div>`;
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    }
    
    confirmMessage += `</div></div>`;
    
    const confirmed = await showPOSConfirm(
        'Complete Sale',
        confirmMessage,
        'Process Sale',
        'Cancel'
    );
    
    if (!confirmed) {
        return;
    }
    
    // Show processing modal
    showPOSModal('Processing Sale', 'Please wait while we process your transaction...', 'processing');
    
    try {
        // Create order for POS customer - format for add-order.php API
        const itemIds = cart.map(item => item.sku);
        const quantities = cart.map(item => item.quantity);
        const colors = cart.map(() => null); // No color selection in POS
        const sizes = cart.map(() => null);  // No size selection in POS
        
        const orderData = {
            customerId: 'POS001', // POS customer ID (changed from userId to customerId)
            itemIds: itemIds,     // Array of SKUs
            quantities: quantities, // Array of quantities  
            colors: colors,       // Array of colors (null for POS)
            sizes: sizes,         // Array of sizes (null for POS)
            total: total,
            subtotal: subtotal,
            taxAmount: taxAmount,
            taxRate: TAX_RATE,
            paymentMethod: paymentMethod,
            paymentStatus: 'Received',
            shippingMethod: 'Customer Pickup',
            order_status: 'Delivered'
        };const response = await fetch('/api/add-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(orderData)
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Hide processing modal
            hidePOSModal();
            
            // Show receipt options modal
            showReceiptModal({
                orderId: result.orderId,
                items: cart,
                subtotal: subtotal,
                taxAmount: taxAmount,
                taxRate: TAX_RATE,
                total: total,
                paymentMethod: paymentMethod,
                cashReceived: cashReceived,
                changeAmount: changeAmount,
                timestamp: new Date()
            });
            
        } else {
            throw new Error(result.error || 'Checkout failed');
        }
        
    } catch (error) {
        console.error('Checkout error:', error);
        hidePOSModal();
        showPOSModal('Transaction Failed', `❌ Checkout failed: ${error.message}`, 'error');
    }
}

// Receipt Modal System
function showReceiptModal(saleData) {
    // Store sale data for email functionality
    window.lastSaleData = saleData;
    
    hidePOSModal();
    
    const modal = document.createElement('div');
    modal.id = 'posModal';
    modal.className = 'pos-modal-overlay';
    
    const receiptContent = generateReceiptContent(saleData);
    
    modal.innerHTML = `
<<<<<<< HEAD
        <div class="pos-modal-content" style="max-width: 500px;">
            <div class="pos-modal-header" style="background: #4CAF50;">
                <h3 style="margin: 0; color: white; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.5rem;">🧾</span>
                    Transaction Complete
                </h3>
            </div>
            <div class="pos-modal-body" style="padding: 0; max-height: 500px; overflow-y: auto;">
                ${receiptContent}
            </div>
            <div class="pos-modal-footer" style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                <button class="pos-modal-btn" onclick="printReceipt()" style="background: #2196F3; color: white; flex: 1; min-width: 120px;">
                    🖨️ Print Receipt
                </button>
                <button class="pos-modal-btn" onclick="emailReceipt('${saleData.orderId}')" style="background: #FF9800; color: white; flex: 1; min-width: 120px;">
                    📧 Email Receipt
                </button>
                <button class="pos-modal-btn pos-modal-btn-confirm" onclick="finishSale()" style="background: #4CAF50; color: white; flex: 1; min-width: 120px;">
=======
        <div class="pos-modal-content pos-modal-small">
            <div class="pos-modal-header pos-modal-header-success">
                <h3 class="pos-modal-title">
                    <span class="pos-modal-icon">🧾</span>
                    Transaction Complete
                </h3>
            </div>
            <div class="pos-modal-body pos-modal-body-scroll">
                ${receiptContent}
            </div>
            <div class="pos-modal-footer" >
                <button class="btn btn-secondary" onclick="printReceipt()" >
                    🖨️ Print Receipt
                </button>
                <button class="btn btn-secondary" onclick="emailReceipt('${saleData.orderId}')" >
                    📧 Email Receipt
                </button>
                <button class="btn btn-primary" onclick="finishSale()" >
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    ✅ Finish Sale
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
}

function generateReceiptContent(saleData) {
    const timestamp = saleData.timestamp.toLocaleString();
    
    let itemsHTML = '';
    saleData.items.forEach(item => {
        const itemTotal = item.price * item.quantity;
        itemsHTML += `
<<<<<<< HEAD
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; padding: 0.25rem 0; border-bottom: 1px dotted #ccc;">
                <div style="flex: 1;">
                    <div style="font-weight: bold;">${item.name}</div>
                    <div style="font-size: 0.8rem; color: #666;">SKU: ${item.sku}</div>
                    <div style="font-size: 0.8rem; color: #666;">${item.quantity} x $${item.price.toFixed(2)}</div>
                </div>
                <div style="font-weight: bold; text-align: right;">
=======
            <div >
                <div >
                    <div >${item.name}</div>
                    <div >SKU: ${item.sku}</div>
                    <div >${item.quantity} x $${item.price.toFixed(2)}</div>
                </div>
                <div >
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    $${itemTotal.toFixed(2)}
                </div>
            </div>
        `;
    });
    
    return `
<<<<<<< HEAD
        <div style="background: white; padding: 2rem; font-family: 'Courier New', monospace; font-size: 0.9rem; line-height: 1.4;">
            <!-- Receipt Header -->
            <div style="text-align: center; margin-bottom: 2rem; border-bottom: 2px solid #333; padding-bottom: 1rem;">
                <div style="font-size: 1.4rem; font-weight: bold; margin-bottom: 0.5rem;">WHIMSICALFROG</div>
                <div style="font-size: 0.9rem;">Point of Sale Receipt</div>
            </div>
            
            <!-- Transaction Info -->
            <div style="margin-bottom: 1.5rem; font-size: 0.8rem;">
=======
        <div >
            <!- Receipt Header ->
            <div >
                <div >WHIMSICALFROG</div>
                <div >Point of Sale Receipt</div>
            </div>
            
            <!- Transaction Info ->
            <div >
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                <div><strong>Order ID:</strong> ${saleData.orderId}</div>
                <div><strong>Date:</strong> ${timestamp}</div>
                <div><strong>Cashier:</strong> POS System</div>
            </div>
            
<<<<<<< HEAD
            <!-- Items -->
            <div style="margin-bottom: 1.5rem;">
                <div style="font-weight: bold; margin-bottom: 1rem; border-bottom: 1px solid #333; padding-bottom: 0.5rem;">ITEMS PURCHASED</div>
                ${itemsHTML}
            </div>
            
            <!-- Totals -->
            <div style="border-top: 2px solid #333; padding-top: 1rem; margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span>Subtotal:</span>
                    <span>$${saleData.subtotal.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <span>Sales Tax (${(saleData.taxRate * 100).toFixed(2)}%):</span>
                    <span>$${saleData.taxAmount.toFixed(2)}</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: bold; border-top: 1px solid #333; padding-top: 0.5rem; margin-top: 0.5rem;">
=======
            <!- Items ->
            <div >
                <div >ITEMS PURCHASED</div>
                ${itemsHTML}
            </div>
            
            <!- Totals ->
            <div >
                <div >
                    <span>Subtotal:</span>
                    <span>$${saleData.subtotal.toFixed(2)}</span>
                </div>
                <div >
                    <span>Sales Tax (${(saleData.taxRate * 100).toFixed(2)}%):</span>
                    <span>$${saleData.taxAmount.toFixed(2)}</span>
                </div>
                <div >
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    <span>TOTAL:</span>
                    <span>$${saleData.total.toFixed(2)}</span>
                </div>
            </div>
            
<<<<<<< HEAD
            <!-- Payment Info -->
            <div style="margin-bottom: 1.5rem; border-top: 1px solid #ccc; padding-top: 1rem;">
                <div style="font-weight: bold; margin-bottom: 0.5rem;">PAYMENT DETAILS</div>
=======
            <!- Payment Info ->
            <div >
                <div >PAYMENT DETAILS</div>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                <div><strong>Method:</strong> ${saleData.paymentMethod}</div>
                ${saleData.paymentMethod === 'Cash' ? `
                    <div><strong>Cash Received:</strong> $${saleData.cashReceived.toFixed(2)}</div>
                    <div><strong>Change Due:</strong> $${saleData.changeAmount.toFixed(2)}</div>
                ` : ''}
            </div>
            
<<<<<<< HEAD
            <!-- Footer -->
            <div style="text-align: center; font-size: 0.8rem; color: #666; border-top: 1px dotted #ccc; padding-top: 1rem;">
                <div>Thank you for your business!</div>
                <div style="margin-top: 0.5rem;">Visit us online at WhimsicalFrog.com</div>
=======
            <!- Footer ->
            <div >
                <div>Thank you for your business!</div>
                <div >Visit us online at WhimsicalFrog.com</div>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            </div>
        </div>
    `;
}

function printReceipt() {
    const receiptContent = document.querySelector('.pos-modal-body').innerHTML;
    
    // Create a new window for printing
    const printWindow = window.open('', '_blank', 'width=600,height=800');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Receipt - Order ${new Date().getTime()}</title>
            
<<<<<<< HEAD
        
<!-- Database-driven CSS for admin_pos -->

<script>
    // Load CSS from database
    async function loadAdmin_posCSS() {
        try {
            const response = await fetch('/api/css_generator.php?category=admin_pos');
            const cssText = await response.text();
            const styleElement = document.getElementById('admin_pos-css');
            if (styleElement && cssText) {
                styleElement.textContent = cssText;
                console.log('✅ admin_pos CSS loaded from database');
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load admin_pos CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>admin_pos CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadAdmin_posCSS);
</script>

<!-- Database-driven CSS for admin_pos -->

<script>
    // Load CSS from database
    async function loadAdmin_posCSS() {
        try {
            const response = await fetch('/api/css_generator.php?category=admin_pos');
            const cssText = await response.text();
            const styleElement = document.getElementById('admin_pos-css');
            if (styleElement && cssText) {
                styleElement.textContent = cssText;
                console.log('✅ admin_pos CSS loaded from database');
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load admin_pos CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>admin_pos CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadAdmin_posCSS);
</script>

<!-- Database-driven CSS for admin_pos -->
<style id="admin_pos-css">
/* CSS will be loaded from database */
</style>
<script>
    // Load CSS from database
    async function loadAdmin_posCSS() {
        try {
            const response = await fetch('/api/css_generator.php?category=admin_pos');
            const cssText = await response.text();
            const styleElement = document.getElementById('admin_pos-css');
            if (styleElement && cssText) {
                styleElement.textContent = cssText;
                console.log('✅ admin_pos CSS loaded from database');
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load admin_pos CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>admin_pos CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadAdmin_posCSS);
</script>
</head>
=======
        </head>
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
        <body>
            ${receiptContent}
            <script>
                window.onload = function() {
                    window.print();
                    setTimeout(() => window.close(), 1000);
                }
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function emailReceipt(orderId) {
    // Get the current sale data
    if (!window.lastSaleData || window.lastSaleData.orderId !== orderId) {
        showPOSModal(
            'Email Receipt - Error',
            'Sale data not found. Please complete the checkout process first.',
            'error'
        );
        return;
    }

    // Prompt for customer email
    const email = prompt('Enter customer email address:');
    
    if (!email) {
        return; // User cancelled
    }
    
    // Basic email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showPOSModal(
            'Email Receipt - Error',
            'Please enter a valid email address.',
            'error'
        );
        return;
    }
    
    // Show sending modal
    showPOSModal(
        'Sending Receipt...',
        'Please wait while we send the receipt to ' + email,
        'info'
    );
    
    // Send email via API
    fetch('/api/send_receipt_email.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            orderId: orderId,
            customerEmail: email,
            orderData: window.lastSaleData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showPOSModal(
                'Email Sent Successfully! ✅',
                data.message || 'Receipt has been sent to ' + email,
                'success'
            );
        } else {
            showPOSModal(
                'Email Failed ❌',
                'Failed to send receipt: ' + (data.error || 'Unknown error'),
                'error'
            );
        }
    })
    .catch(error => {
        console.error('Email receipt error:', error);
        showPOSModal(
            'Email Failed ❌',
            'Failed to send receipt: ' + error.message,
            'error'
        );
    });
}

function finishSale() {
    hidePOSModal();
    
    // Clear cart and reset POScart = [];
    updateCartDisplay();
    document.getElementById('skuSearch').value = '';
    showAllItems();
    
    // Verify cart total was reset correctly
    setTimeout(() => {
        const cartTotal = document.getElementById('posCartTotal');
        if (cartTotal) {
            const currentTotal = cartTotal.textContent || cartTotal.innerHTML;if (currentTotal !== '$0.00') {
                console.warn('⚠️ Cart total not reset properly, forcing reset...');
                cartTotal.textContent = '$0.00';
                cartTotal.innerHTML = '$0.00';
            }
        }
    }, 100);}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // F1 - Focus search
    if (e.key === 'F1') {
        e.preventDefault();
        document.getElementById('skuSearch').focus();
    }
    
    // F2 - Show all items
    if (e.key === 'F2') {
        e.preventDefault();
        showAllItems();
        document.getElementById('skuSearch').value = '';
    }
    
    // F9 - Checkout
    if (e.key === 'F9') {
        e.preventDefault();
        if (!document.getElementById('checkoutBtn').disabled) {
            processCheckout();
        }
    }
    
    // Escape - Exit
    if (e.key === 'Escape') {
        showPOSConfirm(
            'Exit POS',
            'Are you sure you want to exit the Point of Sale system?',
            'Yes, Exit',
            'Stay Here'
        ).then(confirmed => {
            if (confirmed) {
                window.location.href = '/?page=admin';
            }
        });
    }
});</script>

<<<<<<< HEAD
</div> <!-- Close pos-register --> 
=======
</div> <!- Close pos-register -> 
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
