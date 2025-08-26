<?php
/**
 * Template: password_reset
 * Variables: $reset_token (string), $user_name (string|null), $reset_url (string)
 */
$brandPrimary = function_exists('BusinessSettings::getPrimaryColor') ? BusinessSettings::getPrimaryColor() : '#0ea5e9';
$brandSecondary = function_exists('BusinessSettings::getSecondaryColor') ? BusinessSettings::getSecondaryColor() : '#22c55e';
$name = htmlspecialchars($user_name ?: 'there');
$url = htmlspecialchars($reset_url ?? '#');
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Password Reset</title>
  <style>
    body { margin:0; padding:0; background:#ffffff; color:#333; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; line-height:1.5; }
    .wrapper { max-width:600px; margin:0 auto; padding:16px; }
    .header { background: <?= $brandPrimary ?>; color:#fff; padding:16px; text-align:center; }
    .h1 { margin:0; font-size:20px; }
    .section { margin:16px 0; }
    .btn { display:inline-block; background: <?= $brandPrimary ?>; color:#fff !important; text-decoration:none; padding:10px 14px; border-radius:4px; }
    .muted { color:#666; font-size:14px; }
    a { color: <?= $brandSecondary ?>; }
  </style>
</head>
<body>
  <div class="header">
    <h1 class="h1">Whimsical Frog</h1>
    <p>Password Reset Request</p>
  </div>
  <div class="wrapper">
    <p>Hi <?= $name ?>,</p>
    <p>We received a request to reset your password. Click the button below to choose a new password:</p>
    <p>
      <a href="<?= $url ?>" class="btn">Reset Your Password</a>
    </p>
    <p class="muted">If you didn't request this, you can safely ignore this email. This link will expire after a short time for your security.</p>
  </div>
</body>
</html>
