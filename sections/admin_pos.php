<?php
// Admin POS - Point of Sale System
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.php';

// Initialize database
try {
    $db = Database::getInstance();
    
    // Get all items for searching
    $stmt = $db->query("SELECT i.sku, i.name, i.retailPrice, COALESCE(img.image_path, i.imageUrl) as imageUrl FROM items i LEFT JOIN item_images img ON i.sku = img.sku AND img.is_primary = 1 ORDER BY i.name");
    $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    Logger::error('Admin POS data loading failed', ['error' => $e->getMessage()]);
    $allItems = [];
}
?>

<style>
/* Full-screen POS styling */
.pos-register {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--pos-main-bg, #c8e6c9);
    z-index: 1000;
    display: flex;
    flex-direction: column;
    font-family: var(--pos-font-family, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif);
    color: var(--pos-text-color, #333);
    font-weight: var(--pos-text-weight, 700);
    overflow-y: auto;
}

.pos-header {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    color: white;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.pos-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    color: #333;
}

.pos-header-buttons {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.pos-fullscreen-btn {
    background: var(--pos-fullscreen-bg, rgba(255, 255, 255, 0.1));
    color: var(--pos-button-text, #333);
    border: 2px solid var(--pos-fullscreen-border, rgba(255, 255, 255, 0.3));
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: var(--pos-text-weight, 700);
    transition: all 0.3s ease;
}

.pos-fullscreen-btn:hover {
    background: var(--pos-fullscreen-hover-bg, rgba(255, 255, 255, 0.2));
    transform: translateY(-2px);
}

.pos-exit-btn {
    background: var(--pos-exit-bg, #dc3545);
    color: var(--pos-exit-text, white);
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: var(--pos-text-weight, 700);
    transition: all 0.3s ease;
}

.pos-exit-btn:hover {
    background: var(--pos-exit-hover-bg, #c82333);
    transform: translateY(-2px);
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.pos-main {
    flex: 1;
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 2rem;
    padding: 2rem;
}

.pos-left-panel {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.pos-search-section {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.pos-search-title {
    color: var(--pos-text-color, #333);
    font-size: var(--pos-heading-size, 1.25rem);
    font-weight: var(--pos-text-weight, 700);
    margin: 0 0 1rem 0;
}

.pos-search-methods {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

.pos-search-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--pos-input-border, #666);
    border-radius: 8px;
    background: var(--pos-input-bg, rgba(255, 255, 255, 0.9));
    color: var(--pos-text-color, #333);
    font-size: 1rem;
    font-weight: var(--pos-text-weight, 700);
}

.pos-search-input::placeholder {
    color: var(--pos-placeholder-color, #666);
}

.pos-search-input:focus {
    outline: none;
    border-color: var(--pos-input-focus-border, #333);
    background: var(--pos-input-focus-bg, white);
}

.pos-browse-btn {
    background: rgba(76, 175, 80, 0.8);
    color: white;
    border: none;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.pos-browse-btn:hover {
    background: rgba(76, 175, 80, 1);
    transform: translateY(-2px);
}

.pos-items-grid {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    flex: 1;
}

.items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
}

.item-card {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
    pointer-events: auto;
    position: relative;
    z-index: 1;
}

.item-card:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.item-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 6px;
    margin: 0 auto 0.5rem;
    display: block;
    background: rgba(255, 255, 255, 0.1);
    pointer-events: none;
}

.item-name {
    color: var(--pos-text-color, #333);
    font-size: var(--pos-item-name-size, 0.875rem);
    font-weight: var(--pos-text-weight, 700);
    margin: 0.5rem 0;
    line-height: 1.3;
    pointer-events: none;
}

.item-sku {
    color: var(--pos-sku-color, #666);
    font-size: var(--pos-sku-size, 0.75rem);
    font-family: monospace;
    font-weight: var(--pos-text-weight, 700);
    margin: 0.25rem 0;
    pointer-events: none;
}

.item-price {
    color: var(--pos-price-color, #dc3545);
    font-size: var(--pos-price-size, 1.25rem);
    pointer-events: none;
    font-weight: var(--pos-price-weight, 700);
    margin: 0.5rem 0 0 0;
}

.pos-cart {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    min-height: 0;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.cart-header {
    background: transparent;
    color: #333;
    padding: 1rem;
    text-align: center;
}

.cart-title {
    margin: 0;
    font-size: var(--pos-heading-size, 1.25rem);
    font-weight: var(--pos-text-weight, 700);
    color: var(--pos-text-color, #333);
}

.cart-items {
    flex: 1;
    padding: 1rem;
}

.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #eee;
}

.cart-item:last-child {
    border-bottom: none;
}

.cart-item-info {
    flex: 1;
}

.cart-item-name {
    font-weight: 500;
    color: #333;
    margin: 0 0 0.25rem 0;
    font-size: 0.875rem;
}

.cart-item-sku {
    color: #666;
    font-size: 0.75rem;
    font-family: monospace;
}

.cart-item-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.qty-btn {
    background: transparent;
    border: 1px solid #ddd;
    width: 30px;
    height: 30px;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
}

.qty-btn:hover {
    background: rgba(255, 255, 255, 0.1);
}

.qty-display {
    min-width: 40px;
    text-align: center;
    font-weight: 500;
}

.cart-item-price {
    color: #2196F3;
    font-weight: 600;
    margin-left: 0.5rem;
}

.cart-summary {
    background: #f8f9fa;
    padding: 1rem;
    border-top: 2px solid #2196F3;
}

.cart-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 2.5rem;
    font-weight: 700;
    color: #dc3545;
    margin-bottom: 1rem;
    border: none;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
    background: rgba(255,255,255,0.1);
    padding: 1rem;
    border-radius: 8px;
}

.cart-total span {
    color: #dc3545;
    border: none;
    font-size: 2.5rem;
    font-weight: 700;
    text-shadow: 3px 3px 6px rgba(0,0,0,0.3);
}

/* Ensure cart total is always visible */
#posCartTotal {
    color: #dc3545 !important;
    font-size: 2.5rem !important;
    font-weight: 700 !important;
    text-shadow: 3px 3px 6px rgba(0,0,0,0.3) !important;
    display: inline !important;
    visibility: visible !important;
}

.checkout-btn {
    width: 100%;
    background: #4CAF50;
    color: white;
    border: none;
    padding: 1rem;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.checkout-btn:hover {
    background: #45a049;
    transform: translateY(-2px);
}

.checkout-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

.empty-cart {
    text-align: center;
    color: #666;
    padding: 2rem;
    font-style: italic;
}

.cart-item {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cart-item-info {
    flex: 1;
}

.cart-item-name {
    font-size: 0.875rem;
    font-weight: 500;
    color: #333;
    margin-bottom: 0.25rem;
}

.cart-item-sku {
    font-size: 0.75rem;
    color: #666;
    font-family: monospace;
}

.cart-item-controls {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.qty-btn {
    background: transparent;
    color: #333;
    border: 1px solid #ddd;
    width: 30px;
    height: 30px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: bold;
}

.qty-btn:hover {
    background: rgba(255, 255, 255, 0.1);
}

.qty-display {
    background: #f5f5f5;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    min-width: 30px;
    text-align: center;
    font-weight: 500;
}

.cart-item-price {
    font-size: 1rem;
    font-weight: 600;
    color: #4CAF50;
    margin-left: 0.5rem;
}

/* POS Modal Styles */
.pos-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: posModalFadeIn 0.3s ease;
}

@keyframes posModalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.pos-modal-content {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    min-width: 400px;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    animation: posModalSlideIn 0.3s ease;
}

@keyframes posModalSlideIn {
    from { 
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to { 
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.pos-modal-header {
    padding: 1.5rem;
    color: white;
    font-weight: bold;
}

.pos-modal-body {
    padding: 2rem;
    font-size: 1.1rem;
    line-height: 1.5;
    color: #333;
    text-align: center;
}

.pos-modal-footer {
    padding: 1rem 2rem 2rem;
    text-align: center;
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.pos-modal-btn {
    padding: 0.75rem 2rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    min-width: 120px;
}

.pos-modal-btn-close {
    background: #4CAF50;
    color: white;
}

.pos-modal-btn-close:hover {
    background: #45a049;
    transform: translateY(-2px);
}

.pos-modal-btn-confirm {
    background: #4CAF50;
    color: white;
}

.pos-modal-btn-confirm:hover {
    background: #45a049;
    transform: translateY(-2px);
}

.pos-modal-btn-cancel {
    background: #f5f5f5;
    color: #333;
    border: 2px solid #ddd;
}

.pos-modal-btn-cancel:hover {
    background: #e0e0e0;
    transform: translateY(-2px);
}

/* Mobile responsive */
@media (max-width: 768px) {
    .pos-main {
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 1rem;
    }
    
    .pos-search-methods {
        grid-template-columns: 1fr;
    }
    
    .items-grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    }
    
    .pos-modal-content {
        min-width: 300px;
        margin: 1rem;
    }
    
    .pos-modal-footer {
        flex-direction: column;
    }
}
</style>

<div class="pos-register">
    <!-- Header -->
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

    <!-- Main Content -->
    <div class="pos-main">
        <!-- Left Panel - Search & Items -->
        <div class="pos-left-panel">
            <!-- Search Section -->
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

            <!-- Items Grid -->
            <div class="pos-items-grid">
                <div class="items-grid" id="itemsGrid">
                    <!-- Items will be populated here -->
                </div>
            </div>
        </div>

        <!-- Right Panel - Cart -->
        <div class="pos-cart">
            <div class="cart-header">
                <h2 class="cart-title">🛒 Cart</h2>
            </div>
            
            <div class="cart-summary">
                <div class="cart-total">
                    <span>Total:</span>
                    <span id="posCartTotal" style="color: #dc3545 !important; font-size: 2.5rem !important; font-weight: 700 !important; text-shadow: 3px 3px 6px rgba(0,0,0,0.3) !important; display: inline-block !important; visibility: visible !important; opacity: 1 !important; background: rgba(255,255,255,0.1) !important; padding: 4px 8px !important; border-radius: 4px !important; border: 2px solid #dc3545 !important;">$0.00</span>
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
        const response = await fetch('/api/global_css_rules.php?action=generate_css');
        const data = await response.json();
        
        if (data.success && data.css_content) {
            // Create and inject CSS
            const styleElement = document.createElement('style');
            styleElement.textContent = data.css_content;
            document.head.appendChild(styleElement);
            console.log('🎨 POS CSS variables loaded successfully');
        }
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
        document.documentElement.requestFullscreen().then(() => {
            console.log('🖥️ Entered fullscreen mode');
            updateFullscreenButton(true);
        }).catch(err => {
            console.error('Error entering fullscreen:', err);
            alert('Could not enter fullscreen mode');
        });
    } else {
        // Exit fullscreen
        document.exitFullscreen().then(() => {
            console.log('🖥️ Exited fullscreen mode');
            updateFullscreenButton(false);
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
document.addEventListener('DOMContentLoaded', function() {
    console.log('🛒 POS System initialized with', allItems.length, 'items ready for sale');
    
    showAllItems();
    
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
                alert('Item not found: ' + sku);
            }
        }
    });
    
    // Verify cards are clickable
    setTimeout(() => {
        const cardCount = document.querySelectorAll('.item-card').length;
        console.log(`✅ ${cardCount} clickable item cards ready for POS use`);
        
        // Debug cart elements
        const cartItems = document.getElementById('cartItems');
        const cartTotal = document.getElementById('posCartTotal');
        const checkoutBtn = document.getElementById('checkoutBtn');
        
        console.log('🔍 Cart element check:');
        console.log('  cartItems:', cartItems ? 'Found' : 'Missing');
        console.log('  cartTotal:', cartTotal ? 'Found' : 'Missing');
        console.log('  checkoutBtn:', checkoutBtn ? 'Found' : 'Missing');
        
        if (cartItems) {
            console.log('  cartItems HTML:', cartItems.innerHTML);
        }
        
        // Cart system ready
        console.log('✅ Cart system initialized and ready');
    }, 500);
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
    });
    
    console.log(`📦 Displaying ${allItems.length} items in POS grid`);
}

// Simple test cart display function
function simpleCartDisplay() {
    console.log('🧪 SIMPLE CART DISPLAY TEST');
    const cartItems = document.getElementById('cartItems');
    if (cartItems) {
        cartItems.innerHTML = '<div style="color: blue; padding: 1rem;">🧪 SIMPLE TEST: Cart has ' + cart.length + ' items</div>';
        console.log('🧪 Simple cart display successful');
    } else {
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
function addToCart(item) {
    if (!item) {
        console.error('addToCart: item is null or undefined');
        return;
    }
    
    console.log('🛒 Adding item to cart:', item);
    
    const existing = cart.find(cartItem => cartItem.sku === item.sku);
    
    if (existing) {
        existing.quantity += 1;
        console.log(`➕ ${item.name} quantity increased to ${existing.quantity}, item price: $${existing.price}`);
    } else {
        const parsedPrice = parseFloat(item.retailPrice || 0);
        console.log(`💰 Parsing price for ${item.name}: '${item.retailPrice}' -> ${parsedPrice}`);
        
        if (isNaN(parsedPrice) || parsedPrice === 0) {
            console.error('❌ Invalid price detected:', item.retailPrice, 'parsed as:', parsedPrice);
        }
        
        const newItem = {
            sku: item.sku,
            name: item.name,
            price: parsedPrice,
            quantity: 1
        };
        cart.push(newItem);
        console.log(`🛒 Added ${item.name} to cart - Price: $${parsedPrice} (from '${item.retailPrice}')`);
    }
    
    // Log current cart state before updating display
    // Calculate total with tax (matches checkout calculation)
    const TAX_RATE = 0.0825; // 8.25% sales tax
    const subtotal = cart.reduce((sum, cartItem) => sum + (cartItem.price * cartItem.quantity), 0);
    const taxAmount = subtotal * TAX_RATE;
    const currentTotal = subtotal + taxAmount;
    console.log('📊 Current cart state:', cart.length, 'items, calculated total: $' + currentTotal.toFixed(2));
    console.log('📊 Cart contents:', cart);
    
    updateCartDisplay();
    
    // Visual feedback
    const skuSearch = document.getElementById('skuSearch');
    if (skuSearch) {
        skuSearch.style.borderColor = '#4CAF50';
        setTimeout(() => {
            skuSearch.style.borderColor = 'rgba(255, 255, 255, 0.3)';
        }, 300);
    }
    
    // Additional visual feedback - flash the cart
    const cartElement = document.querySelector('.pos-cart');
    if (cartElement) {
        cartElement.style.backgroundColor = 'rgba(76, 175, 80, 0.2)';
        setTimeout(() => {
            cartElement.style.backgroundColor = '';
        }, 300);
    }
}

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
        checkoutBtn.disabled = true;
        console.log('📭 Cart cleared - empty state');
        return;
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
                    cartTotal.parentNode.replaceChild(newTotal, cartTotal);
                    console.log('🔄 Replaced cart total element as last resort');
                } else {
                    console.log('✅ Cart total update successful after retry:', finalDisplay);
                }
            }, 100);
        } else {
            console.log('✅ Cart total updated successfully on first try:', currentDisplay);
        }
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
    
    modal.innerHTML = `
        <div class="pos-modal-content">
            <div class="pos-modal-header" style="background: ${colorMap[type]};">
                <h3 style="margin: 0; color: white; display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.5rem;">${iconMap[type]}</span>
                    ${title}
                </h3>
            </div>
            <div class="pos-modal-body">
                ${message}
            </div>
            ${!autoClose ? `
                <div class="pos-modal-footer">
                    <button class="pos-modal-btn pos-modal-btn-close" onclick="hidePOSModal()">OK</button>
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
                <div class="pos-modal-header" style="background: #FF9800;">
                    <h3 style="margin: 0; color: white; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.5rem;">❓</span>
                        ${title}
                    </h3>
                </div>
                <div class="pos-modal-body">
                    ${message}
                </div>
                <div class="pos-modal-footer">
                    <button class="pos-modal-btn pos-modal-btn-cancel" onclick="resolvePOSConfirm(false)">${cancelText}</button>
                    <button class="pos-modal-btn pos-modal-btn-confirm" onclick="resolvePOSConfirm(true)">${confirmText}</button>
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
                            Total: $${total.toFixed(2)}
                        </div>
                    </div>
                    
                    <div class="payment-methods" style="display: grid; gap: 1rem; margin-bottom: 2rem;">
                        <button class="payment-method-btn" data-method="Cash" style="background: #4CAF50; color: white; border: none; padding: 1rem; border-radius: 8px; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; gap: 1rem;">
                            <span style="font-size: 1.5rem;">💵</span>
                            <div style="text-align: left;">
                                <div style="font-weight: bold;">Cash</div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">Includes change calculator</div>
                            </div>
                        </button>
                        
                        <button class="payment-method-btn" data-method="Credit Card" style="background: #2196F3; color: white; border: none; padding: 1rem; border-radius: 8px; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; gap: 1rem;">
                            <span style="font-size: 1.5rem;">💳</span>
                            <div style="text-align: left;">
                                <div style="font-weight: bold;">Credit Card</div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">Visa, MasterCard, etc.</div>
                            </div>
                        </button>
                        
                        <button class="payment-method-btn" data-method="Debit Card" style="background: #FF9800; color: white; border: none; padding: 1rem; border-radius: 8px; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; gap: 1rem;">
                            <span style="font-size: 1.5rem;">💳</span>
                            <div style="text-align: left;">
                                <div style="font-weight: bold;">Debit Card</div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">PIN required</div>
                            </div>
                        </button>
                        
                        <button class="payment-method-btn" data-method="Check" style="background: #9C27B0; color: white; border: none; padding: 1rem; border-radius: 8px; font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; gap: 1rem;">
                            <span style="font-size: 1.5rem;">📝</span>
                            <div style="text-align: left;">
                                <div style="font-weight: bold;">Check</div>
                                <div style="font-size: 0.9rem; opacity: 0.9;">Personal or business</div>
                            </div>
                        </button>
                    </div>
                </div>
                <div class="pos-modal-footer">
                    <button class="pos-modal-btn pos-modal-btn-cancel" onclick="resolvePaymentMethod(null)">Cancel</button>
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
                        ⚠️ Insufficient funds - please collect more cash
                    </div>
                </div>
                <div class="pos-modal-footer">
                    <button class="pos-modal-btn pos-modal-btn-cancel" onclick="resolveCashCalculator(null)">Cancel</button>
                    <button id="acceptCashBtn" class="pos-modal-btn pos-modal-btn-confirm" onclick="acceptCashPayment(${total})" disabled>Accept Payment</button>
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
                style="background: #f0f0f0; border: none; padding: 0.75rem; border-radius: 6px; cursor: pointer; font-size: 1rem; ${suggestion.style || ''}"
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
                    <span>Total:</span>
                    <span>$${total.toFixed(2)}</span>
                </div>
            </div>
        </div>
        
        <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px;">
            <div style="font-weight: bold; margin-bottom: 0.5rem;">Payment Details:</div>
            <div><strong>Method:</strong> ${paymentMethod}</div>`;
    
    if (paymentMethod === 'Cash') {
        confirmMessage += `
            <div><strong>Cash Received:</strong> $${cashReceived.toFixed(2)}</div>
            <div style="font-weight: bold; color: #4CAF50;"><strong>Change Due:</strong> $${changeAmount.toFixed(2)}</div>`;
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
        };
        
        console.log('Processing checkout:', orderData);
        
        const response = await fetch('/api/add-order.php', {
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
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; padding: 0.25rem 0; border-bottom: 1px dotted #ccc;">
                <div style="flex: 1;">
                    <div style="font-weight: bold;">${item.name}</div>
                    <div style="font-size: 0.8rem; color: #666;">SKU: ${item.sku}</div>
                    <div style="font-size: 0.8rem; color: #666;">${item.quantity} x $${item.price.toFixed(2)}</div>
                </div>
                <div style="font-weight: bold; text-align: right;">
                    $${itemTotal.toFixed(2)}
                </div>
            </div>
        `;
    });
    
    return `
        <div style="background: white; padding: 2rem; font-family: 'Courier New', monospace; font-size: 0.9rem; line-height: 1.4;">
            <!-- Receipt Header -->
            <div style="text-align: center; margin-bottom: 2rem; border-bottom: 2px solid #333; padding-bottom: 1rem;">
                <div style="font-size: 1.4rem; font-weight: bold; margin-bottom: 0.5rem;">WHIMSICALFROG</div>
                <div style="font-size: 0.9rem;">Point of Sale Receipt</div>
            </div>
            
            <!-- Transaction Info -->
            <div style="margin-bottom: 1.5rem; font-size: 0.8rem;">
                <div><strong>Order ID:</strong> ${saleData.orderId}</div>
                <div><strong>Date:</strong> ${timestamp}</div>
                <div><strong>Cashier:</strong> POS System</div>
            </div>
            
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
                    <span>TOTAL:</span>
                    <span>$${saleData.total.toFixed(2)}</span>
                </div>
            </div>
            
            <!-- Payment Info -->
            <div style="margin-bottom: 1.5rem; border-top: 1px solid #ccc; padding-top: 1rem;">
                <div style="font-weight: bold; margin-bottom: 0.5rem;">PAYMENT DETAILS</div>
                <div><strong>Method:</strong> ${saleData.paymentMethod}</div>
                ${saleData.paymentMethod === 'Cash' ? `
                    <div><strong>Cash Received:</strong> $${saleData.cashReceived.toFixed(2)}</div>
                    <div><strong>Change Due:</strong> $${saleData.changeAmount.toFixed(2)}</div>
                ` : ''}
            </div>
            
            <!-- Footer -->
            <div style="text-align: center; font-size: 0.8rem; color: #666; border-top: 1px dotted #ccc; padding-top: 1rem;">
                <div>Thank you for your business!</div>
                <div style="margin-top: 0.5rem;">Visit us online at WhimsicalFrog.com</div>
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
            <style>
                body { margin: 0; padding: 0; background: white; }
                @media print {
                    body { margin: 0; }
                }
            </style>
        </head>
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
    
    // Clear cart and reset POS
    console.log('🧹 Clearing cart after successful checkout...');
    cart = [];
    updateCartDisplay();
    document.getElementById('skuSearch').value = '';
    showAllItems();
    
    // Verify cart total was reset correctly
    setTimeout(() => {
        const cartTotal = document.getElementById('posCartTotal');
        if (cartTotal) {
            const currentTotal = cartTotal.textContent || cartTotal.innerHTML;
            console.log('✅ Cart total after clear:', currentTotal);
            if (currentTotal !== '$0.00') {
                console.warn('⚠️ Cart total not reset properly, forcing reset...');
                cartTotal.textContent = '$0.00';
                cartTotal.innerHTML = '$0.00';
            }
        }
    }, 100);
    
    console.log('🧹 Sale completed and POS reset');
}

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
});

console.log('POS loaded successfully');
console.log('Keyboard shortcuts: F1=Search, F2=Show All, F9=Checkout, Escape=Exit');
</script>

</div> <!-- Close pos-register --> 