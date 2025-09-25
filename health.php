<?php
header('Content-Type: text/plain; charset=utf-8');

echo "WF Health Check\n";
echo "PHP: " . PHP_VERSION . "\n";

$ok = true;

try {
    require_once __DIR__ . '/api/config.php';
    echo "config.php: OK\n";
} catch (Throwable $e) {
    echo "config.php: ERROR: " . $e->getMessage() . "\n";
    $ok = false;
}

try {
    require_once __DIR__ . '/includes/database.php';
    echo "includes/database.php: OK\n";
} catch (Throwable $e) {
    echo "includes/database.php: ERROR: " . $e->getMessage() . "\n";
    $ok = false;
}

// Try DB connection in a guarded block
try {
    if (class_exists('Database')) {
        // Prefer creating a separate lightweight connection using the current config to avoid side-effects
        if (function_exists('wf_get_db_config')) {
            $cfg = wf_get_db_config('current');
            $pdo = Database::createConnection(
                $cfg['host'] ?? 'localhost',
                $cfg['db'] ?? 'db',
                $cfg['user'] ?? 'user',
                $cfg['pass'] ?? '',
                (int)($cfg['port'] ?? 3306),
                $cfg['socket'] ?? null
            );
        } else {
            // Fallback: use the singleton
            $pdo = Database::getInstance();
        }
        $stmt = $pdo->query('SELECT 1');
        $stmt->fetch();
        echo "database: OK (simple query)\n";
    } else {
        echo "database: class not found\n";
        $ok = false;
    }
} catch (Throwable $e) {
    echo "database: ERROR: " . $e->getMessage() . "\n";
    $ok = false;
}

// Check manifest readability and JSON
try {
    $man = __DIR__ . '/dist/.vite/manifest.json';
    if (is_file($man)) {
        $json = file_get_contents($man);
        $data = json_decode($json, true);
        if (is_array($data)) {
            echo "manifest: OK\n";
        } else {
            echo "manifest: INVALID JSON\n";
        }
    } else {
        echo "manifest: MISSING\n";
    }
} catch (Throwable $e) {
    echo "manifest: ERROR: " . $e->getMessage() . "\n";
}

http_response_code($ok ? 200 : 500);
