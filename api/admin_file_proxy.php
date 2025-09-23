<?php

// Admin-only file proxy for documentation/ and reports/
// Streams files if the user is an admin and the path is within allowed bases.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

AuthHelper::requireAdmin(403, 'Admin access required');

$allowedBases = [
    realpath(__DIR__ . '/../documentation'),
    realpath(__DIR__ . '/../reports'),
];

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

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . addslashes($basename) . '"');
header('Content-Length: ' . filesize($target));
header('X-Content-Type-Options: nosniff');

$fp = fopen($target, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 8192);
        @ob_flush();
        flush();
    }
    fclose($fp);
}
exit;
