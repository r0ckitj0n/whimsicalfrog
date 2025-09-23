<?php
/**
 * Standalone POS (Point of Sale) System
 * Clean interface without admin headers/navbar
 */

// Security and authentication
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/auth.php';

// Require admin authentication for POS access
if (!isLoggedIn()) {
    header('Location: /login?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get database instance and load items
$db = Database::getInstance();

try {
    // Get all items (both live and draft) with categories and options
    $stmt = $db->query(
        "SELECT sku, name, category, retailPrice, imageUrl, status,
                gender, color_options, size_options
         FROM items 
         ORDER BY category, name"
    );
    $allItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique categories
    $categories = [];
    foreach ($allItems as $item) {
        $cat = $item['category'] ?: 'Uncategorized';
        if (!in_array($cat, $categories)) {
            $categories[] = $cat;
        }
    }
    sort($categories);
} catch (Exception $e) {
    error_log('POS data loading failed: ' . $e->getMessage());
    $allItems = [];
    $categories = [];
}

// Business info
$businessName = 'WhimsicalFrog';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($businessName) ?> - Point of Sale</title>

    <?php
    // Since this is a standalone page, we need to manually include the Vite assets.
    // The main app.js or a specific entry point is required for functionality.
    if (function_exists('vite')) {
        vite('src/js/app.js'); // Load the main app which initializes all modules
    }
?>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }
        
        .pos-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        .pos-header {
            background: #2563eb;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .pos-title {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .pos-header-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .pos-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            background: rgba(255,255,255,0.2);
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        
        .pos-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .pos-main {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        .pos-left {
            flex: 2;
            padding: 1rem;
            overflow-y: auto;
            background: #f9fafb;
        }
        
        .pos-right {
            flex: 1;
            background: white;
            border-left: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
        }
        
        .search-section {
            background: white;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.375rem;
            font-size: 1rem;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #2563eb;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .item-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }
        
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-color: #2563eb;
        }
        
        .item-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            line-height: 1.3;
        }
        
        .item-price {
            color: #059669;
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        
        .item-sku {
            color: #6b7280;
            font-size: 0.8rem;
        }
        
        .cart-header {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .cart-title {
            font-size: 1.25rem;
            font-weight: bold;
        }
        
        .cart-items {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            background: #f9fafb;
            margin-bottom: 0.5rem;
            border-radius: 0.375rem;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .cart-item-price {
            color: #059669;
            font-weight: bold;
        }
        
        .cart-summary {
            padding: 1rem;
            border-top: 2px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .cart-total {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            text-align: center;
            color: #1f2937;
        }
        
        .checkout-btn {
            width: 100%;
            padding: 1rem;
            background: #059669;
            color: white;
            border: none;
            border-radius: 0.375rem;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .checkout-btn:hover:not(:disabled) {
            background: #047857;
        }
        
        .checkout-btn:disabled {
            background: #d1d5db;
            cursor: not-allowed;
        }
        
        .empty-cart {
            text-align: center;
            color: #6b7280;
            padding: 2rem;
            font-style: italic;
        }
        
        .remove-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .remove-btn:hover {
            background: #dc2626;
        }
        
        .option-btn {
            padding: 0.5rem 1rem;
            border: 2px solid #e5e7eb;
            background: white;
            border-radius: 0.25rem;
            cursor: pointer;
            margin: 0.25rem;
            transition: all 0.2s;
        }
        
        .option-btn:hover {
            border-color: #2563eb;
        }
        
        /* Branded Modal System */
        .wf-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .wf-modal {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 90%;
            border: 2px solid #87ac3a;
        }
        
        .wf-modal-header {
            background: linear-gradient(135deg, #87ac3a 0%, #6b8e23 100%);
            color: white;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .wf-modal-body {
            padding: 1.5rem;
        }
        
        .wf-modal-footer {
            padding: 1rem 1.5rem;
            background: #f9fafb;
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        
        .wf-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }
        
        .wf-btn-primary {
            background: #87ac3a;
            color: white;
        }
        
        .wf-btn-secondary {
            background: white;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }
    </style>
</head>
<body>
    <div class="pos-container pos-register"> <!-- Added .pos-register for JS module to hook into -->
        <div class="pos-header">
            <h1 class="pos-title">🛒 <?= htmlspecialchars($businessName) ?> POS</h1>
            <div class="pos-header-buttons">
                <a href="/sections/admin_router.php?section=dashboard" class="pos-btn">← Back to Admin</a>
                <button class="pos-btn" data-action="toggle-fullscreen">📺 Fullscreen</button>
            </div>
        </div>
        
        <div class="pos-main">
            <div class="pos-left">
                <div class="search-section">
                    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                        <select class="search-input" id="categoryFilter" style="flex: 1;">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="text" class="search-input" placeholder="🔍 Enter SKU (e.g., WF-TS-002) or search by name..." id="skuSearch">
                </div>
                
                <div class="items-grid" id="itemsGrid">
                    <?php if (empty($allItems)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: #6b7280;">
                            No items available. Please add items to your inventory.
                        </div>
                    <?php else: ?>
                        <?php foreach ($allItems as $item): ?>
                        <div class="item-card" 
                             data-item-sku="<?= htmlspecialchars($item['sku'], ENT_QUOTES) ?>"
                             data-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>"
                             data-item-price="<?= $item['retailPrice'] ?>"
                             data-name="<?= strtolower(htmlspecialchars($item['name'], ENT_QUOTES)) ?>" 
                             data-sku="<?= strtolower(htmlspecialchars($item['sku'], ENT_QUOTES)) ?>"
                             data-category="<?= htmlspecialchars($item['category'] ?: 'Uncategorized', ENT_QUOTES) ?>"
                             data-gender="<?= htmlspecialchars($item['gender'] ?: '', ENT_QUOTES) ?>"
                             data-colors="<?= htmlspecialchars($item['color_options'] ?: '', ENT_QUOTES) ?>"
                             data-sizes="<?= htmlspecialchars($item['size_options'] ?: '', ENT_QUOTES) ?>"
                             style="cursor: pointer;">
                            <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="item-price">$<?= number_format($item['retailPrice'], 2) ?></div>
                            <div class="item-sku"><?= htmlspecialchars($item['sku']) ?></div>
                            <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;">
                                <?= htmlspecialchars($item['category'] ?: 'Uncategorized') ?>
                                <?php if ($item['status'] === 'draft'): ?>
                                    <span style="color: #f59e0b;">• Draft</span>
                                <?php endif; ?>
                                <?php if ($item['color_options'] || $item['size_options'] || $item['gender']): ?>
                                    <span style="color: #2563eb;">• Options</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="pos-right">
                <div class="cart-header">
                    <h2 class="cart-title">🛒 Cart</h2>
                </div>
                
                <div class="cart-items" id="cartItems">
                    <div class="empty-cart">
                        Cart is empty<br>
                        <small>Click items to add them to cart</small>
                    </div>
                </div>
                
                <div class="cart-summary">
                    <div class="cart-total">
                        Total: <span id="cartTotal">$0.00</span>
                    </div>
                    <button class="checkout-btn" id="checkoutBtn" data-action="checkout" disabled>
                        💳 Complete Sale
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Options Modal -->
    <div id="optionsModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 0.5rem; padding: 1.5rem; max-width: 400px; width: 90%; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <h3 style="margin: 0 0 1rem 0; font-size: 1.1rem; font-weight: 600;">Select Options</h3>
            
            <div id="optionsContent">
                <!-- Options will be populated here -->
            </div>
            
            <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                <button data-action="cancel-options" style="flex: 1; padding: 0.75rem; border: 1px solid #d1d5db; background: white; border-radius: 0.375rem; cursor: pointer;">Cancel</button>
                <button data-action="confirm-options" style="flex: 1; padding: 0.75rem; background: #2563eb; color: white; border: none; border-radius: 0.375rem; cursor: pointer; font-weight: 500;">Add to Cart</button>
            </div>
        </div>
    </div>

    <!-- Branded Confirmation Modal -->
    <div id="wfModal" class="wf-modal-overlay">
        <div class="wf-modal">
            <div class="wf-modal-header">
                <span id="wfModalIcon">🛒</span>
                <h3 id="wfModalTitle">Confirm Sale</h3>
            </div>
            <div class="wf-modal-body">
                <p id="wfModalMessage">Complete this sale?</p>
            </div>
            <div class="wf-modal-footer">
                <button class="wf-btn wf-btn-secondary" data-action="close-wf-modal">Cancel</button>
                <button class="wf-btn wf-btn-primary" data-action="confirm-wf-modal">Complete Sale</button>
            </div>
        </div>
    </div>

    <!-- Data bridge for the POS module -->
    <script id="pos-data" type="application/json">
        <?= json_encode($allItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>
    </script>

    </body>
</html>
