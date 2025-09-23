<?php
/**
 * Template: admin_notification
 * Variables: $order (array), $customer (array), $items (array)
 */
require_once dirname(__DIR__, 2) . '/includes/email_branding.php';
require_once dirname(__DIR__, 2) . '/api/business_settings_helper.php';
$orderId = htmlspecialchars($order['id'] ?? 'N/A');
$customerEmail = htmlspecialchars($customer['email'] ?? '');
$businessName = BusinessSettings::getBusinessName();
?>
<!-- WF_GUARD_TEMPLATES_CSS_IGNORE -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>New Order #<?= $orderId ?></title>
  <?= $EMAIL_BRANDING_STYLE ?>
</head>
<body>
  <div class="header">
    <h1 class="h1"><?= htmlspecialchars($businessName) ?></h1>
    <p>Admin Notification</p>
  </div>
  <div class="wrapper">
    <p>A new order <strong>#<?= $orderId ?></strong> was placed.</p>
    <p><strong>Customer:</strong> <?= htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?> (<?= $customerEmail ?>)</p>
    <h3>Items</h3>
    <ul class="items">
      <?php if (is_array($items)) {
          foreach ($items as $it): ?>
        <li><?= htmlspecialchars(($it['quantity'] ?? 1) . ' × ' . ($it['name'] ?? 'Item')) ?> — $<?= htmlspecialchars(number_format((float)($it['price'] ?? 0), 2)) ?></li>
      <?php endforeach;
      } ?>
    </ul>
  </div>
</body>
</html>
