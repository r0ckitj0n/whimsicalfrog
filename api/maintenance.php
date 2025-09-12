<?php
// Secure maintenance endpoint(s) for routine tasks like pruning old sessions.
// Usage (example):
//   GET /api/maintenance.php?action=prune_sessions&days=2&admin_token=whimsical_admin_2024
// Response: {"success":true,"action":"prune_sessions","deleted":N}

declare(strict_types=1);

header('Content-Type: application/json');

$root = dirname(__DIR__);
require_once $root . '/api/config.php';
require_once $root . '/includes/secret_store.php';

// Token handling via Secret Store (rotatable)
function maintenance_generate_token(): string {
    return bin2hex(random_bytes(24)); // 48 hex chars
}

function maintenance_get_token(): string {
    $key = 'maintenance_admin_token';
    $tok = secret_get($key);
    if (!$tok) {
        $tok = maintenance_generate_token();
        secret_set($key, $tok);
    }
    return $tok;
}

function maintenance_log_file(): string {
    return dirname(__DIR__) . '/logs/maintenance.log';
}

function maintenance_log($event, array $data = []): void {
    $logDir = dirname(maintenance_log_file());
    if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
    $entry = [
        'ts' => date('c'),
        'event' => (string)$event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'data' => $data,
    ];
    @file_put_contents(maintenance_log_file(), json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

$provided = $_REQUEST['admin_token'] ?? '';
$expected = maintenance_get_token();
$legacy = 'whimsical_admin_2024'; // temporary backward-compatibility
if (!hash_equals($expected, (string)$provided)) {
    if ($provided !== '' && hash_equals($legacy, (string)$provided)) {
        header('X-Legacy-Token-Used: 1');
        maintenance_log('auth.legacy_token_used');
        // allow but encourage rotation; log handled above
    } else {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Forbidden',
        ]);
        exit;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function json_ok(array $data = []): void {
    echo json_encode(array_merge(['success' => true], $data));
}

function json_fail(string $message, array $data = [], int $code = 400): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => false, 'error' => $message], $data));
}

switch ($action) {
    case 'prune_sessions':
        $days = isset($_REQUEST['days']) ? max(0, (int)$_REQUEST['days']) : 2;
        $sessionDir = $root . '/sessions';

        if (!is_dir($sessionDir)) {
            json_ok(['action' => 'prune_sessions', 'deleted' => 0, 'note' => 'sessions directory not found']);
            exit;
        }

        // Safety: only operate within the expected sessions directory
        $sessionDirReal = realpath($sessionDir);
        if ($sessionDirReal === false || !is_dir($sessionDirReal)) {
            json_fail('Invalid sessions directory');
            exit;
        }

        $now = time();
        $threshold = $now - ($days * 86400);
        $deleted = 0;

        $dir = opendir($sessionDirReal);
        if ($dir === false) {
            json_fail('Unable to open sessions directory');
            exit;
        }

        while (($entry = readdir($dir)) !== false) {
            if ($entry === '.' || $entry === '..') { continue; }
            // Only target standard PHP session files
            if (strpos($entry, 'sess_') !== 0) { continue; }
            $path = $sessionDirReal . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path)) { continue; }
            $mtime = @filemtime($path);
            if ($mtime === false) { continue; }
            if ($mtime < $threshold) {
                if (@unlink($path)) {
                    $deleted++;
                }
            }
        }
        closedir($dir);

        // Log a summary for auditing (Logger + file)
        if (class_exists('Logger')) {
            Logger::info('maintenance.prune_sessions', [
                'deleted' => $deleted,
                'days' => $days,
                'session_dir' => $sessionDirReal,
            ]);
        }
        maintenance_log('prune_sessions', [
            'deleted' => $deleted,
            'days' => $days,
            'session_dir' => $sessionDirReal,
        ]);

        json_ok(['action' => 'prune_sessions', 'deleted' => $deleted, 'days' => $days]);
        break;

    default:
        json_fail('Unknown or missing action', ['allowed' => ['prune_sessions']], 400);
        break;
}
