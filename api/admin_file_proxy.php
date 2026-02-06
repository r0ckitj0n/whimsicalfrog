<?php

// Admin-only file proxy for documentation/ and reports/
// Streams files if the user is an admin and the path is within allowed bases.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

AuthHelper::requireAdmin(403, 'Admin access required');

$allowedBases = [
    realpath(__DIR__ . '/../documentation'),
    realpath(__DIR__ . '/../reports'),
    realpath(__DIR__ . '/../backups'),
    realpath(__DIR__ . '/../logs'),
];

// Optional streaming mode for large files (e.g. logs)
$mode = isset($_GET['mode']) ? (string)$_GET['mode'] : '';
$maxBytes = isset($_GET['max']) ? (int)$_GET['max'] : 0;
if ($maxBytes <= 0) {
    $maxBytes = 1024 * 1024; // Default: 1MB tail
}

$rel = isset($_GET['path']) ? (string)$_GET['path'] : '';
if ($rel === '') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing path parameter']);
    exit;
}

// Normalize and prevent traversal
$rel = str_replace('\\', '/', $rel);
$rel = ltrim($rel, '/');
$target = realpath(__DIR__ . '/../' . $rel);
if ($target === false || !is_file($target)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'File not found']);
    exit;
}

// Ensure target is under one of the allowed bases
$allowed = false;
foreach ($allowedBases as $base) {
    if ($base && strpos($target, $base) === 0) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Stream with content-type
$finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
$mime = $finfo ? finfo_file($finfo, $target) : 'application/octet-stream';
if ($finfo) {
    finfo_close($finfo);
}
$basename = basename($target);

// Determine file size and, for tail mode, the starting offset
$fileSize = filesize($target);
$startOffset = 0;
if ($mode === 'tail' && $fileSize > $maxBytes) {
    $startOffset = $fileSize - $maxBytes;
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . addslashes($basename) . '"');
header('Content-Length: ' . ($fileSize - $startOffset));
header('X-Content-Type-Options: nosniff');

$fp = fopen($target, 'rb');
if ($fp) {
    if ($startOffset > 0) {
        // Seek to tail position for large files; ignore errors and fall back to full stream
        @fseek($fp, $startOffset, SEEK_SET);
    }
    while (!feof($fp)) {
        echo fread($fp, 8192);
        @ob_flush();
        flush();
    }
    fclose($fp);
}
exit;
