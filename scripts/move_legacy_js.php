<?php
declare(strict_types=1);

// Move legacy root-level JS files (and src/main.js) into backups/unused-YYYY-MM-DD[/HHMMSS]
// Preserves relative paths under backups/.
// Usage (web):   /scripts/move_legacy_js.php?confirm=1[&dry_run=1][&overwrite=1]
// Usage (CLI):   php scripts/move_legacy_js.php --confirm [--dry-run] [--overwrite]
// Safety: defaults to dry-run unless confirm=1 or --confirm is provided.

error_reporting(E_ALL);
@ini_set('display_errors', '1');
@set_time_limit(300);

function param_bool(array $sources, string $name, bool $default = false): bool {
    foreach ($sources as $src) {
        if (isset($src[$name])) {
            $val = (string)$src[$name];
            return $val === '1' || strtolower($val) === 'true' || strtolower($val) === 'yes' || $val === 'on';
        }
    }
    return $default;
}

function param_flag_cli(array $argv, string $flag): bool {
    foreach ($argv as $a) {
        if ($a === $flag) return true;
    }
    return false;
}

// Resolve project root from this script location
$scriptDir = __DIR__;
$projectRoot = realpath($scriptDir . '/..');
if ($projectRoot === false) {
    http_response_code(500);
    echo "ERROR: Could not resolve project root";
    exit(1);
}

// Inputs
$now = new DateTimeImmutable('now');
$date = $now->format('Y-m-d');
$base = "backups/unused-$date";
$destBase = $projectRoot . DIRECTORY_SEPARATOR . $base;

// If directory exists already, create a unique suffix with time to avoid collisions
if (is_dir($destBase)) {
    $suffix = $now->format('His');
    $base .= "-$suffix";
    $destBase = $projectRoot . DIRECTORY_SEPARATOR . $base;
}

// Read flags from web or CLI
$confirm = false;
$dryRun = true;
$overwrite = false;

if (PHP_SAPI === 'cli') {
    $confirm = param_flag_cli($argv ?? [], '--confirm');
    $dryRun = !param_flag_cli($argv ?? [], '--execute') && !param_flag_cli($argv ?? [], '--no-dry-run') && !param_flag_cli($argv ?? [], '--move');
    $overwrite = param_flag_cli($argv ?? [], '--overwrite');
    // If explicitly confirmed without any execute flags, allow move (no dry run)
    if ($confirm && ($dryRun === true)) {
        $dryRun = false;
    }
} else {
    $confirm = param_bool([$_GET, $_POST], 'confirm', false);
    // default dry-run unless confirm=1 AND dry_run=0
    $dryRun = !($confirm && param_bool([$_GET, $_POST], 'dry_run', false) === false);
    $overwrite = param_bool([$_GET, $_POST], 'overwrite', false);
}

// Files to move (relative to project root)
$files = [
    'src/main.js',
    'js/whimsical-frog-core.js',
    'js/utils.js',
    'js/additional_main.js',
    'js/pos-calculator.js',
    'js/advanced-search.js',
    'js/analytics.js',
    'js/analytics_enhanced.js',
    'js/chart.min.js',
    'js/full-calendar.js',
    'js/cookie-consent.js',
    'js/dynamic-background-loader.js',
    'js/bundle-scripts.js',
    'js/global-event-listeners.js',
    'js/wf-unified.js',
    'js/script-loader.js',
    'js/search.js',
    'js/comprehensive-missing-functions.js',
    'js/modal-debug.js',
    'js/modal-css-diagnostics.js',
    'js/api-client.js',
    'js/ui-manager.js',
    'js/image-viewer.js',
    'js/global-notifications.js',
    'js/notification-messages.js',
    'js/global-popup.js',
    'js/global-modals.js',
    'js/modal-functions.js',
    'js/modal-close-positioning.js',
    'js/room-coordinate-manager.js',
    'js/room-functions.js',
    'js/room-helper.js',
    'js/room-main.js',
    'js/global-item-modal.js',
    'js/detailed-item-modal.js',
    'js/room-modal-manager.js',
    'js/cart-system.js',
    'js/main-application.js',
];

// Helpers
function ensure_dir(string $dir): void {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Failed to create directory: $dir");
    }
}

function move_preserve(string $projectRoot, string $rel, string $baseDest, bool $overwrite, bool $dryRun): array {
    $src = $projectRoot . DIRECTORY_SEPARATOR . $rel;
    $dest = $projectRoot . DIRECTORY_SEPARATOR . $baseDest . DIRECTORY_SEPARATOR . $rel;

    if (!file_exists($src)) {
        return ['status' => 'skip_missing', 'rel' => $rel, 'src' => $src, 'dest' => $dest];
    }

    if (file_exists($dest)) {
        if ($overwrite) {
            // remove destination to allow overwrite
            if (!$dryRun) {
                if (is_dir($dest)) {
                    return ['status' => 'error', 'rel' => $rel, 'message' => 'Destination exists and is a directory'];
                }
                if (!unlink($dest)) {
                    return ['status' => 'error', 'rel' => $rel, 'message' => 'Failed to remove existing destination'];
                }
            }
        } else {
            return ['status' => 'skip_exists', 'rel' => $rel, 'src' => $src, 'dest' => $dest];
        }
    }

    $destDir = dirname($dest);
    if (!$dryRun) {
        ensure_dir($destDir);
        // Try rename first (same filesystem). If fails, fallback to copy+unlink.
        if (!@rename($src, $dest)) {
            if (!@copy($src, $dest)) {
                return ['status' => 'error', 'rel' => $rel, 'message' => 'copy failed'];
            }
            if (!@unlink($src)) {
                return ['status' => 'error', 'rel' => $rel, 'message' => 'unlink after copy failed'];
            }
        }
    }

    return ['status' => $dryRun ? 'would_move' : 'moved', 'rel' => $rel, 'src' => $src, 'dest' => $dest];
}

$results = [];

try {
    // Ensure destination root exists (in execute mode)
    if (!$dryRun) {
        ensure_dir($destBase);
    }

    foreach ($files as $rel) {
        $results[] = move_preserve($projectRoot, $rel, $base, $overwrite, $dryRun);
    }
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Output (simple text for compatibility)
header('Content-Type: text/plain; charset=utf-8');
echo "WhimsicalFrog Legacy JS Move\n";
echo "Project root: $projectRoot\n";
echo "Destination:   $base\n";
echo "Mode:          " . ($dryRun ? 'DRY-RUN' : 'EXECUTE') . "\n";
echo "Overwrite:     " . ($overwrite ? 'yes' : 'no') . "\n";
echo str_repeat('-', 60) . "\n";

$counts = [
    'moved' => 0,
    'would_move' => 0,
    'skip_missing' => 0,
    'skip_exists' => 0,
    'error' => 0,
];

foreach ($results as $r) {
    $status = $r['status'];
    $counts[$status] = ($counts[$status] ?? 0) + 1;
    $line = sprintf("%-12s %s", $status, $r['rel'] ?? '');
    if (isset($r['message'])) {
        $line .= ' :: ' . $r['message'];
    }
    echo $line . "\n";
}

echo str_repeat('-', 60) . "\n";
foreach ($counts as $k => $v) {
    echo sprintf("%13s : %d\n", $k, $v);
}

echo str_repeat('-', 60) . "\n";
if ($dryRun && !$confirm) {
    echo "This was a preview. To execute, run one of:\n";
    echo "  CLI : php scripts/move_legacy_js.php --confirm\n";
    echo "  Web : /scripts/move_legacy_js.php?confirm=1&dry_run=0\n";
}
