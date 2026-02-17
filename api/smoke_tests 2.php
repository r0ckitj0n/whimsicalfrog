<?php
// Admin Smoke Tests API
// Provides endpoints to list tests, run tests, list screenshots and logs, and fetch file contents.

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';

if (class_exists('Auth')) {
  Auth::requireAdmin();
} elseif (function_exists('requireAdmin')) {
  requireAdmin();
}
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$body = null;
if ($method === 'POST') {
  $raw = file_get_contents('php://input');
  if ($raw) {
    try {
      $body = json_decode($raw, true);
    } catch (Throwable $e) {
      $body = null;
    }
  }
}

$root = realpath(dirname(__DIR__));
$logsRoot = $root . '/logs/smoke-logs';
$screenshotsRoot = $root . '/logs/screenshots';
@mkdir($logsRoot, 0777, true);
@mkdir($screenshotsRoot, 0777, true);

$tests = [
  // id => [label, npm script, env keys]
  'overlays_smoke' => ['label' => 'Admin Overlays Smoke', 'script' => 'smoke:overlays', 'env' => ['BASE_URL', 'ADMIN_USER', 'ADMIN_PASS']],
  'overlays_suite' => ['label' => 'Admin Overlays Suite', 'script' => 'test:overlays', 'env' => ['BASE_URL']],
  'admin_crawl' => ['label' => 'Admin Settings Crawl', 'script' => 'smoke:admin:crawl', 'env' => ['BASE_URL', 'ADMIN_USER', 'ADMIN_PASS', 'MAX_DEPTH']],
  'store_crawl' => ['label' => 'Storefront Crawl', 'script' => 'smoke:store:crawl', 'env' => ['STORE_URL', 'MAX_DEPTH']],
  'cart_smoke' => ['label' => 'Cart Smoke', 'script' => 'smoke:cart', 'env' => ['STORE_URL']],
];

function json_out($arr)
{
  echo json_encode($arr);
  exit;
}
function safe_rel($path, $root)
{
  $real = realpath($path);
  if ($real === false)
    return null;
  if (strpos($real, $root) !== 0)
    return null;
  return substr($real, strlen($root));
}

if ($action === 'list_tests') {
  $out = [];
  foreach ($tests as $id => $cfg) {
    $out[] = ['id' => $id, 'label' => $cfg['label'], 'env' => $cfg['env']];
  }
  json_out(['success' => true, 'tests' => $out]);
}

if ($action === 'list_runs') {
  $runs = [];
  $dir = @opendir($logsRoot);
  if ($dir) {
    while (($f = readdir($dir)) !== false) {
      if ($f === '.' || $f === '..')
        continue;
      $p = $logsRoot . '/' . $f;
      if (is_file($p)) {
        $runs[] = ['file' => $f, 'mtime' => @filemtime($p) ?: 0, 'size' => @filesize($p) ?: 0];
      }
    }
    closedir($dir);
  }
  usort($runs, function ($a, $b) {
    return ($b['mtime'] <=> $a['mtime']); });
  json_out(['success' => true, 'runs' => $runs]);
}

if ($action === 'get_log') {
  $name = $_GET['name'] ?? '';
  if (!$name || strpos($name, '..') !== false || strpos($name, '/') !== false)
    json_out(['success' => false, 'error' => 'bad name']);
  $p = $logsRoot . '/' . $name;
  if (!is_file($p))
    json_out(['success' => false, 'error' => 'not found']);
  $txt = @file_get_contents($p);
  json_out(['success' => true, 'content' => $txt]);
}

if ($action === 'list_screenshots') {
  $dirs = [];
  $dir = @opendir($screenshotsRoot);
  if ($dir) {
    while (($f = readdir($dir)) !== false) {
      if ($f === '.' || $f === '..')
        continue;
      $p = $screenshotsRoot . '/' . $f;
      if (is_dir($p)) {
        $dirs[] = ['dir' => $f, 'mtime' => @filemtime($p) ?: 0];
      }
    }
    closedir($dir);
  }
  usort($dirs, function ($a, $b) {
    return ($b['mtime'] <=> $a['mtime']); });
  json_out(['success' => true, 'dirs' => $dirs]);
}

