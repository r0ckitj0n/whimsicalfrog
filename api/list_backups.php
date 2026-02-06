<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

AuthHelper::requireAdmin(403, 'Admin access required');
header('Content-Type: application/json');

$base = realpath(__DIR__ . '/../backups/sql');
if (!$base || !is_dir($base)) {
  @mkdir($base, 0775, true);
}
$base = realpath(__DIR__ . '/../backups/sql');

$files = [];
if ($base && is_dir($base)) {
  $entries = @scandir($base) ?: [];
  foreach ($entries as $e) {
    if ($e === '.' || $e === '..') continue;
    $path = $base . DIRECTORY_SEPARATOR . $e;
    if (is_file($path) && (preg_match('/\.sql(\.gz)?$/i', $e))) {
      $files[] = [
        'name' => $e,
        'size' => @filesize($path) ?: 0,
        'mtime' => @filemtime($path) ?: 0,
        'rel' => 'backups/sql/' . $e,
      ];
    }
  }
}
// Sort newest first
usort($files, function($a,$b){ return ($b['mtime'] <=> $a['mtime']); });

echo json_encode(['success' => true, 'files' => $files], JSON_UNESCAPED_SLASHES);
