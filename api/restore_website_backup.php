<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

AuthHelper::requireAdmin(403, 'Admin access required');

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$file = (string)($input['file'] ?? '');
$confirmRestore = !empty($input['confirm_restore']);

if ($file === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing backup file path']);
    exit;
}

if (!$confirmRestore) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Restore confirmation is required']);
    exit;
}

$normalized = str_replace('\\', '/', $file);
if (strpos($normalized, '../') !== false || strpos($normalized, "\0") !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid backup file path']);
    exit;
}

if (!preg_match('/^backups\/whimsicalfrog_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/', $normalized)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid website backup filename']);
    exit;
}

$projectRoot = realpath(dirname(__DIR__));
$backupRoot = realpath($projectRoot . '/backups');
$candidate = realpath($projectRoot . '/' . $normalized);

if (!$projectRoot || !$backupRoot || !$candidate || !is_file($candidate)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Backup file not found']);
    exit;
}

if (strpos($candidate, $backupRoot) !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied to backup path']);
    exit;
}

exec('command -v tar >/dev/null 2>&1', $tarCheckOut, $tarCheckCode);
if ($tarCheckCode !== 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'tar command is not available on this server']);
    exit;
}

$start = microtime(true);

exec(
    'tar -tzf ' . escapeshellarg($candidate) . ' 2>&1',
    $listOutput,
    $listCode
);

if ($listCode !== 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to inspect website backup archive',
        'details' => implode("\n", $listOutput),
    ]);
    exit;
}

foreach ($listOutput as $entry) {
    $trimmed = trim((string)$entry);
    if ($trimmed === '') {
        continue;
    }
    if (str_starts_with($trimmed, '/') || str_contains($trimmed, '../')) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Backup archive contains unsafe paths and cannot be restored',
        ]);
        exit;
    }
}

$extractCmd =
    'tar -xzf ' . escapeshellarg($candidate) .
    ' -C ' . escapeshellarg($projectRoot) .
    ' --no-same-owner --overwrite 2>&1';

exec($extractCmd, $extractOutput, $extractCode);

if ($extractCode !== 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Website restore failed during extraction',
        'details' => implode("\n", $extractOutput),
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Website backup restored successfully.',
    'restored_file' => $normalized,
    'extracted_files' => count($listOutput),
    'restore_time_seconds' => round(microtime(true) - $start, 2),
], JSON_UNESCAPED_SLASHES);
