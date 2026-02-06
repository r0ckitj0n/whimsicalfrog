<?php
require_once __DIR__ . '/config.php';

// Change working directory to project root from api/ directory
chdir(dirname(__DIR__));

if (!isset($_GET['file'])) {
    http_response_code(400);
    die("File parameter missing.");
}

$filename = basename($_GET['file']); // Protect against path traversal (e.g. ../../../etc/passwd)
$filepath = "backups/" . $filename;

if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    die("The requested backup file was not found on the server. Path: " . htmlspecialchars($filepath));
}

// Basic security check: ensure the file is actually inside the backups directory
$realPath = realpath($filepath);
$backupsDir = realpath('backups');
if (strpos($realPath, $backupsDir) !== 0) {
    http_response_code(403);
    die("Security violation: Access denied to this path.");
}

$ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
// Handle double extension for .tar.gz
if (strpos($_GET['file'], '.tar.gz') !== false) {
    $mime = 'application/x-gzip';
} else {
    $mimes = [
        'sql' => 'application/sql',
        'gz' => 'application/x-gzip',
        'zip' => 'application/zip',
        'tar' => 'application/x-tar'
    ];
    $mime = $mimes[$ext] ?? 'application/octet-stream';
}

// Send the file
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));

// Clear output buffer to avoid any data corruption (e.g. if config.php had trailing spaces)
if (ob_get_level())
    ob_end_clean();

readfile($filepath);
exit;
