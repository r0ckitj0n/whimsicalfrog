<?php
/**
 * Template: welcome
 * Variables: $user_name (string), $activation_token (string|null), $activation_url (string|null)
 */
require_once dirname(__DIR__, 2) . '/includes/email_branding.php';
require_once dirname(__DIR__, 2) . '/api/business_settings_helper.php';
$name = htmlspecialchars($user_name ?: 'there');
$hasActivation = !empty($activation_url);
$url = htmlspecialchars($activation_url ?? '#');
$businessName = BusinessSettings::getBusinessName();
$supportEmail = BusinessSettings::get('business_support_email', BusinessSettings::getBusinessEmail());
$privacyUrl = BusinessSettings::get('business_privacy_url', '');
$termsUrl = BusinessSettings::get('business_terms_url', '');
$policyUrl = BusinessSettings::get('business_policy_url', '');
?>
<!-- WF_GUARD_TEMPLATES_CSS_IGNORE -->
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Welcome to Whimsical Frog</title>
  <?= $EMAIL_BRANDING_STYLE ?>
</head>
<body>
  <div class="header">
    <h1 class="h1">Welcome to <?= htmlspecialchars($businessName) ?></h1>
  </div>
  <div class="wrapper">
    <p>Hi <?= $name ?>,</p>
    <p>We're excited to have you on board! Explore our handcrafted items and enjoy your stay.</p>

    <?php if ($hasActivation): ?>
    <p>
      <a href="<?= $url ?>" class="btn">Activate Your Account</a>
    </p>
    <p class="muted">If the button doesn't work, paste this link into your browser:<br><?= $url ?></p>
    <?php endif; ?>

    <p class="muted">If you have any questions, just reply to this email.</p>
    <hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0;">
    <p class="muted" style="margin:0;">
      <?php if (!empty($policyUrl)): ?><a href="<?= htmlspecialchars($policyUrl) ?>">Policies</a><?php endif; ?>
      <?php if (!empty($policyUrl) && (!empty($privacyUrl) || !empty($termsUrl))): ?> • <?php endif; ?>
      <?php if (!empty($privacyUrl)): ?><a href="<?= htmlspecialchars($privacyUrl) ?>">Privacy</a><?php endif; ?>
      <?php if (!empty($privacyUrl) && !empty($termsUrl)): ?> • <?php endif; ?>
      <?php if (!empty($termsUrl)): ?><a href="<?= htmlspecialchars($termsUrl) ?>">Terms</a><?php endif; ?>
      <?php if (!empty($supportEmail)): ?>
        <br>Support: <a href="mailto:<?= htmlspecialchars($supportEmail) ?>"><?= htmlspecialchars($supportEmail) ?></a>
      <?php endif; ?>
    </p>
  </div>
</body>
</html>
