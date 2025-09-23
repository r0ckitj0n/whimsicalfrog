<?php
/**
 * Template: password_reset
 * Variables: $reset_token (string), $user_name (string|null), $reset_url (string)
 */
require_once dirname(__DIR__, 2) . '/includes/email_branding.php';
require_once dirname(__DIR__, 2) . '/api/business_settings_helper.php';
$name = htmlspecialchars($user_name ?: 'there');
$url = htmlspecialchars($reset_url ?? '#');
$businessName = BusinessSettings::getBusinessName();
?>
<!-- WF_GUARD_TEMPLATES_CSS_IGNORE -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Password Reset</title>
  <?= $EMAIL_BRANDING_STYLE ?>
</head>
<body>
  <div class="header">
    <h1 class="h1"><?= htmlspecialchars($businessName) ?></h1>
    <p>Password Reset Request</p>
  </div>
  <div class="wrapper">
    <p>Hi <?= $name ?>,</p>
    <p>We received a request to reset your password. Click the button below to choose a new password:</p>
    <p><a href="<?= $url ?>" class="btn">Reset Your Password</a></p>
    <p class="muted">If you didn't request this, you can safely ignore this email. This link will expire after a short time for your security.</p>
  </div>
</body>
</html>
