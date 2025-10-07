<?php
/**
 * Template: order_confirmation
 * Variables: $order (array), $customer (array), $items (array)
 */
require_once dirname(__DIR__, 2) . '/includes/email_branding.php';
require_once dirname(__DIR__, 2) . '/api/business_settings_helper.php';
$orderId = htmlspecialchars($order['id'] ?? 'N/A');
$fullName = htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
$total = htmlspecialchars(number_format((float)($order['total'] ?? 0), 2));
$businessName = BusinessSettings::getBusinessName();
$businessAddressBlock = BusinessSettings::getBusinessAddressBlock();
$businessPhone = BusinessSettings::get('business_phone', '');
$businessUrl = BusinessSettings::getSiteUrl('');
?>
<!-- WF_GUARD_TEMPLATES_CSS_IGNORE -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Order Confirmation #<?= $orderId ?></title>
  <?= $EMAIL_BRANDING_STYLE ?>
</head>
<body>
  <div class="header">
    <h1 class="h1"><?= htmlspecialchars($businessName) ?></h1>
    <p>Order Confirmation</p>
    <?php if (!empty($businessAddressBlock) || !empty($businessPhone) || !empty($businessUrl)): ?>
      <p class="muted" style="white-space: pre-line;">
        <?php if (!empty($businessAddressBlock)): ?><?= nl2br(htmlspecialchars($businessAddressBlock)) ?><?php endif; ?>
        <?php if (!empty($businessPhone) || !empty($businessUrl)): ?><br><?php endif; ?>
        <?php if (!empty($businessPhone)): ?><?= htmlspecialchars($businessPhone) ?><?php endif; ?>
        <?php if (!empty($businessPhone) && !empty($businessUrl)): ?> | <?php endif; ?>
        <?php if (!empty($businessUrl)): ?><?= htmlspecialchars($businessUrl) ?><?php endif; ?>
      </p>
    <?php endif; ?>
  </div>
  <div class="wrapper">
    <p>Hi <?= $fullName ?>,</p>
    <p>Thanks for your order! Your order <strong>#<?= $orderId ?></strong> has been received.</p>

    <div class="section">
      <h3 class="h3">Order Summary</h3>
      <ul class="items">
        <?php if (is_array($items)) {
            foreach ($items as $it): ?>
          <li><?= htmlspecialchars(($it['quantity'] ?? 1) . ' × ' . ($it['name'] ?? 'Item')) ?> — $<?= htmlspecialchars(number_format((float)($it['price'] ?? 0), 2)) ?></li>
        <?php endforeach;
        } ?>
      </ul>
      <p><strong>Total:</strong> $<?= $total ?></p>
    </div>

    <p class="muted">If you have any questions, just reply to this email.</p>
  </div>
</body>
</html>
