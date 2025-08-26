<?php
/**
 * Template: welcome
 * Variables: $user_name (string), $activation_token (string|null), $activation_url (string|null)
 */
$brandPrimary = function_exists('BusinessSettings::getPrimaryColor') ? BusinessSettings::getPrimaryColor() : '#0ea5e9';
$brandSecondary = function_exists('BusinessSettings::getSecondaryColor') ? BusinessSettings::getSecondaryColor() : '#22c55e';
$name = htmlspecialchars($user_name ?: 'there');
$hasActivation = !empty($activation_url);
$url = htmlspecialchars($activation_url ?? '#');
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Welcome to Whimsical Frog</title>
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
    <h1 class="h1">Welcome to Whimsical Frog</h1>
  </div>
  <div class="wrapper">
    <p>Hi <?= $name ?>,</p>
    <p>We're excited to have you on board! Explore our handcrafted products and enjoy your stay.</p>

    <?php if ($hasActivation): ?>
    <p>
      <a href="<?= $url ?>" class="btn">Activate Your Account</a>
    </p>
    <p class="muted">If the button doesn't work, paste this link into your browser:<br><?= $url ?></p>
    <?php endif; ?>

    <p class="muted">If you have any questions, just reply to this email.</p>
  </div>
</body>
</html>
