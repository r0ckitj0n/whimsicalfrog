<?php
// api/db_smoke_test.php
// Browser/CI runnable DB smoke test

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

function wf_mask_secret(string $s): string {
    if ($s === '') return '';
    $len = strlen($s);
    if ($len <= 2) return str_repeat('*', $len);
    return substr($s, 0, 1) . str_repeat('*', max(0, $len - 2)) . substr($s, -1);
}

try {
    $env = wf_env('WHF_ENV', $GLOBALS['isLocalhost'] ? 'local' : 'prod');
    $target = $_GET['target'] ?? 'current'; // current|local|live
    if (!in_array($target, ['current','local','live'], true)) {
        $target = 'current';
    }

    $cfg = wf_get_db_config($target);

    // Allow overrides via query for CI experimentation (host,db,user,pass,port)
    $cfg['host'] = $_GET['host'] ?? $cfg['host'] ?? '127.0.0.1';
    $cfg['db']   = $_GET['db']   ?? $cfg['db']   ?? '';
    $cfg['user'] = $_GET['user'] ?? $cfg['user'] ?? '';
    $cfg['pass'] = $_GET['pass'] ?? $cfg['pass'] ?? '';
    $cfg['port'] = isset($_GET['port']) ? (int)$_GET['port'] : ($cfg['port'] ?? 3306);
    $cfg['socket'] = $_GET['socket'] ?? ($cfg['socket'] ?? null);

    // Choose connection
    if ($target === 'current') {
        $pdo = Database::getInstance();
    } else {
        $pdo = Database::createConnection(
            $cfg['host'],
            $cfg['db'],
            $cfg['user'],
            $cfg['pass'],
            $cfg['port'] ?? 3306,
            $cfg['socket'] ?? null,
            [ PDO::ATTR_TIMEOUT => 5 ]
        );
    }

    $metaStmt = $pdo->query("SELECT VERSION() AS version, DATABASE() AS dbname");
    $meta = $metaStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $tables = null;
    try {
        $dbName = $cfg['db'] ?? $meta['dbname'] ?? '';
        if ($dbName) {
            $stmt2 = $pdo->query("SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = '" . addslashes($dbName) . "'");
            $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
            $tables = (int)($row2['table_count'] ?? 0);
        }
    } catch (Throwable $e) {
        $tables = null;
    }

    echo json_encode([
        'ok' => true,
        'env' => $env,
        'target' => $target,
        'config' => [
            'host' => $cfg['host'] ?? null,
            'db' => $cfg['db'] ?? null,
            'user' => $cfg['user'] ?? null,
            'port' => $cfg['port'] ?? null,
            'socket' => $cfg['socket'] ?? null,
            'pass_masked' => wf_mask_secret((string)($cfg['pass'] ?? '')),
        ],
        'connection' => 'OK',
        'mysql_version' => $meta['version'] ?? null,
        'current_db' => $meta['dbname'] ?? null,
        'tables' => $tables,
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'trace' => (getenv('WF_DEBUG_SMOKE') === '1') ? $e->getTraceAsString() : null,
    ], JSON_PRETTY_PRINT);
}
