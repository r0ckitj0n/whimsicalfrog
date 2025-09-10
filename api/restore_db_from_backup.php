<?php
// api/restore_db_from_backup.php
// Securely restore a SQL dump (located under backups/sql/) into the current environment DB.
// Usage (GET or POST):
//   /api/restore_db_from_backup.php?file=backups/sql/local_db_dump_2025-09-10_15-50-00.sql.gz&admin_token=whimsical_admin_2024
// Notes:
// - Requires admin token and only accepts files under backups/sql/.
// - Streams SQL via db_import_sql.php which batches statements.

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/config.php';

$token = $_GET['admin_token'] ?? $_POST['admin_token'] ?? '';
if ($token !== AuthHelper::ADMIN_TOKEN) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$filename = $_GET['file'] ?? $_POST['file'] ?? '';
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
$filterNeedle = '';
if ($filterTable !== '') {
    // Match backticked table references like `items`
    $filterNeedle = '`' . str_replace('`', '``', $filterTable) . '`';
}

// Allow importer and set target file
define('WF_IMPORT_ALLOWED', true);
$_GET['file'] = $filename; // consumed by db_import_sql.php

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
        if ($data === false) break;
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

$executed = 0;
$errors = 0;
$errorSamples = [];

ob_start();
require_once dirname(__DIR__) . '/db_import_sql.php';
$output = ob_get_clean();

// Stream and execute the SQL file
$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

$buffer = '';
while ($line = fgets(STDIN)) {
    $buffer .= $line;
    if (substr($buffer, -1) === ';') {
        $stmt = substr($buffer, 0, -1);
        // Apply optional table filter: execute only when statement references the table
        if ($filterNeedle !== '' && stripos($stmt, $filterNeedle) === false) {
            $buffer = '';
            continue;
        }
        try {
            $pdo->exec($stmt);
            $executed++;
        } catch (Exception $e) {
            $errors++;
            if (count($errorSamples) < 5) {
                $preview = substr($stmt, 0, 240);
                $errorSamples[] = [ 'error' => $e->getMessage(), 'stmt_preview' => $preview ];
            }
            // Continue importing remaining statements
        }
        $buffer = '';
    }
}

// Cleanup temp file if created
if ($decompressed) {
    @unlink(dirname(__DIR__) . '/' . $tempPath);
}

echo json_encode([
    'ok' => true,
    'message' => "Import complete. Errors: $errors",
    'executed' => $executed,
    'errors' => $errors,
    'error_samples' => $errorSamples,
    'filter_table' => $filterTable,
], JSON_UNESCAPED_SLASHES);
