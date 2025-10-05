<?php
// This page is included by index.php routing system
// No need to include config or session - already handled by index.php
if (!defined('INCLUDED_FROM_INDEX')) {
    // Fallback for direct access - redirect through router
    header('Location: /receipt?' . $_SERVER['QUERY_STRING']);
    exit;
}

require_once __DIR__ . '/api/business_settings_helper.php';
// Optional tax lookup by ZIP (used for recomputation fallback)
@require_once __DIR__ . '/includes/tax_service.php';

$orderId = $_GET['orderId'] ?? '';
if ($orderId === '') {
    echo '<div class="text-center"><h1 class="text-brand-primary">Invalid order ID</h1></div>';
    return;
}

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
    Database::execute("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Get order details
    $order = Database::queryOne('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$order) {
        throw new Exception('Order not found');
    }

    // Get order items with details
    $orderItems = Database::queryAll("\n        SELECT \n            oi.sku,\n            oi.quantity,\n            oi.price,\n            i.name as itemName,\n            i.category,\n            i.retailPrice AS itemRetailPrice\n        FROM order_items oi\n        LEFT JOIN items i ON oi.sku = i.sku\n        WHERE oi.orderId = ?\n    ", [$orderId]);

    // Get receipt message based on context
    $receiptMessage = getReceiptMessage($order, $orderItems);

    // Get sales verbiage
    $salesVerbiage = getSalesVerbiage();

} catch (Exception $e) {
    echo '<div class="text-center"><h1 class="text-brand-primary">Error loading order</h1><p>'.htmlspecialchars($e->getMessage()).'</p></div>';
    return;
}

