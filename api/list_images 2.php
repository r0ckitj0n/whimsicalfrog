<?php
// api/list_images.php — List images under /images for selection in UIs
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$root = dirname(__DIR__);
$baseDir = realpath($root . '/images');
if ($baseDir === false || !is_dir($baseDir)) {
  echo json_encode([ 'success' => false, 'error' => 'images directory not found' ]);
  exit;
}

$allowed = ['jpg','jpeg','png','gif','webp','svg','bmp','tiff','ico','heic','heif'];
$images = [];

// Derive base URL for absolute links
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $host ? ($scheme . '://' . $host) : '';

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
  if ($file->isDir()) continue;
  $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
  if (!in_array($ext, $allowed, true)) continue;
  $full = $file->getPathname();
  // Build web path starting with /images
  $rel = substr($full, strlen($baseDir));
  $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
  $webPath = '/images' . $rel;
  $label = ltrim($rel, '/');
  $absUrl = $baseUrl ? ($baseUrl . $webPath) : $webPath;
  $images[] = [ 'path' => $webPath, 'url' => $absUrl, 'name' => $label ];
}

usort($images, function($a,$b){ return strcasecmp($a['name'],$b['name']); });

echo json_encode([ 'success' => true, 'images' => $images ]);
