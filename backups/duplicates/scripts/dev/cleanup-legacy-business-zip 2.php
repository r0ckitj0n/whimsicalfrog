<?php
// Cleanup script: Remove legacy 'business_zip' rows from business_settings
// Usage: php scripts/dev/cleanup-legacy-business-zip.php [--dry-run]

require_once __DIR__ . '/../../api/config.php';

$dryRun = in_array('--dry-run', $argv, true);

try {
    $db = Database::getInstance();
    $countRow = Database::queryOne("SELECT COUNT(*) AS cnt FROM business_settings WHERE setting_key = 'business_zip'");
    $count = (int)($countRow['cnt'] ?? 0);
    if ($dryRun) {
        echo "[DRY RUN] Found {$count} legacy business_zip rows. No deletions performed.\n";
        exit(0);
    }
    if ($count > 0) {
        $deleted = Database::execute("DELETE FROM business_settings WHERE setting_key = 'business_zip'");
        echo "Deleted {$deleted} legacy business_zip rows.\n";
    } else {
        echo "No legacy business_zip rows found.\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . "\n");
    exit(1);
}
