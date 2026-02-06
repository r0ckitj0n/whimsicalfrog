<?php
/**
 * System Cleanup Manager Logic
 */

function getUnusedFiles()
{
    $staleFiles = [
        '../api/email_config_backup_2025-06-16_15-14-32.php',
        '../api/square_settings_refactored_demo.php',
        '../api/square_settings_refactored.php',
        '../cookies.txt',
        '../current_cron.txt',
        '../new_cron.txt',
        '../test_cart_options.html'
    ];
    $unused = [];
    foreach ($staleFiles as $file) {
        if (file_exists($file)) {
            $unused[] = [
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file),
                'reason' => 'Stale/backup file'
            ];
        }
    }
    return $unused;
}

function getStaleComments()
{
    $patterns = ['/\/\*\*?\s*TODO[^*]*\*\//', '/\/\/\s*TODO.*/', '/\/\/\s*DEBUG.*/'];
    $files = glob('../{api,sections,includes}/*.php', GLOB_BRACE);
    $stale = [];
    foreach ($files as $file) {
        $lines = explode("\n", file_get_contents($file));
        foreach ($lines as $idx => $line) {
            foreach ($patterns as $p) {
                if (preg_match($p, $line)) {
                    $stale[] = ['file' => str_replace('../', '', $file), 'line' => $idx + 1, 'content' => trim($line)];
                }
            }
        }
    }
    return $stale;
}

function optimizeDatabase($pdo)
{
    $tables = array_map(fn($r) => array_values($r)[0], Database::queryAll("SHOW TABLES"));
    $details = [];
    foreach ($tables as $table) {
        $t0 = microtime(true);
        Database::queryOne("OPTIMIZE TABLE \`$table\`");
        $details[] = ['table' => $table, 'time' => round(microtime(true) - $t0, 3)];
    }
    return $details;
}
