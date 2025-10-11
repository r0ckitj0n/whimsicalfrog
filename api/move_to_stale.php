<?php
// api/move_to_stale.php
// Moves a provided list of repo-relative files to backups/stale/ preserving paths.
// POST JSON: { files: ["images/foo.png", ...] }
// Returns JSON { success, moved: [], errors: [] }

header('Content-Type: application/json');
$ROOT = dirname(__DIR__);

try {
    // Auth guard if present
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

    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($payload)) $payload = [];
    $files = isset($payload['files']) && is_array($payload['files']) ? $payload['files'] : [];

    $repo = realpath($ROOT);
    $backupRoot = $repo . '/backups/stale';
    if (!is_dir($backupRoot) && !@mkdir($backupRoot, 0775, true)) {
        throw new Exception('Failed to create backups/stale');
    }

    $moved = [];
    $errors = [];
    foreach ($files as $rel) {
        $rel = ltrim((string)$rel, '/');
        if ($rel === '' || strpos($rel, '..') !== false) { $errors[] = [ 'file' => $rel, 'error' => 'invalid path' ]; continue; }
        $abs = $repo . '/' . $rel;
        if (!file_exists($abs) || !is_file($abs)) { $errors[] = [ 'file' => $rel, 'error' => 'not found' ]; continue; }
        $dest = $backupRoot . '/' . $rel;
        $destDir = dirname($dest);
        if (!is_dir($destDir) && !@mkdir($destDir, 0775, true)) { $errors[] = [ 'file' => $rel, 'error' => 'mkdir failed' ]; continue; }
        // prefer git mv when possible
        $gitTracked = false;
        $cmd = sprintf('git -C %s ls-files --error-unmatch %s 2>/dev/null', escapeshellarg($repo), escapeshellarg($rel));
        exec($cmd, $out, $code);
        $gitTracked = ($code === 0);
        if ($gitTracked) {
            $cmd = sprintf('git -C %s mv -f %s %s 2>/dev/null', escapeshellarg($repo), escapeshellarg($rel), escapeshellarg($dest));
            exec($cmd, $out2, $code2);
            if ($code2 !== 0) {
                // fallback
                if (!@rename($abs, $dest)) { $errors[] = [ 'file' => $rel, 'error' => 'move failed' ]; continue; }
            }
        } else {
            if (!@rename($abs, $dest)) { $errors[] = [ 'file' => $rel, 'error' => 'move failed' ]; continue; }
        }
        $moved[] = $rel;
    }

    echo json_encode([ 'success' => true, 'moved' => $moved, 'errors' => $errors ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([ 'success' => false, 'error' => $e->getMessage() ]);
}
