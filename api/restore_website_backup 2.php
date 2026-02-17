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

function ini_size_to_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    return match ($unit) {
        'g' => (int)($number * 1024 * 1024 * 1024),
        'm' => (int)($number * 1024 * 1024),
        'k' => (int)($number * 1024),
        default => (int)$number,
    };
}

function map_upload_error_code(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE => 'file exceeds upload_max_filesize (' . (string)ini_get('upload_max_filesize') . ')',
        UPLOAD_ERR_FORM_SIZE => 'file exceeds MAX_FILE_SIZE from form',
        UPLOAD_ERR_PARTIAL => 'file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'no file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'missing temporary upload directory',
        UPLOAD_ERR_CANT_WRITE => 'failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'a PHP extension stopped the upload',
        default => 'unknown upload error code ' . $code,
    };
}

function create_pre_restore_backup(string $projectRoot, string $backupRoot): array
{
    if (!is_dir($backupRoot) && !mkdir($backupRoot, 0755, true)) {
        throw new RuntimeException('Failed to create backups directory');
    }

    $timestamp = date('Y-m-d_H-i-s');
    $backupName = 'whimsicalfrog_backup_' . $timestamp . '.tar.gz';
    $backupPath = $backupRoot . '/' . $backupName;

    $command = 'tar -czf ' . escapeshellarg($backupPath)
        . ' --exclude=backups --exclude=node_modules --exclude=.git --exclude=.DS_Store --exclude=logs --exclude=.env .';

    $out = [];
    $code = 0;
    exec('cd ' . escapeshellarg($projectRoot) . ' && ' . $command . ' 2>&1', $out, $code);

    if ($code !== 0 || !is_file($backupPath)) {
        throw new RuntimeException('Failed to create pre-restore website backup: ' . implode("\n", $out));
    }

    return [
        'filename' => $backupName,
        'path' => $backupPath,
    ];
}

exec('command -v tar >/dev/null 2>&1', $tarCheckOut, $tarCheckCode);
if ($tarCheckCode !== 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'tar command is not available on this server']);
    exit;
}

$projectRoot = realpath(dirname(__DIR__));
$backupRoot = realpath($projectRoot . '/backups') ?: ($projectRoot . '/backups');

$isMultipart = str_contains(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'multipart/form-data');
$input = [];
if ($isMultipart) {
    $input = $_POST;
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }
}

$confirmRestore = !empty($input['confirm_restore']);
if (!$confirmRestore) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Restore confirmation is required']);
    exit;
}

$candidate = '';
$normalized = '';

if (isset($_FILES['backup_file']) && isset($_FILES['backup_file']['error']) && (int)$_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
    $name = (string)($_FILES['backup_file']['name'] ?? '');
    if (!preg_match('/\.tar\.gz$/i', $name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Uploaded website backup must be a .tar.gz file']);
        exit;
    }
    $candidate = (string)($_FILES['backup_file']['tmp_name'] ?? '');
    $normalized = 'upload:' . basename($name);
} elseif (isset($_FILES['backup_file']) && isset($_FILES['backup_file']['error']) && (int)$_FILES['backup_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Website backup upload failed: ' . map_upload_error_code((int)$_FILES['backup_file']['error'])]);
    exit;
} else {
    $file = (string)($input['file'] ?? '');
    if ($file === '') {
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
        if ($contentLength > 0) {
            $postMaxBytes = ini_size_to_bytes((string)ini_get('post_max_size'));
            $uploadMaxBytes = ini_size_to_bytes((string)ini_get('upload_max_filesize'));
            if (($postMaxBytes > 0 && $contentLength > $postMaxBytes) || ($uploadMaxBytes > 0 && $contentLength > $uploadMaxBytes)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Website backup upload exceeded PHP limits (post_max_size=' . (string)ini_get('post_max_size') . ', upload_max_filesize=' . (string)ini_get('upload_max_filesize') . ').',
                ]);
                exit;
            }
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing backup file path']);
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

    $candidate = realpath($projectRoot . '/' . $normalized);
    if (!$projectRoot || !$candidate || !is_file($candidate)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Backup file not found']);
        exit;
    }

    $safeBackupRoot = realpath($backupRoot);
    if (!$safeBackupRoot || strpos($candidate, $safeBackupRoot) !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Access denied to backup path']);
        exit;
    }
}

if (!$candidate || !is_file($candidate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Backup file is missing']);
    exit;
}

$start = microtime(true);

exec('tar -tzf ' . escapeshellarg($candidate) . ' 2>&1', $listOutput, $listCode);
if ($listCode !== 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Unable to inspect website backup archive',
        'details' => implode("\n", $listOutput),
    ]);
    exit;
}

if (count($listOutput) < 1) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Website backup archive appears empty',
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

try {
    $preRestoreBackup = create_pre_restore_backup($projectRoot, $backupRoot);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create pre-restore safety backup: ' . $e->getMessage(),
    ]);
    exit;
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
    'pre_restore_backup' => $preRestoreBackup['filename'] ?? null,
    'extracted_files' => count($listOutput),
    'restore_time_seconds' => round(microtime(true) - $start, 2),
], JSON_UNESCAPED_SLASHES);