function getSalesVerbiage()
{
    try {
        $rows = Database::queryAll("
            SELECT setting_key, setting_value 
            FROM business_settings 
            WHERE category = 'sales' AND setting_key LIKE 'receipt_%'
            ORDER BY display_order
        ");
        $out = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
        return $out;
    } catch (Exception $e) {
        error_log("Error getting sales verbiage: " . $e->getMessage());
        return [];
    }
}

function getReceiptMessage($order, $orderItems)
{
    // Default message
    $defaultMessage = [
        'title' => 'Payment Received',
        'content' => 'Your order is being processed with care. You\'ll receive updates as your custom items are prepared and shipped.'
    ];

    try {
        // Ensure receipt_settings table exists
        $createTableSQL = "
            CREATE TABLE IF NOT EXISTS receipt_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_type ENUM('shipping_method', 'item_count', 'item_category', 'default') NOT NULL,
                condition_key VARCHAR(100) NOT NULL,
                condition_value VARCHAR(255) NOT NULL,
                message_title VARCHAR(255) NOT NULL,
                message_content TEXT NOT NULL,
                ai_generated BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_condition (setting_type, condition_key, condition_value)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        Database::execute($createTableSQL);

        // Priority order: shipping method > item category > item count > default

        // 1. Check for shipping method specific message
        if (!empty($order['shippingMethod'])) {
            $result = Database::queryOne("
                SELECT message_title, message_content 
                FROM receipt_settings 
                WHERE setting_type = 'shipping_method' 
                AND condition_key = 'method' 
                AND condition_value = ?
                LIMIT 1
            ", [$order['shippingMethod']]);

            if ($result) {
                return [
                    'title' => $result['message_title'],
                    'content' => $result['message_content']
                ];
            }
        }

        // 2. Check for item category specific message (if single category dominates)
        if (!empty($orderItems)) {
            $categories = array_column($orderItems, 'category');
            $categories = array_filter($categories); // Remove nulls

            if (!empty($categories)) {
                $categoryCounts = array_count_values($categories);
                $dominantCategory = array_keys($categoryCounts, max($categoryCounts))[0];

                $result = Database::queryOne("
                    SELECT message_title, message_content 
                    FROM receipt_settings 
                    WHERE setting_type = 'item_category' 
                    AND condition_key = 'category' 
                    AND condition_value = ?
                    LIMIT 1
                ", [$dominantCategory]);

                if ($result) {
                    return [
                        'title' => $result['message_title'],
                        'content' => $result['message_content']
                    ];
                }
            }
        }

        // 3. Check for item count specific message
        $itemCount = count($orderItems);
        $countCondition = $itemCount === 1 ? '1' : 'multiple';

        $result = Database::queryOne("
            SELECT message_title, message_content 
            FROM receipt_settings 
            WHERE setting_type = 'item_count' 
            AND condition_key = 'count' 
            AND condition_value = ?
            LIMIT 1
        ", [$countCondition]);

        if ($result) {
            return [
                'title' => $result['message_title'],
                'content' => $result['message_content']
            ];
        }

        // 4. Check for default message
        $result = Database::queryOne("
            SELECT message_title, message_content 
            FROM receipt_settings 
            WHERE setting_type = 'default' 
            LIMIT 1
        ");

        if ($result) {
            return [
                'title' => $result['message_title'],
                'content' => $result['message_content']
            ];
        }

    } catch (Exception $e) {
        error_log("Error getting receipt message: " . $e->getMessage());
    }

    return $defaultMessage;
}

$orderTotalRaw = (float) ($order['total'] ?? 0);
// Prefer pre-tax base from items.retailPrice when available; fallback to stored order_items.price
$itemsSubtotal = 0.0;                 // base used for totals display
$__wf_line_subtotals = [];            // base used for allocation ratios and displayed ext price
foreach (($orderItems ?? []) as $idx => $__it) {
    $q = (float) ($__it['quantity'] ?? 0);
    $unitRetail = isset($__it['itemRetailPrice']) ? (float)$__it['itemRetailPrice'] : 0.0;
    $unitStored = (float) ($__it['price'] ?? 0);
    $unitBase = $unitRetail > 0 ? $unitRetail : $unitStored;
    $line = $q * $unitBase;
    $__wf_line_subtotals[$idx] = $line;
    $itemsSubtotal += $line;
}
$shippingAmount = (float) ($order['shippingAmount'] ?? $order['shipping'] ?? $order['shipping_cost'] ?? 0);
$taxAmount = (float) ($order['taxAmount'] ?? $order['tax'] ?? 0);

// If the order does not persist shipping/tax, recompute using BusinessSettings
if (abs($shippingAmount) < 0.00001 && abs($taxAmount) < 0.00001) {
    try {
        // Shipping method detection
        $shippingMethod = isset($order['shippingMethod']) && $order['shippingMethod'] !== ''
            ? (string)$order['shippingMethod']
            : 'USPS';

        // Recompute shipping based on subtotal and method
        $shipCfg = BusinessSettings::getShippingConfig(false);
        $freeThreshold    = (float)($shipCfg['free_shipping_threshold'] ?? 0);
        $localDeliveryFee = (float)($shipCfg['local_delivery_fee'] ?? 0);
        $rateUSPS         = (float)($shipCfg['shipping_rate_usps'] ?? 0);
        $rateFedEx        = (float)($shipCfg['shipping_rate_fedex'] ?? 0);
        $rateUPS          = (float)($shipCfg['shipping_rate_ups'] ?? 0);

        if ($shippingMethod === 'Customer Pickup') {
            $shippingAmount = 0.0;
        } elseif ($itemsSubtotal >= $freeThreshold && $freeThreshold > 0) {
            $shippingAmount = 0.0;
        } elseif ($shippingMethod === 'Local Delivery') {
            $shippingAmount = $localDeliveryFee;
        } elseif ($shippingMethod === 'USPS') {
            $shippingAmount = $rateUSPS;
        } elseif ($shippingMethod === 'FedEx') {
            $shippingAmount = $rateFedEx;
        } elseif ($shippingMethod === 'UPS') {
            $shippingAmount = $rateUPS;
        } else {
            $shippingAmount = $rateUSPS; // default
        }

        // Recompute tax (ZIP/state based if available)
        $taxCfg = BusinessSettings::getTaxConfig(false);
        $taxShipping = (bool)($taxCfg['taxShipping'] ?? false);
        $settingsEnabled = (bool)($taxCfg['enabled'] ?? false);
        $settingsRate = (float)($taxCfg['rate'] ?? 0);

        // Extract ZIP from orders.shippingAddress JSON if present
        $shippingAddress = null;
        if (!empty($order['shippingAddress'])) {
            $raw = $order['shippingAddress'];
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) $shippingAddress = $decoded;
            } elseif (is_array($raw)) {
                $shippingAddress = $raw;
            }
        }
        $zipForTax = '';
        if (is_array($shippingAddress)) {
            $zipForTax = trim((string)($shippingAddress['zip_code'] ?? $shippingAddress['postal_code'] ?? ''));
        }
        if ($zipForTax === '') {
            $zipForTax = (string) BusinessSettings::get('business_zip', '');
        }

        // Optional ZIP-based tax rate via TaxService (if available)
        $rateToUse = 0.0;
        if (class_exists('TaxService') && $zipForTax !== '') {
            try {
                $zipRate = (float) TaxService::getTaxRateForZip($zipForTax);
                if ($zipRate > 0) { $rateToUse = $zipRate; }
            } catch (\Throwable $e) { /* ignore */ }
        }
        if ($rateToUse <= 0 && $settingsEnabled && $settingsRate > 0) {
            $rateToUse = $settingsRate;
        }

        $taxBase = $itemsSubtotal + ($taxShipping ? $shippingAmount : 0.0);
        $taxAmount = ($rateToUse > 0) ? round($taxBase * $rateToUse, 2) : 0.0;
    } catch (\Throwable $e) {
        // Leave zeros on failure
    }
}
// Prefer explicit order total; otherwise compute
if ($orderTotalRaw <= 0.00001) {
    $orderTotalRaw = $itemsSubtotal + $shippingAmount + $taxAmount;
}
$total = number_format($orderTotalRaw, 2);
$subtotalFormatted = number_format($itemsSubtotal, 2);
$shippingFormatted = number_format($shippingAmount, 2);
$taxFormatted = number_format($taxAmount, 2);
$pending = ($order['paymentStatus'] === 'Pending');

// Final fallback: if both shipping and tax are zero but order total includes extra over items subtotal,
// allocate the difference to tax when tax is enabled; otherwise to shipping. This preserves correctness
// for legacy orders that did not persist a breakdown.
$__diff = $orderTotalRaw - $itemsSubtotal;
if ($__diff > 0.009 && abs($shippingAmount) < 0.00001 && abs($taxAmount) < 0.00001) {
    try {
        $taxCfg = BusinessSettings::getTaxConfig(false);
        $taxEnabled = (bool)($taxCfg['enabled'] ?? false);
    } catch (\Throwable $e) { $taxEnabled = false; }
    if ($taxEnabled) {
        $taxAmount = round($__diff, 2);
    } else {
        $shippingAmount = round($__diff, 2);
    }
    // Reformat labels and total for display
    $shippingFormatted = number_format($shippingAmount, 2);
    $taxFormatted = number_format($taxAmount, 2);
    $orderTotalRaw = $itemsSubtotal + $shippingAmount + $taxAmount;
    $total = number_format($orderTotalRaw, 2);
}

// Business info (centralized)
$businessName     = BusinessSettings::getBusinessName();
$businessDomain   = BusinessSettings::getBusinessDomain();
$businessOwner    = BusinessSettings::get('business_owner', '');
$businessPhone    = BusinessSettings::get('business_phone', '');
$businessAddress  = BusinessSettings::get('business_address', '');
$businessUrl      = BusinessSettings::getSiteUrl('');
$businessTagline  = BusinessSettings::get('business_tagline', 'Custom Crafts & Personalized Gifts');

// Emit brand font CSS variables for bare rendering (header.php is skipped when bare=1)
try {
    $biz = BusinessSettings::getByCategory('business');
    $vars = [];
    $sanitize = function ($v) { return trim((string)$v); };
    $quoteIfNeeded = function ($v) {
        $v = trim((string)$v);
        if ($v === '') return $v;
        // If already contains quotes or commas (multiple families), leave as-is
        if (strpbrk($v, "'\",") !== false) return $v;
        // If contains spaces, wrap in single quotes
        if (strpos($v, ' ') !== false) return "'" . $v . "'";
        return $v;
    };
    if (!empty($biz['business_brand_font_primary'])) {
        $vars[] = "--brand-font-primary: " . $quoteIfNeeded($sanitize($biz['business_brand_font_primary'])) . ';';
    }
    if (!empty($biz['business_brand_font_secondary'])) {
        $vars[] = "--brand-font-secondary: " . $quoteIfNeeded($sanitize($biz['business_brand_font_secondary'])) . ';';
    }
    if (!empty($vars)) {
        echo "<style id=\"wf-branding-vars-inline\">:root{\n" . implode("\n", $vars) . "\n}</style>\n";
    }
} catch (\Throwable $___e) { /* noop */ }
// In bare/embed/print modes, ensure brand webfonts are available by adding a dynamic Google Fonts link
try {
    $qs = $_GET ?? [];
    $isBareLike = (isset($qs['bare']) && $qs['bare'] === '1') || (isset($qs['embed']) && $qs['embed'] === '1') || (isset($qs['modal']) && $qs['modal'] === '1') || (isset($qs['print']) && $qs['print'] === '1');
    if ($isBareLike) {
        // Build Google Fonts URL from Business Settings
        $families = [];
        $addFamily = function($name) use (&$families) {
            $n = trim((string)$name);
            if ($n === '') return;
            // Strip wrapping quotes
            if ((substr($n,0,1)==="'" && substr($n,-1)==="'") || (substr($n,0,1)=='"' && substr($n,-1)=='"')) {
                $n = substr($n,1,-1);
            }
            // Collapse internal whitespace
            $n = preg_replace('/\s+/', ' ', $n);
            if (!in_array($n, $families, true)) $families[] = $n;
        };

        // Prefer configured fonts; fallback to Merienda (title) and Nunito (text)
        $addFamily($biz['business_brand_font_primary'] ?? 'Merienda');
        $addFamily($biz['business_brand_font_secondary'] ?? 'Nunito');

        // Compose Google Fonts CSS2 URL
        $weights = 'wght@400;600;700';
        $parts = [];
        foreach ($families as $fam) {
            // Google Fonts expects '+' for spaces; do NOT rawurlencode '+', it must remain '+'
            $gf = str_replace(' ', '+', $fam);
            $parts[] = 'family=' . $gf . ':' . $weights;
        }
        $href = 'https://fonts.googleapis.com/css2?' . implode('&', $parts) . '&display=swap';

        // Inject links only if fonts are not already available (guard against duplicates)
        $jsFamilies = json_encode($families, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);
        $jsHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        echo '<script>(function(){try{var fams=' . $jsFamilies . ';var need=false;if(document.fonts&&document.fonts.check){for(var i=0;i<fams.length;i++){var f="16px "+fams[i];if(!document.fonts.check(f)&&!document.fonts.check("700 "+f)){need=true;break;}}}else{need=true;}if(!need)return;if(document.querySelector(\'link[data-wf-fonts="1"]\'))return;var l1=document.createElement("link");l1.rel="preconnect";l1.href="https://fonts.googleapis.com";document.head.appendChild(l1);var l2=document.createElement("link");l2.rel="preconnect";l2.href="https://fonts.gstatic.com";l2.crossOrigin="anonymous";document.head.appendChild(l2);var l3=document.createElement("link");l3.rel="stylesheet";l3.href="' . $jsHref . '";l3.setAttribute("data-wf-fonts","1");document.head.appendChild(l3);}catch(e){}})();</script>' . "\n";
    }
} catch (\Throwable $e) { /* noop */ }
?>
<?php
// Build a remit address where the last two lines "City, ST" and "ZIP" are on the same line
// e.g., [street], [city, ST], [ZIP] -> [street], [city, ST ZIP]
$remitAddressRaw = trim((string)$businessAddress);
$remitAddressFormatted = $remitAddressRaw;
if ($remitAddressRaw !== '') {
    $lines = preg_split('/\r?\n+/', $remitAddressRaw);
    $count = is_array($lines) ? count($lines) : 0;
    if ($count >= 2) {
        $last = trim($lines[$count - 1]);
        $secondLast = trim($lines[$count - 2]);
        // If last line is a ZIP code, merge into the previous line
        if (preg_match('/^\d{5}(?:-\d{4})?$/', $last)) {
            $lines[$count - 2] = rtrim($secondLast . ' ' . $last);
            array_pop($lines);
        }
        $remitAddressFormatted = implode("\n", $lines);
    }
}
?>

<style>
@media print {
    * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
    .text-red-600 { color: #dc2626 !important; }
    .receipt-total, .receipt-total-label { color: #dc2626 !important; }
    .text-center { text-align: center !important; }
    .flex.justify-end { text-align: right !important; }
}
</style>

<style>
/* Receipt table alignment rules */
.receipt-total-label { color: #dc2626; font-weight: 700; }
.receipt-total { color: #dc2626; font-weight: 800; font-size: 1.25rem; }
.receipt-message-center { text-align: center; }
.brand-header-row { display: inline-flex; align-items: center; gap: 10px; white-space: nowrap; }
.brand-title { font-family: var(--brand-font-primary, 'Merienda', 'Nunito', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif) !important; }
.brand-tagline { font-family: var(--brand-font-primary, 'Nunito', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif) !important; }
.receipt-table {
    table-layout: fixed; /* makes colgroup widths authoritative */
}
.receipt-table th,
.receipt-table td {
    padding: 0.5rem 0.75rem; /* match th/td exactly */
    vertical-align: top;
}
.receipt-table th { font-weight: 600; text-align: left !important; }
.receipt-table th:nth-child(1),
.receipt-table td:nth-child(1) { text-align: left; }
.receipt-table th:nth-child(2),
.receipt-table td:nth-child(2) { text-align: left; }
.receipt-table th:nth-child(3),
.receipt-table td:nth-child(3) { text-align: center; }
.receipt-table th:nth-child(4),
.receipt-table td:nth-child(4) { text-align: right; }
.receipt-table th:nth-child(5),
.receipt-table td:nth-child(5) { text-align: right; }
/* Removed per-line tax/shipping columns; table now has 5 columns */

/* Totals area */
.receipt-totals {
    width: 100%;
    display: grid;
    grid-template-columns: 1fr auto; /* label on left gap, value right */
    gap: 6px 18px;
}
.receipt-totals .label { text-align: right; color: #374151; }
.receipt-totals .value { text-align: right; }
.receipt-totals .grand-total { color: #dc2626; font-size: 1.5rem; font-weight: 800; }

</style>

<!-- Receipt Page Container -->
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Simple Receipt Header with Company Info -->
    <div class="receipt-container card-standard bg-white shadow-lg rounded-lg p-6">
    <!-- Company Header -->
    <div class="text-center receipt-message-center">
        <div class="brand-header-row wf-brand-font">
            <img src="/images/logos/logo-whimsicalfrog.webp" alt="<?php echo htmlspecialchars($businessName); ?> Logo" class="header-logo" style="max-height: 3.5rem; width: auto;" 
                 data-fallback-src="/images/logos/logo-whimsicalfrog.png">
            <div>
                <h1 class="text-brand-primary wf-brand-font brand-title"><?php echo htmlspecialchars($businessName); ?></h1>
                <p class="text-brand-secondary wf-brand-font brand-tagline"><?php echo htmlspecialchars($businessTagline); ?></p>
            </div>

        </div>
        <div class="text-sm text-brand-secondary receipt-message-center">
            <?php if (!empty($businessPhone) || !empty($businessDomain)): ?>
                <p>
                    <?php if (!empty($businessPhone)): ?>
                        <a href="tel:<?php echo preg_replace('/[^0-9+]/', '', $businessPhone); ?>" class="link-brand"><?php echo htmlspecialchars($businessPhone); ?></a>
                    <?php endif; ?>
                    <?php if (!empty($businessPhone) && !empty($businessDomain)): ?> | <?php endif; ?>
                    <?php if (!empty($businessDomain)): ?>
                        <a href="<?php echo htmlspecialchars($businessUrl); ?>" target="_blank" rel="noopener" class="link-brand"><?php echo htmlspecialchars($businessDomain ?: $businessUrl); ?></a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Info -->
    <div class="text-center receipt-message-center">
        <h2 class="text-brand-primary wf-brand-font">Order Receipt</h2>
        <p class="text-sm text-brand-secondary">Order ID: <strong><?= htmlspecialchars($orderId) ?></strong></p>
        <p class="text-sm text-brand-secondary">Date: <?= date('M d, Y', strtotime($order['date'] ?? 'now')) ?></p>
    </div>

    <!-- Items Table -->
    <table class="receipt-table w-full text-sm border-collapse mt-6">
        <colgroup>
            <col style="width: 18%;">  <!-- Item ID -->
            <col style="width: 50%;">  <!-- Item Name -->
            <col style="width: 8%;">   <!-- Qty -->
            <col style="width: 12%;">  <!-- Unit Price -->
            <col style="width: 12%;">  <!-- Ext. Price -->
        </colgroup>
        <thead>
            <tr class="bg-brand-light border-b-2 border-gray-300">
                <th><span class="font-mono text-xs">Item ID</span></th>
                <th>Item</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Ext. Price</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orderItems as $i => $it):
                $qty = (float)($it['quantity'] ?? 0);
                $unitRetail = isset($it['itemRetailPrice']) ? (float)$it['itemRetailPrice'] : 0.0;
                $unitStored = (float)($it['price'] ?? 0);
                $unit = $unitRetail > 0 ? $unitRetail : $unitStored;
                $ext = $qty * $unit;
            ?>
                <tr class="border-b border-gray-200">
                    <td class="font-mono text-xs"><?= htmlspecialchars($it['sku'] ?? '') ?></td>
                    <td><?= htmlspecialchars($it['itemName'] ?? $it['sku'] ?? '') ?></td>
                    <td><?= (int)$qty ?></td>
                    <td>$<?= number_format($unit, 2) ?></td>
                    <td>$<?= number_format($ext, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"></td>
                <td class="text-right">Subtotal</td>
                <td class="text-right">$<?= $subtotalFormatted ?></td>
            </tr>
            <tr>
                <td colspan="3"></td>
                <td class="text-right">Shipping</td>
                <td class="text-right">$<?= $shippingFormatted ?></td>
            </tr>
            <tr>
                <td colspan="3"></td>
                <td class="text-right">Tax</td>
                <td class="text-right">$<?= $taxFormatted ?></td>
            </tr>
            <tr>
                <td colspan="3"></td>
                <td class="text-right receipt-total-label"><strong>Total</strong></td>
                <td class="text-right"><span class="receipt-total">$<?= $total ?></span></td>
            </tr>
        </tfoot>
    </table>

    <?php if ($pending): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 text-sm receipt-message-center" role="alert">
            <p class="font-bold text-brand-primary wf-brand-font">Thank you for choosing <?php echo htmlspecialchars($businessName); ?>!</p>
            <p>Your order is reserved and will be shipped as soon as we receive your payment&nbsp;🙂</p>
            <?php
                $remitName = !empty($businessOwner) ? $businessOwner : $businessName;
        ?>
            <p class="">Remit payment to:<br><strong><?php echo htmlspecialchars($remitName); ?></strong><?php if (!empty($remitAddressFormatted)): ?><br><?php echo nl2br(htmlspecialchars($remitAddressFormatted)); ?><?php endif; ?></p>
            <p class="">Please include your order&nbsp;ID on the memo line.<br>As soon as we record your payment we'll send a confirmation e-mail and get your items on their way.</p>
        </div>
    <?php else: ?>
        <div class="card-standard text-sm receipt-message-center" role="alert">
            <p class="font-bold text-brand-primary wf-brand-font"><?= htmlspecialchars($receiptMessage['title']) ?></p>
            <p class="text-brand-secondary wf-brand-font"><?= htmlspecialchars($receiptMessage['content']) ?></p>
        </div>
    <?php endif; ?>

    <!-- Sales Verbiage Section -->
    <?php if (!empty($salesVerbiage)): ?>
        <div class="border-t space-y-3">
            <?php if (!empty($salesVerbiage['receipt_thank_you_message'])): ?>
                <div class="card-standard receipt-message-center">
                    <p class="text-sm text-brand-primary">🎉 <?= htmlspecialchars($salesVerbiage['receipt_thank_you_message']) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($salesVerbiage['receipt_next_steps'])): ?>
                <div class="card-standard receipt-message-center">
                    <p class="text-sm text-brand-primary">📋 <?= htmlspecialchars($salesVerbiage['receipt_next_steps']) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($salesVerbiage['receipt_social_sharing'])): ?>
                <div class="card-standard receipt-message-center">
                    <p class="text-sm text-brand-primary">📱 <?= htmlspecialchars($salesVerbiage['receipt_social_sharing']) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($salesVerbiage['receipt_return_customer'])): ?>
                <div class="card-standard receipt-message-center">
                    <p class="text-sm text-brand-primary">🎨 <?= htmlspecialchars($salesVerbiage['receipt_return_customer']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    </div>
</div> 