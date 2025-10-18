<?php
/**
 * Standalone POS (Point of Sale) System
 * Clean interface without admin headers/navbar
 */

// Security and authentication
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/auth_helper.php';

// Require admin access for POS
if (!AuthHelper::isAdmin()) {
    // Redirect to login; after login, only admins will be allowed through
    header('Location: /login?redirect_to=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Build POS items using the exact same loader as Shop for perfect alignment
try {
    require_once __DIR__ . '/includes/shop_data_loader.php'; // populates $categories
    require_once __DIR__ . '/includes/image_helper.php';     // getPrimaryImageBySku()
    require_once __DIR__ . '/includes/stock_manager.php';    // getStockLevel()

    $allItems = [];
    if (!empty($categories) && is_array($categories)) {
        foreach ($categories as $slug => $catData) {
            $catLabel = isset($catData['label']) ? $catData['label'] : ($slug ?: 'Uncategorized');
            $products = isset($catData['products']) && is_array($catData['products']) ? $catData['products'] : [];
            foreach ($products as $p) {
                $sku = $p['sku'] ?? '';
                if ($sku === '') continue;
                $name = $p['productName'] ?? ($p['name'] ?? $sku);
                $price = isset($p['price']) ? (float)$p['price'] : 0.0;
                // Compute an aggregate stock from sizes/colors; use Shop's stock for parity when present
                $totalStock = getStockLevel($pdo, $sku);
                $stock = isset($p['stock']) ? (int)$p['stock'] : $totalStock;
                $primary = getPrimaryImageBySku($sku);
                $imagePath = $primary && isset($primary['image_path']) ? $primary['image_path'] : null;
                $allItems[] = [
                    'sku'         => $sku,
                    'name'        => $name,
                    'category'    => $catLabel,
                    'retailPrice' => $price,
                    'stock'       => $stock,
                    'stockLevel'  => $stock,
                    'imageUrl'    => $imagePath,
                    'totalStock'  => (int)$totalStock,
                    // Optional attributes for options/labels; provide safe defaults
                    'gender'       => isset($p['gender']) ? (string)$p['gender'] : '',
                    'color_options'=> isset($p['color_options']) ? (string)$p['color_options'] : '',
                    'size_options' => isset($p['size_options']) ? (string)$p['size_options'] : '',
                    'status'       => isset($p['status']) ? (string)$p['status'] : '',
                ];
            }
        }
    }

    // Extract unique category labels for the filter dropdown
    $catSet = [];
    foreach ($allItems as $item) {
        $cat = $item['category'] ?: 'Uncategorized';
        $catSet[$cat] = true;
    }
    $categories = array_keys($catSet);
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
    // Since this is a standalone page, include the dedicated POS Vite entry.
    // This entry imports POS-specific CSS and the shared detailed item modal stack.
    if (function_exists('vite_entry')) {
        echo vite_entry('src/entries/pos.js');
    } elseif (function_exists('vite')) {
        // Backward compatibility: some templates still call vite().
        echo vite('src/entries/pos.js');
    }
    // Inject brand CSS variables (same approach as header) so POS uses Business Settings
    try {
        require_once __DIR__ . '/api/business_settings_helper.php';
        $biz = BusinessSettings::getByCategory('business');
        $vars = [];
        $san = function ($v) { return trim((string)$v); };
        if (!empty($biz['business_brand_primary']))   { $vars[] = "--brand-primary: "   . $san($biz['business_brand_primary'])   . ';'; }
        if (!empty($biz['business_brand_secondary'])) { $vars[] = "--brand-secondary: " . $san($biz['business_brand_secondary']) . ';'; }
        if (!empty($biz['business_brand_accent']))    { $vars[] = "--brand-accent: "    . $san($biz['business_brand_accent'])    . ';'; }
        if (!empty($biz['business_brand_background'])){ $vars[] = "--brand-bg: "        . $san($biz['business_brand_background']). ';'; }
        if (!empty($biz['business_brand_text']))      { $vars[] = "--brand-text: "      . $san($biz['business_brand_text'])      . ';'; }
        if (!empty($biz['business_brand_font_primary']))   { $vars[] = "--brand-font-primary: "   . $san($biz['business_brand_font_primary'])   . ';'; }
        if (!empty($biz['business_brand_font_secondary'])) { $vars[] = "--brand-font-secondary: " . $san($biz['business_brand_font_secondary']) . ';'; }
        if (!empty($vars)) {
            echo "<style id=\"wf-branding-vars-pos\">:root\n{" . implode("\n", $vars) . "\n}</style>\n";
        }
    } catch (Throwable $e) { /* noop */ }
?>
</head>
<body class="pos-body">
    <div class="pos-container pos-register"> <!-- Added .pos-register for JS module to hook into -->
        <div class="pos-header">
            <h1 class="pos-title">🛒 <?= htmlspecialchars($businessName) ?> POS</h1>
            <div class="pos-header-buttons">
                <a href="/sections/admin_router.php?section=dashboard" class="btn btn-secondary btn-sm">← Back to Admin</a>
                <button class="btn btn-secondary btn-sm" data-action="toggle-fullscreen">📺 Fullscreen</button>
            </div>
        </div>
        
        <div class="pos-main">
            <div class="pos-left">
                <div class="search-section">
                    <div class="pos-search-row">
                        <select class="search-input pos-flex-1" id="categoryFilter">
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
                        <div class="pos-empty-list">
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
                             data-sizes="<?= htmlspecialchars($item['size_options'] ?: '', ENT_QUOTES) ?>">
                            <div class="item-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="item-price">$<?= number_format($item['retailPrice'], 2) ?></div>
                            <div class="item-sku"><?= htmlspecialchars($item['sku']) ?></div>
                            <div class="item-meta">
                                <?= htmlspecialchars($item['category'] ?: 'Uncategorized') ?>
                                <?php if ($item['status'] === 'draft'): ?>
                                    <span class="pos-badge-draft">• Draft</span>
                                <?php endif; ?>
                                <?php if ($item['color_options'] || $item['size_options'] || $item['gender']): ?>
                                    <span class="pos-badge-options">• Options</span>
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
    <div id="optionsModal" class="pos-options-overlay">
        <div class="pos-options-modal">
            <h3 class="pos-options-title">Select Options</h3>
            
            <div id="optionsContent">
                <!-- Options will be populated here -->
            </div>
            
            <div class="pos-options-actions">
                <button data-action="cancel-options" class="btn btn-secondary btn-sm">Cancel</button>
                <button data-action="confirm-options" class="btn btn-primary btn-sm">Add to Cart</button>
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
                <button class="btn btn-secondary" data-action="close-wf-modal">Cancel</button>
                <button class="btn btn-primary" data-action="confirm-wf-modal">Complete Sale</button>
            </div>
        </div>
    </div>

    <!-- Data bridge for the POS module -->
    <script id="pos-data" type="application/json">
        <?= json_encode($allItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>
    </script>

    </body>
</html>
