<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

AuthHelper::requireAdmin(403, 'Admin access required');
header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$projectRoot = dirname(__DIR__);
$backupRoot = realpath($projectRoot . '/backups');
if (!$backupRoot || !is_dir($backupRoot)) {
    echo json_encode(['success' => true, 'files' => []], JSON_UNESCAPED_SLASHES);
    exit;
}

$files = [];

$appendIfMatch = static function (string $absPath, string $relPath, string $name) use (&$files): void {
    if (!is_file($absPath)) {
        return;
    }

    if (preg_match('/^whimsicalfrog_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/', $name)) {
        $files[] = [
            'name' => $name,
            'size' => (int) (filesize($absPath) ?: 0),
            'mtime' => (int) (filemtime($absPath) ?: 0),
            'rel' => $relPath,
            'type' => 'website',
        ];
        return;
    }

    if (preg_match('/^whimsicalfrog_database_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql(\.gz)?$/', $name) || preg_match('/\.sql(\.gz)?$/i', $name)) {
        $files[] = [
            'name' => $name,
            'size' => (int) (filesize($absPath) ?: 0),
            'mtime' => (int) (filemtime($absPath) ?: 0),
            'rel' => $relPath,
            'type' => 'database',
        ];
    }
};

$entries = @scandir($backupRoot) ?: [];
foreach ($entries as $entry) {
    if ($entry === '.' || $entry === '..') {
        continue;
    }
    $absPath = $backupRoot . DIRECTORY_SEPARATOR . $entry;
    $appendIfMatch($absPath, 'backups/' . $entry, $entry);
}

$sqlDir = $backupRoot . DIRECTORY_SEPARATOR . 'sql';
if (is_dir($sqlDir)) {
    $sqlEntries = @scandir($sqlDir) ?: [];
    foreach ($sqlEntries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $absPath = $sqlDir . DIRECTORY_SEPARATOR . $entry;
        $appendIfMatch($absPath, 'backups/sql/' . $entry, $entry);
    }
}

usort($files, static fn(array $a, array $b): int => ($b['mtime'] <=> $a['mtime']));

echo json_encode(['success' => true, 'files' => $files], JSON_UNESCAPED_SLASHES);
