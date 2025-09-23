<?php

// db_smoke_test.php (root)
// Mirror of api/db_smoke_test.php placed at web root to avoid routing issues on some hosts.
// Usage:
//   https://YOUR-DOMAIN/db_smoke_test.php?target=live&admin_token=whimsical_admin_2024

header('Content-Type: application/json');

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/includes/auth_helper.php';

$input = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
}

$adminToken = $_GET['admin_token'] ?? $_POST['admin_token'] ?? ($input['admin_token'] ?? null);
if ($adminToken !== AuthHelper::ADMIN_TOKEN) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden: invalid admin token']);
    exit;
}

$target = $_GET['target'] ?? $_POST['target'] ?? ($input['target'] ?? 'current');
if (!in_array($target, ['current','local','live'], true)) {
    $target = 'current';
}

try {
    if ($target === 'current') {
        $pdo = Database::getInstance();
        $cfg = wf_get_db_config('current');
    } else {
        $cfg = wf_get_db_config($target);
        $pdo = Database::createConnection(
            $cfg['host'] ?? '127.0.0.1',
            $cfg['db'] ?? '',
            $cfg['user'] ?? '',
            $cfg['pass'] ?? '',
            (int)($cfg['port'] ?? 3306),
            $cfg['socket'] ?? null,
            [ PDO::ATTR_TIMEOUT => 5 ]
        );
    }

    $meta = $pdo->query('SELECT VERSION() AS version, DATABASE() AS dbname')->fetch(PDO::FETCH_ASSOC) ?: [];
    $tables = null;
    try {
        $dbName = $cfg['db'] ?? $meta['dbname'] ?? '';
        if ($dbName) {
            $row2 = $pdo->query("SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = '" . addslashes($dbName) . "'")->fetch(PDO::FETCH_ASSOC) ?: [];
            $tables = (int)($row2['table_count'] ?? 0);
        }
    } catch (Throwable $e) {
        $tables = null;
    }

    echo json_encode([
        'ok' => true,
        'target' => $target,
        'config' => [
            'host' => $cfg['host'] ?? null,
            'db' => $cfg['db'] ?? null,
            'user' => $cfg['user'] ?? null,
            'port' => $cfg['port'] ?? null,
            'socket' => $cfg['socket'] ?? null,
        ],
        'mysql_version' => $meta['version'] ?? null,
        'current_db' => $meta['dbname'] ?? null,
        'tables' => $tables,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode([
        'ok' => false,
        'target' => $target,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