if ($action === 'list_images') {
  $dirName = $_GET['dir'] ?? '';
  if (!$dirName || strpos($dirName, '..') !== false || strpos($dirName, '/') !== false)
    json_out(['success' => false, 'error' => 'bad dir']);
  $abs = $screenshotsRoot . '/' . $dirName;
  if (!is_dir($abs))
    json_out(['success' => false, 'error' => 'not found']);
  $imgs = [];
  $dh = @opendir($abs);
  if ($dh) {
    while (($f = readdir($dh)) !== false) {
      if (preg_match('/\.(png|jpg|jpeg|webp)$/i', $f)) {
        $imgs[] = ['name' => $f];
      }
    }
    closedir($dh);
  }
  sort($imgs);
  json_out(['success' => true, 'images' => $imgs]);
}

if ($action === 'get_image') {
  $dirName = $_GET['dir'] ?? '';
  $fileName = $_GET['file'] ?? '';
  if (!$dirName || !$fileName) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
  }
  if (strpos($dirName, '..') !== false || strpos($dirName, '/') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
  }
  if (strpos($fileName, '..') !== false || strpos($fileName, '/') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
  }
  $abs = $screenshotsRoot . '/' . $dirName . '/' . $fileName;
  if (!is_file($abs)) {
    http_response_code(404);
    echo json_encode(['success' => false]);
    exit;
  }
  $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
  $mime = ($ext === 'png') ? 'image/png' : (($ext === 'webp') ? 'image/webp' : 'image/jpeg');
  header('Content-Type: ' . $mime);
  readfile($abs);
  exit;
}

if ($action === 'run') {
  $id = $body['test_id'] ?? $_POST['test_id'] ?? '';
  if (!$id || !isset($tests[$id]))
    json_out(['success' => false, 'error' => 'invalid test']);
  $cfg = $tests[$id];
  $env = [];
  foreach (($cfg['env'] ?? []) as $k) {
    if (isset($body[$k]))
      $env[$k] = (string) $body[$k];
  }
  // Defaults if not provided
  if (in_array('BASE_URL', $cfg['env'] ?? [], true) && empty($env['BASE_URL'])) {
    $env['BASE_URL'] = 'http://localhost:8080/admin?section=settings';
  }
  if (in_array('STORE_URL', $cfg['env'] ?? [], true) && empty($env['STORE_URL'])) {
    $env['STORE_URL'] = 'http://localhost:8080';
  }
  if (in_array('MAX_DEPTH', $cfg['env'] ?? [], true) && empty($env['MAX_DEPTH'])) {
    $env['MAX_DEPTH'] = '5';
  }

  $timestamp = date('Ymd_His');
  $logFile = $logsRoot . '/' . $timestamp . '_' . $id . '.log';

  // Build environment prefix
  $pairs = [];
  foreach ($env as $k => $v) {
    $pairs[] = $k . '=' . escapeshellarg($v);
  }
  $envPrefix = implode(' ', $pairs);
  $cmd = $envPrefix . ' npm run ' . escapeshellarg($cfg['script']);
  // Background execution with output redirection
  $full = 'bash -lc ' . escapeshellarg($cmd . ' > ' . escapeshellarg($logFile) . ' 2>&1 & echo $!');

  $cwd = $root;
  $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
  $proc = @proc_open($full, $descriptors, $pipes, $cwd, null);
  $pid = null;
  if (is_resource($proc)) {
    $pidOut = stream_get_contents($pipes[1]);
    @fclose($pipes[1]);
    if (isset($pipes[2]))
      @fclose($pipes[2]);
    @proc_close($proc);
    $pid = trim($pidOut);
  }

  json_out(['success' => true, 'job' => ['pid' => $pid, 'log' => basename($logFile)]]);
}

json_out(['success' => false, 'error' => 'invalid action']);
