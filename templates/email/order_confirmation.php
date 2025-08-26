<?php
/**
 * Template: order_confirmation
 * Variables: $order (array), $customer (array), $items (array)
 */
$brandPrimary = function_exists('BusinessSettings::getPrimaryColor') ? BusinessSettings::getPrimaryColor() : '#0ea5e9';
$brandSecondary = function_exists('BusinessSettings::getSecondaryColor') ? BusinessSettings::getSecondaryColor() : '#22c55e';
$orderId = htmlspecialchars($order['id'] ?? 'N/A');
$fullName = htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
$total = htmlspecialchars(number_format((float)($order['total'] ?? 0), 2));
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Order Confirmation #<?= $orderId ?></title>
  <style>
    body { margin:0; padding:0; background:#ffffff; color:#333; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; line-height:1.5; }
    .wrapper { max-width:600px; margin:0 auto; padding:16px; }
    .header { background: <?= $brandPrimary ?>; color:#fff; padding:16px; text-align:center; }
    .h1 { margin:0; font-size:20px; }
    .section { margin:16px 0; }
    .muted { color:#666; font-size:14px; }
    .items li { margin:4px 0; }
  </style>
</head>
<body>
  <div class="header">
    <h1 class="h1">Whimsical Frog</h1>
    <p>Order Confirmation</p>
  </div>
  <div class="wrapper">
    <p>Hi <?= $fullName ?>,</p>
    <p>Thanks for your order! Your order <strong>#<?= $orderId ?></strong> has been received.</p>

    <div class="section">
      <h3 style="color: <?= $brandSecondary ?>; margin:0 0 8px;">Order Summary</h3>
      <ul class="items">
        <?php if (is_array($items)) foreach ($items as $it): ?>
          <li><?= htmlspecialchars(($it['quantity'] ?? 1) . ' × ' . ($it['name'] ?? 'Item')) ?> — $<?= htmlspecialchars(number_format((float)($it['price'] ?? 0), 2)) ?></li>
        <?php endforeach; ?>
      </ul>
      <p><strong>Total:</strong> $<?= $total ?></p>
    </div>

    <p class="muted">If you have any questions, just reply to this email.</p>
  </div>
</body>
</html>
