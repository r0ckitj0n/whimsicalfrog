<?php
// scripts/db/extract_table.php
// Usage: php scripts/db/extract_table.php --dump=backups/sql/dev_full_*.sql[.gz] --table=items [--out=backups/sql/items_only.sql]
// Streams a .sql or .sql.gz and writes only the specified table's DROP/CREATE/INSERT statements,
// wrapped with FK disable/enable. Exits non-zero on errors.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$dump = null; $table = null; $out = null;
foreach ($argv as $arg) {
  if (preg_match('/^--dump=(.+)$/', $arg, $m)) $dump = $m[1];
  if (preg_match('/^--table=(.+)$/', $arg, $m)) $table = $m[1];
  if (preg_match('/^--out=(.+)$/', $arg, $m)) $out = $m[1];
}
if (!$dump || !$table) {
  fwrite(STDERR, "Usage: php scripts/db/extract_table.php --dump=PATH --table=TABLE [--out=PATH]\n");
  exit(2);
}
if (!file_exists($dump)) {
  fwrite(STDERR, "Dump not found: $dump\n");
  exit(3);
}
if ($out === null) {
  $ts = date('Y-m-d_H-i-s');
  $base = basename($dump);
  $out = sprintf('backups/sql/%s_only_%s.sql', $table, $ts);
}
$dir = dirname($out);
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
$fhOut = fopen($out, 'w');
if (!$fhOut) { fwrite(STDERR, "Cannot write: $out\n"); exit(4); }

$write = function($s) use ($fhOut) { fwrite($fhOut, $s); };
$write("SET FOREIGN_KEY_CHECKS=0;\n");

$needleDrop = "DROP TABLE IF EXISTS `" . str_replace('`','``',$table) . "`;";
$needleCreate = "CREATE TABLE `" . str_replace('`','``',$table) . "` (";
$needleInsert = "INSERT INTO `" . str_replace('`','``',$table) . "`";

$inCreate = false;

$open = null;
if (preg_match('/\.gz$/i', $dump)) {
  $open = gzopen($dump, 'rb');
  if (!$open) { fwrite(STDERR, "Failed to open gzip: $dump\n"); exit(5); }
  $readLine = function() use ($open) { return gzgets($open); };
  $close = function() use ($open) { gzclose($open); };
} else {
  $open = fopen($dump, 'rb');
  if (!$open) { fwrite(STDERR, "Failed to open: $dump\n"); exit(6); }
  $readLine = function() use ($open) { return fgets($open); };
  $close = function() use ($open) { fclose($open); };
}

while (!feof($open)) {
  $line = $readLine();
  if ($line === false) break;
  $trim = ltrim($line);
  // Drop
  if (strpos($line, $needleDrop) === 0) { $write($line); continue; }
  // Create block start
  if (!$inCreate && strpos($line, $needleCreate) === 0) {
    $inCreate = true;
    $write($line);
    continue;
  }
  if ($inCreate) {
    $write($line);
    // End of CREATE TABLE block (common mysqldump: line starting with ") ENGINE") or a lone ");"
    if (preg_match('/^\) ENGINE/i', $trim) || preg_match('/^\);\s*$/', $trim)) {
      $inCreate = false;
    }
    continue;
  }
  // INSERTs
  if (strpos($line, $needleInsert) === 0) { $write($line); continue; }
}
$close();
$write("\nSET FOREIGN_KEY_CHECKS=1;\n");
fclose($fhOut);

echo $out, "\n";
