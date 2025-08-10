<?php
// Admin POS - Point of Sale System
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/business_settings_helper.php';

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
// Business info for POS UI and receipt modal
$businessName   = BusinessSettings::getBusinessName();
$businessDomain = BusinessSettings::getBusinessDomain();
$businessUrl    = BusinessSettings::getSiteUrl('');
?>



<div class="pos-register">
    <!- Header ->
    <div class="pos-header">
        <h1 class="pos-title">🛒 <?php echo htmlspecialchars($businessName); ?> Point of Sale</h1>
        <div class="pos-header-buttons">
            <button class="pos-fullscreen-btn" data-action="toggle-fullscreen">
                📺 Full Screen
            </button>
            <button class="pos-exit-btn" data-action="exit-pos">
                ✖ Exit POS
            </button>
        </div>
    </div>

    <!- Main Content ->
    <div class="pos-main">
        <!- Left Panel - Search & Items ->
        <div class="pos-left-panel">
            <!- Search Section ->
            <div class="pos-search-section">
                <h2 class="pos-search-title">📦 Add Items</h2>
                <div class="pos-search-methods">
                    <input type="text" 
                           id="skuSearch" 
                           class="pos-search-input" 
                           placeholder="🔍 Scan or enter SKU..."
                           autofocus>
                    <button class="pos-browse-btn" data-action="browse-items">
                        🛍️ Browse Items
                    </button>
                </div>
            </div>

            <!- Items Grid ->
            <div class="pos-items-grid">
                <div class="items-grid" id="itemsGrid">
                    <!- Items will be populated here ->
                </div>
            </div>
        </div>

        <!- Right Panel - Cart ->
        <div class="pos-cart">
            <div class="cart-header">
                <h2 class="cart-title">🛒 Cart</h2>
            </div>
            
            <div class="cart-summary">
                <div class="cart-total">
                    <span>Total:</span>
                    <span id="posCartTotal">$0.00</span>
                </div>
                <button class="checkout-btn" id="checkoutBtn" data-action="checkout" disabled>
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

<!-- Data for JavaScript modules -->
<script type="application/json" id="pos-data">
    <?php echo json_encode($allItems); ?>
</script>
<script>
  // Brand config for POS UI and receipt templates
  window.POS_BRAND = {
    name: <?php echo json_encode($businessName); ?>,
    domain: <?php echo json_encode($businessDomain); ?>,
    url: <?php echo json_encode($businessUrl); ?>
  };
</script>
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
            <div >
                <div >
                    <div >${item.name}</div>
                    <div >SKU: ${item.sku}</div>
                    <div >${item.quantity} x $${item.price.toFixed(2)}</div>
                </div>
                <div >
                    $${itemTotal.toFixed(2)}
                </div>
            </div>
        `;
    });
    
    return `
        <div >
            <!- Receipt Header ->
            <div >
                <div >${(window.POS_BRAND && window.POS_BRAND.name) ? window.POS_BRAND.name : ''}</div>
                <div >Point of Sale Receipt</div>
            </div>
            
            <!- Transaction Info ->
            <div >
                <div><strong>Order ID:</strong> ${saleData.orderId}</div>
                <div><strong>Date:</strong> ${timestamp}</div>
                <div><strong>Cashier:</strong> POS System</div>
            </div>
            
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
                    <span>TOTAL:</span>
                    <span>$${saleData.total.toFixed(2)}</span>
                </div>
            </div>
            
            <!- Payment Info ->
            <div >
                <div >PAYMENT DETAILS</div>
                <div><strong>Method:</strong> ${saleData.paymentMethod}</div>
                ${saleData.paymentMethod === 'Cash' ? `
                    <div><strong>Cash Received:</strong> $${saleData.cashReceived.toFixed(2)}</div>
                    <div><strong>Change Due:</strong> $${saleData.changeAmount.toFixed(2)}</div>
                ` : ''}
            </div>
            
            <!- Footer ->
            <div >
                <div>Thank you for your business!</div>
                <div >Visit us online at <a href="${(window.POS_BRAND && window.POS_BRAND.url) ? window.POS_BRAND.url : '#'}" target="_blank" rel="noopener">${(window.POS_BRAND && (window.POS_BRAND.domain || window.POS_BRAND.url)) ? (window.POS_BRAND.domain || window.POS_BRAND.url) : ''}</a></div>
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

</div> <!- Close pos-register -> 