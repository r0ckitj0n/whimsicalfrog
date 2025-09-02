<?php
/**
 * Template: admin_notification
 * Variables: $order (array), $customer (array), $items (array)
 */
$brandPrimary = function_exists('BusinessSettings::getPrimaryColor') ? BusinessSettings::getPrimaryColor() : '#0ea5e9';
$orderId = htmlspecialchars($order['id'] ?? 'N/A');
$customerEmail = htmlspecialchars($customer['email'] ?? '');
?>
<!-- WF_GUARD_TEMPLATES_CSS_IGNORE -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>New Order #<?= $orderId ?></title>
  <style>
    body { margin:0; padding:0; background:#ffffff; color:#333; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; line-height:1.5; }
    .wrapper { max-width:600px; margin:0 auto; padding:16px; }
    .header { background: <?= $brandPrimary ?>; color:#fff; padding:16px; text-align:center; }
    .h1 { margin:0; font-size:20px; }
    .items li { margin:4px 0; }
  </style>
</head>
<body>
  <div class="header">
    <h1 class="h1">Whimsical Frog</h1>
    <p>Admin Notification</p>
  </div>
  <div class="wrapper">
    <p>A new order <strong>#<?= $orderId ?></strong> was placed.</p>
    <p><strong>Customer:</strong> <?= htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?> (<?= $customerEmail ?>)</p>
    <h3>Items</h3>
    <ul class="items">
      <?php if (is_array($items)) foreach ($items as $it): ?>
        <li><?= htmlspecialchars(($it['quantity'] ?? 1) . ' × ' . ($it['name'] ?? 'Item')) ?> — $<?= htmlspecialchars(number_format((float)($it['price'] ?? 0), 2)) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</body>
</html>
