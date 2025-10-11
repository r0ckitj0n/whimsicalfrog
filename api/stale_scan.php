<?php
// api/stale_scan.php
// Runs the stale asset scanner in JSON mode and returns { success, stale: [] }
// POST JSON: { dbw?: 0|1 }

header('Content-Type: application/json');
$ROOT = dirname(__DIR__);

try {
    // Auth guard if available
    $authPath = $ROOT . '/includes/auth.php';
    $authHelperPath = $ROOT . '/includes/auth_helper.php';
    if (file_exists($authPath)) {
        require_once $authPath;
        if (file_exists($authHelperPath)) require_once $authHelperPath;
        if (class_exists('AuthHelper')) {
            if (!AuthHelper::isLoggedIn()) throw new Exception('Not authorized');
        } elseif (function_exists('isLoggedIn')) {
            if (!isLoggedIn()) throw new Exception('Not authorized');
        }
    }

    $in = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($in)) $in = [];
    $dbw = !empty($in['dbw']) ? true : false;

    $cwd = $ROOT;
    $cmd = 'node scripts/maintenance/find_stale_assets.mjs --json';
    if ($dbw) $cmd .= ' --db-whitelist';

    $descriptors = [ 0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w'] ];
    $proc = proc_open($cmd, $descriptors, $pipes, $cwd, [ 'PATH' => getenv('PATH'), 'HOME' => getenv('HOME') ]);
    if (!is_resource($proc)) throw new Exception('Failed to start scan');
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $code = proc_close($proc);

    if ($code !== 0) {
        // The scanner prints text when no stale; still treat as success with empty list
        $json = @json_decode($out, true);
        if (isset($json['stale']) && is_array($json['stale'])) {
            echo json_encode([ 'success' => true, 'stale' => $json['stale'] ]);
            exit;
        }
        echo json_encode([ 'success' => true, 'stale' => [] ]);
        exit;
    }

    $json = @json_decode($out, true);
    if (!is_array($json) || !isset($json['stale'])) $json = [ 'stale' => [] ];
    echo json_encode([ 'success' => true, 'stale' => array_values($json['stale']) ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([ 'success' => false, 'error' => $e->getMessage(), 'stale' => [] ]);
}
