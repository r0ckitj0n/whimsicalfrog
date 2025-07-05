
<!-- Database-driven CSS for receipt -->
<style id="receipt-css">
/* CSS will be loaded from database */
</style>
<script>
    // Load CSS from database
    async function loadReceiptCSS() {
        try {
            const response = await fetch('/api/css_generator.php?category=receipt');
            const cssText = await response.text();
            const styleElement = document.getElementById('receipt-css');
            if (styleElement && cssText) {
                styleElement.textContent = cssText;
                console.log('✅ receipt CSS loaded from database');
            }
        } catch (error) {
            console.error('❌ FATAL: Failed to load receipt CSS:', error);
                // Show error to user - no fallback
                const errorDiv = document.createElement('div');
                errorDiv.innerHTML = `
                    <div style="position: fixed; top: 20px; right: 20px; background: #dc2626; color: white; padding: 12px; border-radius: 8px; z-index: 9999; max-width: 300px;">
                        <strong>receipt CSS Loading Error</strong><br>
                        Database connection failed. Please refresh the page.
                    </div>
                `;
                document.body.appendChild(errorDiv);
        }
    }
    
    // Load CSS when DOM is ready
    document.addEventListener('DOMContentLoaded', loadReceiptCSS);
</script>

<?php
if (!defined('INCLUDED_FROM_INDEX')) {
    // allow standalone access as fallback
    define('INCLUDED_FROM_INDEX', true);
}
require_once 'api/config.php';

$orderId = $_GET['orderId'] ?? '';
if ($orderId === '') {
    echo '<div class="text-center py-12"><h1 class="text-2xl font-bold text-red-600">Invalid order ID</h1></div>';
    return;
}

try {
    try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }
    $pdo->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Get order details
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found');
    
    // Get order items with details
    $itemsStmt = $pdo->prepare("
        SELECT 
            oi.sku,
            oi.quantity,
            oi.price,
            i.name as itemName,
            i.category
        FROM order_items oi
        LEFT JOIN items i ON oi.sku = i.sku
        WHERE oi.orderId = ?
    ");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get receipt message based on context
    $receiptMessage = getReceiptMessage($pdo, $order, $orderItems);
    
    // Get sales verbiage
    $salesVerbiage = getSalesVerbiage($pdo);
    
} catch (Exception $e) {
    echo '<div class="text-center py-12"><h1 class="text-2xl font-bold text-red-600">Error loading order</h1><p>'.htmlspecialchars($e->getMessage()).'</p></div>';
    return;
}

function getSalesVerbiage($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT setting_key, setting_value 
            FROM business_settings 
            WHERE category = 'sales' AND setting_key LIKE 'receipt_%'
            ORDER BY display_order
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        error_log("Error getting sales verbiage: " . $e->getMessage());
        return [];
    }
}

