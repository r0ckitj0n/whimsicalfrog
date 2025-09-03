<?php
// db_import_sql.php - server-side SQL importer used by api/sync_db.php
// Security: This script is intended to be required by api/sync_db.php which performs token validation.
// Do not expose this script directly without a guard.

if (php_sapi_name() !== 'cli') {
  // When included via sync endpoint, continue. If accessed directly, block.
  if (!defined('WF_IMPORT_ALLOWED')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}

require_once __DIR__ . '/api/config.php'; // provides $dsn, $user, $pass, $options

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
  http_response_code(500);
  echo 'DB connect failed: ' . $e->getMessage();
  exit;
}

$filename = $_GET['file'] ?? 'whimsicalfrog_sync.sql';
$path = __DIR__ . DIRECTORY_SEPARATOR . $filename;
if (!is_file($path) || !is_readable($path)) {
  http_response_code(404);
  echo 'SQL file not found: ' . htmlspecialchars($filename);
  exit;
}

// Stream the SQL file and execute in batches
$handle = fopen($path, 'r');
if (!$handle) {
  http_response_code(500);
  echo 'Unable to open SQL file';
  exit;
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$buffer = '';
$executed = 0;
$errors = 0;

function shouldExecute($sql) {
  $trim = trim($sql);
  if ($trim === '') return false;
  if (str_starts_with($trim, '--') || str_starts_with($trim, '/*')) return false;
  return substr($trim, -1) === ';';
}

while (!feof($handle)) {
  $line = fgets($handle);
  if ($line === false) break;

  // Skip comments quickly
  $t = ltrim($line);
  if ($t === '' || str_starts_with($t, '--') || str_starts_with($t, '/*')) {
    continue;
  }

  $buffer .= $line;
  if (shouldExecute($buffer)) {
    $stmt = trim($buffer);
    // Remove trailing semicolon for exec
    if (substr($stmt, -1) === ';') {
      $stmt = substr($stmt, 0, -1);
    }
    try {
      $pdo->exec($stmt);
      $executed++;
    } catch (Exception $e) {
      $errors++;
      // For safety, continue importing remaining statements
    }
    $buffer = '';
  }
}

fclose($handle);
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

echo 'Import complete.';
if ($errors > 0) {
  echo " Errors: $errors";
}
