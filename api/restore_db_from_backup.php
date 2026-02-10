<?php

// api/restore_db_from_backup.php
// Securely restore a SQL dump (located under backups/sql/) into the current environment DB.
// Usage (GET or POST):
//   /api/restore_db_from_backup.php?file=backups/sql/local_db_dump_2025-09-10_15-50-00.sql.gz&admin_token=whimsical_admin_2024
// Notes:
// - Requires admin token and only accepts files under backups/sql/.
// - Streams SQL via db_import_sql.php which batches statements.

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/config.php';

function wf_restore_has_valid_token(): bool
{
    $provided = $_GET['admin_token'] ?? $_POST['admin_token'] ?? '';
    if ($provided === '') {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        if (is_array($jsonInput)) {
            $provided = $jsonInput['admin_token'] ?? '';
        }
    }

    if ($provided === '') {
        return false;
    }

    $expected = getenv('WF_ADMIN_TOKEN') ?: '';
    if ($expected === '' && defined('WF_ADMIN_TOKEN') && WF_ADMIN_TOKEN) {
        $expected = WF_ADMIN_TOKEN;
    }
    if ($expected === '' && defined('AuthHelper::ADMIN_TOKEN')) {
        $expected = AuthHelper::ADMIN_TOKEN;
    }

    return $expected !== '' && hash_equals($expected, $provided);
}

// Require admin via session unless a valid admin token is supplied
if (!wf_restore_has_valid_token()) {
    AuthHelper::requireAdmin();
}

$filename = $_GET['file'] ?? $_POST['file'] ?? '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}
if (!$filename) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing file parameter']);
    exit;
}

// Normalize and restrict to backups/sql/
$filename = str_replace('\\', '/', $filename);
if (strpos($filename, '../') !== false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid path']);
    exit;
}
if (strpos($filename, 'backups/sql/') !== 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'File must be under backups/sql/']);
    exit;
}

// Optional: filter statements to a specific table (diagnostics)
$filterTable = isset($_GET['filter_table']) ? trim($_GET['filter_table']) : '';
$filterMode = isset($_GET['filter_mode']) ? trim($_GET['filter_mode']) : '';
$filterNeedle = '';
if ($filterTable !== '') {
    // Match backticked table references like `items`
    $filterNeedle = '`' . str_replace('`', '``', $filterTable) . '`';
}

// Allow importer and set target file
define('WF_IMPORT_ALLOWED', true);
$_GET['file'] = $filename; // consumed by db_import_sql.php
if ($filterTable !== '') {
    $_GET['filter_table'] = $filterTable;
}
if ($filterMode !== '') {
    $_GET['filter_mode'] = $filterMode;
}
$_GET['as_json'] = '1';

// If gzip, create a temporary decompressed file path for importer
$absPath = dirname(__DIR__) . '/' . $filename;
if (!file_exists($absPath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'File not found on server']);
    exit;
}

$decompressed = false;
$tempPath = $filename;
if (preg_match('/\.gz$/i', $filename)) {
    $tmp = sys_get_temp_dir() . '/wf_restore_' . uniqid('', true) . '.sql';
    $gz = @gzopen($absPath, 'rb');
    if ($gz === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to open gzip file']);
        exit;
    }
    $out = @fopen($tmp, 'wb');
    if ($out === false) {
        @gzclose($gz);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to create temp file']);
        exit;
    }
    while (!gzeof($gz)) {
        $data = gzread($gz, 8192);
        if ($data === false) {
            break;
        }
        fwrite($out, $data);
    }
    gzclose($gz);
    fclose($out);
    // Swap in decompressed temp file relative path
    $tempRel = 'backups/sql/' . basename($tmp); // importer resolves from project root
    // Move file into backups/sql for importer
    $destAbs = dirname(__DIR__) . '/' . $tempRel;
    if (!@rename($tmp, $destAbs)) {
        // if rename fails across fs, copy
        if (!@copy($tmp, $destAbs)) {
            @unlink($tmp);
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Failed to place temp SQL file']);
            exit;
        }
        @unlink($tmp);
    }
    $_GET['file'] = $tempRel;
    $decompressed = true;
    $tempPath = $tempRel;
}

ob_start();
require_once dirname(__DIR__) . '/scripts/db/db_import_sql.php';
$importJson = ob_get_clean();

// Cleanup temp file if created
if ($decompressed) {
    @unlink(dirname(__DIR__) . '/' . $tempPath);
}

// Pass through importer JSON (already encoded) if available, else fallback
if ($importJson && ($data = json_decode($importJson, true))) {
    $data['filter_table'] = $filterTable;
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode([
      'ok' => true,
      'message' => 'Import complete.',
      'filter_table' => $filterTable,
    ], JSON_UNESCAPED_SLASHES);
}
if (!preg_match('/^backups\/sql\/[a-zA-Z0-9._-]+\.(sql|sql\.gz)$/', $filename)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid backup filename']);
    exit;
}