function getReceiptMessage($pdo, $order, $orderItems) {
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
        $pdo->exec($createTableSQL);
        
        // Priority order: shipping method > item category > item count > default
        
        // 1. Check for shipping method specific message
        if (!empty($order['shippingMethod'])) {
            $stmt = $pdo->prepare("
                SELECT message_title, message_content 
                FROM receipt_settings 
                WHERE setting_type = 'shipping_method' 
                AND condition_key = 'method' 
                AND condition_value = ?
                LIMIT 1
            ");
            $stmt->execute([$order['shippingMethod']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
                
                $stmt = $pdo->prepare("
                    SELECT message_title, message_content 
                    FROM receipt_settings 
                    WHERE setting_type = 'item_category' 
                    AND condition_key = 'category' 
                    AND condition_value = ?
                    LIMIT 1
                ");
                $stmt->execute([$dominantCategory]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
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
        
        $stmt = $pdo->prepare("
            SELECT message_title, message_content 
            FROM receipt_settings 
            WHERE setting_type = 'item_count' 
            AND condition_key = 'count' 
            AND condition_value = ?
            LIMIT 1
        ");
        $stmt->execute([$countCondition]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'title' => $result['message_title'],
                'content' => $result['message_content']
            ];
        }
        
        // 4. Check for default message
        $stmt = $pdo->prepare("
            SELECT message_title, message_content 
            FROM receipt_settings 
            WHERE setting_type = 'default' 
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
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
?>


<script>
// Centralized print receipt function using PrintUtils
function printReceipt() {
    if (window.PrintUtils && typeof window.PrintUtils.printReceipt === 'function') {
        // Use centralized PrintUtils for consistent functionality
        window.PrintUtils.printReceipt('<?= htmlspecialchars($orderId) ?>', <?= $order['total'] ?? 0 ?>);
    } else {
        console.warn('PrintUtils not available, falling back to basic print');
        window.print();
    }
}

// Initialize print functionality when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize centralized print system
    if (window.PrintUtils && typeof window.PrintUtils.initialize === 'function') {
        window.PrintUtils.initialize('receipt', '<?= htmlspecialchars($orderId) ?>');
        
        // Setup keyboard shortcuts using centralized system
        window.PrintUtils.setupPrintShortcuts(printReceipt);
    } else {
        console.warn('PrintUtils not available, using fallback initialization');
        console.log('Receipt print functionality initialized for Order ID: <?= htmlspecialchars($orderId) ?>');
        
        // Fallback keyboard shortcut
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printReceipt();
            }
        });
    }
});
</script>

<!-- Simple Receipt Header with Company Info -->
<div class="receipt-container max-w-2xl mx-auto bg-white shadow-md rounded p-6 mt-6">
    <!-- Company Header -->
    <div class="text-center mb-4 border-b pb-3">
        <div class="flex justify-center items-center mb-4">
            <img src="images/WhimsicalFrog_Logo.webp" alt="Whimsical Frog Crafts Logo" class="h-16 w-auto mr-3" 
                 onerror="this.src='images/WhimsicalFrog_Logo.png'">
            <div>
                <h1 class="text-2xl font-bold text-[#87ac3a]">Whimsical Frog Crafts</h1>
                <p class="text-base text-gray-600 italic">Custom Crafts & Personalized Gifts</p>
            </div>
        </div>
        <div class="text-sm text-gray-600">
            <p><strong>Lisa Lemley</strong></p>
            <p>1524 Red Oak Flats Rd</p>
            <p>Dahlonega, GA 30533</p>
            <p class="mt-1 text-[#87ac3a] font-medium">whimsicalfrog.us</p>
        </div>
    </div>

    <!-- Order Information -->
    <div class="text-center mb-3">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">Order Receipt</h2>
        <p class="text-sm text-gray-600">Order ID: <strong><?= htmlspecialchars($orderId) ?></strong></p>
        <p class="text-sm text-gray-600">Date: <?= date('M d, Y', strtotime($order['date'] ?? 'now')) ?></p>
    </div>

    <!-- Order Items Table -->
    <table class="w-full mb-3 text-sm">
        <thead>
            <tr class="bg-gray-100">
                <th class="text-left p-2">Item ID</th>
                <th class="text-left p-2">Item</th>
                <th class="text-center p-2">Qty</th>
                <th class="text-right p-2">Price</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orderItems as $it): ?>
                <tr class="border-b">
                    <td class="p-2 font-mono text-xs"><?= htmlspecialchars($it['sku'] ?? '') ?></td>
                    <td class="p-2"><?= htmlspecialchars($it['itemName'] ?? 'N/A') ?></td>
                    <td class="text-center p-2"><?= $it['quantity'] ?? 0 ?></td>
                    <td class="text-right p-2">$<?= number_format($it['price'] ?? 0, 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="flex justify-end mb-3 text-base font-semibold">
        <span>Total:&nbsp;$<?= $total ?></span>
    </div>

    <?php if ($pending): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-3 mb-3 text-sm" role="alert">
            <p class="font-bold">Thank you for choosing Whimsical&nbsp;Frog&nbsp;Crafts!</p>
            <p>Your order is reserved and will be shipped as soon as we receive your payment&nbsp;🙂</p>
            <p class="mt-2">Remit payment to:<br><strong>Lisa&nbsp;Lemley</strong><br>1524&nbsp;Red&nbsp;Oak&nbsp;Flats&nbsp;Rd<br>Dahlonega,&nbsp;GA&nbsp;30533</p>
            <p class="mt-2">Please include your order&nbsp;ID on the memo line. As soon as we record your payment we'll send a confirmation e-mail and get your items on their way.</p>
        </div>
    <?php else: ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 mb-3 text-sm" role="alert">
            <p class="font-bold"><?= htmlspecialchars($receiptMessage['title']) ?></p>
            <p><?= htmlspecialchars($receiptMessage['content']) ?></p>
        </div>
    <?php endif; ?>

    <!-- Sales Verbiage Section -->
    <?php if (!empty($salesVerbiage)): ?>
        <div class="border-t pt-4 mt-4 space-y-3">
            <?php if (!empty($salesVerbiage['receipt_thank_you_message'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-800 p-3 rounded-lg text-center">
                    <p class="font-semibold">💚 <?= htmlspecialchars($salesVerbiage['receipt_thank_you_message']) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($salesVerbiage['receipt_next_steps'])): ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-800 p-3 rounded-lg">
                    <p class="text-sm">📋 <?= htmlspecialchars($salesVerbiage['receipt_next_steps']) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($salesVerbiage['receipt_social_sharing'])): ?>
                <div class="bg-purple-50 border border-purple-200 text-purple-800 p-3 rounded-lg text-center">
                    <p class="text-sm font-medium">📱 <?= htmlspecialchars($salesVerbiage['receipt_social_sharing']) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($salesVerbiage['receipt_return_customer'])): ?>
                <div class="bg-orange-50 border border-orange-200 text-orange-800 p-3 rounded-lg text-center">
                    <p class="text-sm">🎨 <?= htmlspecialchars($salesVerbiage['receipt_return_customer']) ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="text-center mt-4">
        <button id="printBtn" onclick="printReceipt();" class="brand-button">
            🖨️ Print Receipt
        </button>
    </div>
</div> 