<?php
if (!defined('INCLUDED_FROM_INDEX')) {
    // allow standalone access as fallback
    define('INCLUDED_FROM_INDEX', true);
}
require_once 'api/config.php';
require_once __DIR__ . '/api/business_settings_helper.php';

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
    $orderItems = Database::queryAll("
        SELECT 
            oi.sku,
            oi.quantity,
            oi.price,
            i.name as itemName,
            i.category
        FROM order_items oi
        LEFT JOIN items i ON oi.sku = i.sku
        WHERE oi.orderId = ?
    ", [$orderId]);

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
        foreach ($rows as $r) { $out[$r['setting_key']] = $r['setting_value']; }
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

$total = number_format($order['total'] ?? 0, 2);
$pending = ($order['paymentStatus'] === 'Pending');

// Business info (centralized)
$businessName     = BusinessSettings::getBusinessName();
$businessDomain   = BusinessSettings::getBusinessDomain();
$businessOwner    = BusinessSettings::get('business_owner', '');
$businessPhone    = BusinessSettings::get('business_phone', '');
$businessAddress  = BusinessSettings::get('business_address', '');
$businessUrl      = BusinessSettings::getSiteUrl('');
$businessTagline  = BusinessSettings::get('business_tagline', 'Custom Crafts & Personalized Gifts');
?>


<!-- Simple Receipt Header with Company Info -->
<div class="receipt-container card-standard">
    <!-- Company Header -->
    <div class="text-center">
        <div class="flex justify-center items-center">
            <img src="images/logos/logo_whimsicalfrog.webp" alt="<?php echo htmlspecialchars($businessName); ?> Logo" class="header-logo" 
                 data-fallback-src="/images/logos/logo_whimsicalfrog.png">
            <div>
                <h1 class="text-brand-primary wf-brand-font"><?php echo htmlspecialchars($businessName); ?></h1>
                <p class="text-brand-secondary wf-brand-font"><?php echo htmlspecialchars($businessTagline); ?></p>
            </div>
        </div>
        <div class="text-sm text-brand-secondary">
            <?php if (!empty($businessOwner)): ?>
                <p><strong>Owner:</strong> <?php echo htmlspecialchars($businessOwner); ?></p>
            <?php endif; ?>
            <?php if (!empty($businessAddress)): ?>
                <p><?php echo htmlspecialchars($businessAddress); ?></p>
            <?php endif; ?>
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
    <div class="text-center">
        <h2 class="text-brand-primary wf-brand-font">Order Receipt</h2>
        <p class="text-sm text-brand-secondary">Order ID: <strong><?= htmlspecialchars($orderId) ?></strong></p>
        <p class="text-sm text-brand-secondary">Date: <?= date('M d, Y', strtotime($order['date'] ?? 'now')) ?></p>
    </div>

    <!-- Items Table -->
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-brand-light">
                <th class="text-left">Item ID</th>
                <th class="text-left">Item</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Price</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orderItems as $it): ?>
                <tr>
                    <td class="font-mono text-xs"><?= htmlspecialchars($it['sku'] ?? '') ?></td>
                    <td><?= htmlspecialchars($it['itemName'] ?? $it['sku'] ?? '') ?></td>
                    <td class="text-center"><?= $it['quantity'] ?? 0 ?></td>
                    <td class="text-right">$<?= number_format($it['price'] ?? 0, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="flex justify-end text-base font-semibold">
        <span>Total:&nbsp;$<?= $total ?></span>
    </div>

    <?php if ($pending): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 text-sm" role="alert">
            <p class="font-bold text-brand-primary wf-brand-font">Thank you for choosing <?php echo htmlspecialchars($businessName); ?>!</p>
            <p>Your order is reserved and will be shipped as soon as we receive your payment&nbsp;🙂</p>
            <?php 
                $remitName = !empty($businessOwner) ? $businessOwner : $businessName; 
                $remitAddress = trim((string)$businessAddress);
            ?>
            <p class="">Remit payment to:<br><strong><?php echo htmlspecialchars($remitName); ?></strong><?php if ($remitAddress !== ''): ?><br><?php echo nl2br(htmlspecialchars($remitAddress)); ?><?php endif; ?></p>
            <p class="">Please include your order&nbsp;ID on the memo line. As soon as we record your payment we'll send a confirmation e-mail and get your items on their way.</p>
        </div>
    <?php else: ?>
        <div class="card-standard text-sm" role="alert">
            <p class="font-bold text-brand-primary wf-brand-font"><?= htmlspecialchars($receiptMessage['title']) ?></p>
            <p class="text-brand-secondary wf-brand-font"><?= htmlspecialchars($receiptMessage['content']) ?></p>
        </div>
    <?php endif; ?>

    <!-- Sales Verbiage Section -->
    <?php if (!empty($salesVerbiage)): ?>
        <div class="border-t space-y-3">
            <?php if (!empty($salesVerbiage['receipt_thank_you_message'])): ?>
                <div class="card-standard text-center">
                    <p class="text-sm text-brand-primary">🎉 <?= htmlspecialchars($salesVerbiage['receipt_thank_you_message']) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($salesVerbiage['receipt_next_steps'])): ?>
                <div class="card-standard">
                    <p class="text-sm text-brand-primary">📋 <?= htmlspecialchars($salesVerbiage['receipt_next_steps']) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($salesVerbiage['receipt_social_sharing'])): ?>
                <div class="card-standard text-center">
                    <p class="text-sm text-brand-primary">📱 <?= htmlspecialchars($salesVerbiage['receipt_social_sharing']) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($salesVerbiage['receipt_return_customer'])): ?>
                <div class="card-standard text-center">
                    <p class="text-sm text-brand-primary">🎨 <?= htmlspecialchars($salesVerbiage['receipt_return_customer']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="text-center">
                <button id="printBtn" class="btn-brand js-print-button">Print Receipt</button>
    </div>
</div> 