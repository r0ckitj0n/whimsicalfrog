<?php
// Set SMTP secrets securely using the encrypted secret store.
// Usage: php scripts/dev/set-smtp-secrets.php

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/secret_store.php';

$u = 'orders@whimsicalfrog.us';
$p = 'Fr0gH0pp3r!';

if (!secret_set('smtp_username', $u)) {
    fwrite(STDERR, "Failed to set smtp_username\n");
    exit(1);
}
if (!secret_set('smtp_password', $p)) {
    fwrite(STDERR, "Failed to set smtp_password\n");
    exit(1);
}

echo "SMTP secrets set.\n";
