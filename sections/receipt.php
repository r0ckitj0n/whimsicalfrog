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
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found');
    $itemsStmt = $pdo->prepare("
        SELECT 
            oi.sku,
            oi.quantity,
            oi.price,
            i.name as itemName
        FROM order_items oi
        LEFT JOIN items i ON oi.sku = i.sku
        WHERE oi.orderId = ?
    ");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo '<div class="text-center py-12"><h1 class="text-2xl font-bold text-red-600">Error loading order</h1><p>'.htmlspecialchars($e->getMessage()).'</p></div>';
    return;
}
    $total = number_format($order['total'] ?? 0, 2);
$pending = ($order['paymentStatus'] === 'Pending');
?>
<style>
@media print {
  #printBtn { display:none; }
}
</style>
<div class="max-w-2xl mx-auto bg-white shadow-md rounded p-6 mt-6">
    <h1 class="text-2xl font-bold text-center text-[#87ac3a] mb-4">Order Receipt</h1>
            <p class="text-sm text-gray-600 text-center mb-6">Order ID: <strong><?= htmlspecialchars($orderId) ?></strong><br>Date: <?= date('M d, Y', strtotime($order['date'] ?? 'now')) ?></p>

    <table class="w-full mb-6 text-sm"><thead><tr class="bg-gray-100"><th class="text-left p-2">Item ID</th><th class="text-left p-2">Item</th><th class="text-center p-2">Qty</th><th class="text-right p-2">Price</th></tr></thead><tbody>
        <?php foreach ($orderItems as $it): ?>
            <tr class="border-b"><td class="p-2 font-mono text-xs"><?= htmlspecialchars($it['sku'] ?? '') ?></td><td class="p-2"><?= htmlspecialchars($it['itemName'] ?? 'N/A') ?></td><td class="text-center p-2"><?= $it['quantity'] ?? 0 ?></td><td class="text-right p-2">$<?= number_format($it['price'] ?? 0, 2) ?></td></tr>
        <?php endforeach; ?>
    </tbody></table>
    <div class="flex justify-end mb-6 text-lg font-semibold">
        <span>Total:&nbsp;$<?= $total ?></span>
    </div>

    <?php if ($pending): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
            <p class="font-bold">Thank you for choosing Whimsical&nbsp;Frog&nbsp;Crafts!</p>
            <p>Your order is reserved and will be shipped as soon as we receive your payment&nbsp;🙂</p>
            <p class="mt-2">Remit payment to:<br><strong>Lisa&nbsp;Lemley</strong><br>1524&nbsp;Red&nbsp;Oak&nbsp;Flats&nbsp;Rd<br>Dahlonega,&nbsp;GA&nbsp;30533</p>
            <p class="mt-2">Please include your order&nbsp;ID on the memo line. As soon as we record your payment we'll send a confirmation e-mail and get your items on their way.</p>
        </div>
    <?php else: ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p class="font-bold">Payment Received</p>
            <p>Your order is being processed. You'll receive tracking information once it ships.</p>
        </div>
    <?php endif; ?>

    <button id="printBtn" onclick="window.print();" class="px-4 py-2 bg-[#87ac3a] hover:bg-[#a3cc4a] text-white rounded">Print Receipt</button>
</div> 