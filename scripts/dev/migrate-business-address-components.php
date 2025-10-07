<?php
// Migration: Parse legacy multi-line business_address into components
// Usage: php scripts/dev/migrate-business-address-components.php [--dry-run]

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/business_settings_helper.php';

$dryRun = in_array('--dry-run', $argv, true);

function getSetting($key) {
    $row = Database::queryOne("SELECT setting_value, setting_type, category FROM business_settings WHERE setting_key = ? ORDER BY (category='business_info') DESC, updated_at DESC LIMIT 1", [$key]);
    return $row ? ($row['setting_value'] ?? '') : '';
}

function upsertSetting($category, $key, $value, $type = 'text', $displayName = '', $description = '') {
    $sql = "INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), display_name = VALUES(display_name), description = VALUES(description), updated_at = NOW()";
    return Database::execute($sql, [$category, $key, $value, $type, $displayName ?: ucwords(str_replace('_',' ', $key)), $description ?: ('Business setting '.$key)]);
}

try {
    Database::getInstance();

    // Read canonical blob and components
    $rawBlob = BusinessSettings::get('business_address', '');
    $addr2   = BusinessSettings::get('business_address2', '');
    $city    = BusinessSettings::get('business_city', '');
    $state   = BusinessSettings::get('business_state', '');
    $postal  = BusinessSettings::get('business_postal', '');

    if ($city !== '' && $state !== '' && $postal !== '') {
        echo "Components already present; nothing to migrate.\n";
        exit(0);
    }

    $blob = trim((string)$rawBlob);
    if ($blob === '') {
        echo "No legacy business_address found to migrate.\n";
        exit(0);
    }

    // Split into lines, normalize whitespace
    $lines = preg_split('/\r?\n+/', $blob);
    $lines = array_values(array_filter(array_map(function($l){ return trim(preg_replace('/\s+/', ' ', (string)$l)); }, $lines), function($l){ return $l !== ''; }));

    $line1 = $lines[0] ?? '';
    $lineCityState = '';
    $lineZip = '';

    // Heuristic: last line that looks like ZIP
    if (!empty($lines)) {
        $last = end($lines);
        if (preg_match('/^\d{5}(?:-\d{4})?$/', $last)) {
            $lineZip = $last;
            array_pop($lines);
        }
    }
    // If we still don't have a zip and any line contains ZIP at end, extract
    if ($lineZip === '' && !empty($lines)) {
        $cand = end($lines);
        if (preg_match('/(\d{5}(?:-\d{4})?)$/', $cand, $m)) {
            $lineZip = $m[1];
            $cand = trim(preg_replace('/\s*\d{5}(?:-\d{4})?$/', '', $cand));
            $lines[count($lines)-1] = $cand;
        }
    }

    // After removing ZIP, try to find city/state line (commonly contains a comma)
    if (!empty($lines)) {
        $candidate = end($lines);
        if (strpos($candidate, ',') !== false) {
            $lineCityState = $candidate;
            array_pop($lines);
        }
    }

    // Now we have:
    // - $line1: primary address
    // - remaining $lines might include address2; take the next line as address2 if present
    $addr2Parsed = $lines[1] ?? ($lines[0] ?? '');
    if ($addr2Parsed === $line1) { $addr2Parsed = ''; }

    // Parse city/state
    $parsedCity = $city;
    $parsedState = $state;
    if ($lineCityState !== '') {
        $parts = array_map('trim', explode(',', $lineCityState, 2));
        $parsedCity = $parts[0] ?? '';
        $right = $parts[1] ?? '';
        // State may be the first token on right side
        if ($right !== '') {
            $tok = preg_split('/\s+/', $right);
            $parsedState = $tok[0] ?? '';
        }
    }

    // Choose final components, preserving any existing explicit values
    $finalLine1 = $line1;
    $finalAddr2 = $addr2 !== '' ? $addr2 : $addr2Parsed;
    $finalCity  = $city !== '' ? $city : $parsedCity;
    $finalState = $state !== '' ? $state : $parsedState;
    $finalZip   = $postal !== '' ? $postal : $lineZip;

    echo "Proposed migration:\n";
    printf("  Line1 : %s\n", $finalLine1);
    printf("  Line2 : %s\n", $finalAddr2);
    printf("  City  : %s\n", $finalCity);
    printf("  State : %s\n", $finalState);
    printf("  Postal: %s\n", $finalZip);

    if ($dryRun) {
        echo "[DRY RUN] No writes performed.\n";
        exit(0);
    }

    $saved = 0;
    $saved += upsertSetting('business_info', 'business_address', $finalLine1);
    if ($finalAddr2 !== '') $saved += upsertSetting('business_info', 'business_address2', $finalAddr2);
    if ($finalCity  !== '') $saved += upsertSetting('business_info', 'business_city', $finalCity);
    if ($finalState !== '') $saved += upsertSetting('business_info', 'business_state', $finalState);
    if ($finalZip   !== '') $saved += upsertSetting('business_info', 'business_postal', $finalZip);

    if (class_exists('BusinessSettings')) {
        BusinessSettings::clearCache();
    }

    echo "Saved/updated {$saved} setting rows.\n";
    exit(0);

} catch (Throwable $e) {
    fwrite(STDERR, '[ERROR] ' . $e->getMessage() . "\n");
    exit(1);
}
