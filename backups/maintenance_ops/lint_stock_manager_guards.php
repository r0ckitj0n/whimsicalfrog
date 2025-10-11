<?php
/**
 * Lint guard: enforce centralized stock sync usage in API files
 *
 * Checks:
 *  1) No local definitions of syncColorStockWithSizes/syncTotalStockWithSizes/syncTotalStockWithColors
 *  2) If file uses any of these functions, it must require includes/stock_manager.php
 *  3) Calls to sync* must pass a PDO/Database instance as first arg (either variable like $pdo or Database::getInstance())
 *
 * Usage:
 *   php scripts/dev/lint_stock_manager_guards.php [--fix]  # --fix will try to add the require_once include if missing
 */

$root = dirname(__DIR__, 1); // scripts/
$apiDir = realpath($root . '/../api');
if (!$apiDir || !is_dir($apiDir)) {
    fwrite(STDERR, "Cannot locate api directory\n");
    exit(2);
}

$errors = 0;
$fix = in_array('--fix', $argv, true);

$apiFiles = glob($apiDir . '/*.php');
foreach ($apiFiles as $file) {
    $code = file_get_contents($file);
    if ($code === false) continue;

    $rel = substr($file, strlen(realpath($root . '/..')) + 1);

    // 1) Forbid local function definitions
    if (preg_match('/function\s+sync(Color|Total)StockWith(Size|Colors)\s*\(/', $code)) {
        echo "[FAIL] $rel defines forbidden sync helper(s). Use includes/stock_manager.php instead.\n";
        $errors++;
        continue;
    }

    // See if it uses any sync calls
    $usesSync = preg_match('/sync(Color|Total)StockWith(Size|Colors)\s*\(/', $code);
    if ($usesSync) {
        // 2) Must include stock_manager
        if (strpos($code, "/includes/stock_manager.php") === false) {
            echo "[FAIL] $rel uses sync functions but does not include includes/stock_manager.php\n";
            if ($fix) {
                // Insert require after config include
                $code = preg_replace(
                    '/(require_once\s+__DIR__\s*\.\s*\'\/config.php\'\s*;)/',
                    "$1\nrequire_once __DIR__ . '/../includes/stock_manager.php';",
                    $code,
                    1,
                    $replacements
                );
                if ($replacements > 0) {
                    file_put_contents($file, $code);
                    echo "  -> Fixed: added stock_manager include\n";
                } else {
                    echo "  -> Could not auto-fix include location\n";
                }
            }
            $errors++;
        }

        // 3) First arg of sync* must be a DB instance
        // Simple heuristic: syncXxx(\s*Database::getInstance|\$[a-zA-Z_][a-zA-Z0-9_]*,)
        if (preg_match_all('/sync(?:Color|Total)StockWith(?:Sizes|Colors)\s*\(([^)]*)\)/', $code, $m)) {
            foreach ($m[1] as $args) {
                $first = trim(explode(',', $args, 2)[0] ?? '');
                if (!preg_match('/^(Database::getInstance\(\)|\$[a-zA-Z_][a-zA-Z0-9_]*)$/', $first)) {
                    echo "[FAIL] $rel calls sync* without DB instance as first arg: ($args)\n";
                    $errors++;
                }
            }
        }
    }
}

if ($errors > 0) {
    echo "\nGuard failed with $errors issue(s).\n";
    exit(1);
}

echo "All API files pass stock manager guard.\n";
exit(0);
