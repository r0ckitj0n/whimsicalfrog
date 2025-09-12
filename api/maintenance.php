<?php
// Secure maintenance endpoint(s) for routine tasks like pruning old sessions.
// Usage (example):
//   GET /api/maintenance.php?action=prune_sessions&days=2&admin_token=whimsical_admin_2024
// Response: {"success":true,"action":"prune_sessions","deleted":N}

declare(strict_types=1);

header('Content-Type: application/json');

$root = dirname(__DIR__);
require_once $root . '/api/config.php';

$adminToken = $_REQUEST['admin_token'] ?? '';
if ($adminToken !== 'whimsical_admin_2024') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Forbidden',
    ]);
    exit;
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

        // Log a summary for auditing
        if (class_exists('Logger')) {
            Logger::info('maintenance.prune_sessions', [
                'deleted' => $deleted,
                'days' => $days,
                'session_dir' => $sessionDirReal,
            ]);
        }

        json_ok(['action' => 'prune_sessions', 'deleted' => $deleted, 'days' => $days]);
        break;

    default:
        json_fail('Unknown or missing action', ['allowed' => ['prune_sessions']], 400);
        break;
}
