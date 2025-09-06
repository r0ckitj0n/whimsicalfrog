<?php
// scripts/dev/smoke_db_test.php
// Quick smoke test for centralized DB configuration and connection

require_once __DIR__ . '/../../api/config.php';

function mask($s) {
    if ($s === null || $s === '') return '';
    $len = strlen($s);
    if ($len <= 2) return str_repeat('*', $len);
    return substr($s, 0, 1) . str_repeat('*', max(0, $len - 2)) . substr($s, -1);
}

$env = wf_env('WHF_ENV', $GLOBALS['isLocalhost'] ? 'local' : 'prod');
$currentCfg = wf_get_db_config('current');

$printCfg = $currentCfg;
$printCfg['pass'] = mask($printCfg['pass'] ?? '');

echo "== WhimsicalFrog DB Smoke Test ==\n";
echo "Env (WHF_ENV): {$env}\n";
if (isset($printCfg['socket']) && $printCfg['socket']) {
    echo "Resolved Config: host(unix_socket)={$printCfg['socket']}, db={$printCfg['db']}, user={$printCfg['user']}, port=" . ($printCfg['port'] ?? 'n/a') . "\n";
} else {
    echo "Resolved Config: host={$printCfg['host']}, db={$printCfg['db']}, user={$printCfg['user']}, port=" . ($printCfg['port'] ?? 'n/a') . "\n";
}
echo "Password (masked): {$printCfg['pass']}\n";

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->query("SELECT VERSION() AS version, DATABASE() AS dbname");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Connection: OK\n";
    echo "MySQL Version: " . ($row['version'] ?? 'unknown') . "\n";
    echo "Current DB: " . ($row['dbname'] ?? 'unknown') . "\n";

    // Quick table count (non-fatal)
    try {
        $dbName = $currentCfg['db'];
        $stmt2 = $pdo->query("SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = '" . addslashes($dbName) . "'");
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        echo "Tables: " . ($row2['table_count'] ?? 'n/a') . "\n";
    } catch (Throwable $e) {
        echo "Tables: n/a (" . $e->getMessage() . ")\n";
    }

    exit(0);
} catch (Throwable $e) {
    echo "Connection: FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
