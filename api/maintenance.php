<?php

// Secure maintenance endpoint(s) for routine tasks like pruning old sessions.
// Prefer POST + X-Maintenance-Token header (or admin_token in POST body). Do not put tokens in query strings.
// Usage:
//   curl -sS -X POST 'https://whimsicalfrog.us/api/maintenance.php?action=prune_sessions' \
//     -H 'X-Maintenance-Token: <token>' -d 'days=2'

declare(strict_types=1);

header('Content-Type: application/json');

$root = dirname(__DIR__);
require_once $root . '/api/config.php';
require_once $root . '/includes/secret_store.php';

const MAINTENANCE_TOKEN_KEY = 'maintenance_admin_token';

function maintenance_generate_token(): string
{
    return bin2hex(random_bytes(24)); // 48 hex chars
}

/** True when secret_store value is a password_hash() digest. */
function maintenance_is_hash(string $stored): bool
{
    // password_get_info is the reliable detector for PHP password_* digests.
    $info = password_get_info($stored);
    return isset($info['algo']) && $info['algo'] !== null && $info['algo'] !== 0;
}

/**
 * Verify a provided bearer/admin token against the rotatable secret.
 * Prefer storing password_hash() digests; plaintext is accepted only during migration.
 */
function maintenance_token_valid(string $provided): bool
{
    if ($provided === '') {
        return false;
    }
    $stored = secret_get(MAINTENANCE_TOKEN_KEY);
    if (!is_string($stored) || $stored === '') {
        return false;
    }
    if (maintenance_is_hash($stored)) {
        return password_verify($provided, $stored);
    }
    // Legacy plaintext secret (pre-hash migration)
    return hash_equals($stored, $provided);
}

/** Rotate: store only a hash server-side; return the one-time plaintext to the caller. */
function maintenance_rotate_token(): string
{
    $plain = maintenance_generate_token();
    $hash = password_hash($plain, PASSWORD_DEFAULT);
    if ($hash === false || !secret_set(MAINTENANCE_TOKEN_KEY, $hash)) {
        throw new RuntimeException('Failed to persist maintenance token hash');
    }
    return $plain;
}

function maintenance_ensure_token_exists(): void
{
    $stored = secret_get(MAINTENANCE_TOKEN_KEY);
    if (is_string($stored) && $stored !== '') {
        return;
    }
    // Bootstrap: store a hash only (plaintext is not retained server-side).
    maintenance_rotate_token();
}

function maintenance_log_file(): string
{
    return dirname(__DIR__) . '/logs/maintenance.log';
}

function maintenance_log($event, array $data = []): void
{
    $logDir = dirname(maintenance_log_file());
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $entry = [
        'ts' => date('c'),
        'event' => (string)$event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'ua' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'data' => $data,
    ];
    @file_put_contents(maintenance_log_file(), json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/** Read php://input once (stream is not rewindable). */
function maintenance_json_body(): array
{
    static $parsed = null;
    if ($parsed !== null) {
        return $parsed;
    }
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') {
        $parsed = [];
        return $parsed;
    }
    $json = json_decode($raw, true);
    $parsed = is_array($json) ? $json : [];
    return $parsed;
}

function maintenance_read_provided_token(): string
{
    // Prefer header, then POST body / JSON — never require query-string tokens.
    $header = $_SERVER['HTTP_X_MAINTENANCE_TOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        return $header;
    }
    if (isset($_POST['admin_token']) && is_string($_POST['admin_token']) && $_POST['admin_token'] !== '') {
        return $_POST['admin_token'];
    }
    $json = maintenance_json_body();
    if (isset($json['admin_token']) && is_string($json['admin_token']) && $json['admin_token'] !== '') {
        return $json['admin_token'];
    }
    // Last-resort legacy query param (discouraged; kept so old cron fails closed after rotation)
    if (isset($_GET['admin_token']) && is_string($_GET['admin_token'])) {
        return $_GET['admin_token'];
    }
    return '';
}

maintenance_ensure_token_exists();

$provided = maintenance_read_provided_token();
if (!maintenance_token_valid($provided)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Forbidden',
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($action === '' ) {
    $jsonAction = maintenance_json_body();
    if (isset($jsonAction['action']) && is_string($jsonAction['action'])) {
        $action = $jsonAction['action'];
    }
}
$allowedActions = ['prune_sessions'];
if (!in_array($action, $allowedActions, true)) {
    json_fail('Unknown or missing action', ['allowed' => $allowedActions], 400);
    exit;
}

function json_ok(array $data = []): void
{
    echo json_encode(array_merge(['success' => true], $data));
}

function json_fail(string $message, array $data = [], int $code = 400): void
{
    http_response_code($code);
    echo json_encode(array_merge(['success' => false, 'error' => $message], $data));
}

switch ($action) {
    case 'prune_sessions':
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            json_fail('Method not allowed', [], 405);
            exit;
        }
        $days = 2;
        if (isset($_POST['days'])) {
            $days = max(0, min(30, (int)$_POST['days']));
        } else {
            $json = maintenance_json_body();
            if (isset($json['days'])) {
                $days = max(0, min(30, (int)$json['days']));
            }
        }
        $sessionDir = '/tmp/whimsicalfrog_sessions';

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
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            // Only target standard PHP session files
            if (strpos($entry, 'sess_') !== 0) {
                continue;
            }
            $path = $sessionDirReal . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path)) {
                continue;
            }
            $mtime = @filemtime($path);
            if ($mtime === false) {
                continue;
            }
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
        json_fail('Unknown or missing action', ['allowed' => $allowedActions], 400);
        break;
}
