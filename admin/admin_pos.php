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
 
<?php // Admin POS script is loaded via app.js per-page imports ?>
 
 </div> <!- Close pos-register ->