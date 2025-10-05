<?php

// Verify SMTP settings and secrets
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';
require_once __DIR__ . '/../../includes/secret_store.php';

$cfg = BusinessSettings::getByCategory('email');
$keys = ['smtp_host','smtp_port','smtp_encryption','smtp_enabled'];

$out = [
  'email_settings' => [],
  'secrets' => [
    'smtp_username' => secret_has('smtp_username'),
    'smtp_password' => secret_has('smtp_password'),
  ],
  'from_email' => BusinessSettings::getBusinessEmail(),
  'from_name'  => BusinessSettings::getBusinessName(),
  'site_url'   => BusinessSettings::getSiteUrl(''),
];

foreach ($keys as $k) {
    $out['email_settings'][$k] = $cfg[$k] ?? null;
}

header('Content-Type: application/json');
echo json_encode($out, JSON_PRETTY_PRINT), "\n";
