<?php
// api/publish_social.php — Publish social posts to connected accounts (simulated)
// Uses get_social_accounts.php to fetch configured accounts; logs results to reports/social_publish_log.json

require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$logFile = dirname(__DIR__) . '/reports/social_publish_log.json';
if (!file_exists(dirname($logFile))) @mkdir(dirname($logFile), 0777, true);

function read_log($path){
  if (!file_exists($path)) return [];
  $raw = @file_get_contents($path);
  if ($raw === false || trim($raw) === '') return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}
function write_log($path, $data){
  $ok = @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
  return $ok !== false;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'publish');
$method = $_SERVER['REQUEST_METHOD'] ?? 'POST';

if ($method !== 'POST' || $action !== 'publish') {
  echo json_encode([ 'success' => false, 'error' => 'Unsupported action' ]); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$content = (string)($body['content'] ?? '');
$imageUrl = (string)($body['image_url'] ?? '');
$platforms = is_array($body['platforms'] ?? null) ? $body['platforms'] : [];
$publishAll = !!($body['publish_all'] ?? false);

if ($content === '') { echo json_encode([ 'success' => false, 'error' => 'Missing content' ]); exit; }

// Load connected accounts
$accountsRaw = @file_get_contents(__DIR__ . '/get_social_accounts.php');
$connected = [];
try {
  // Fallback: call via HTTP might not be necessary since file returns JSON dynamically; we will include via HTTP fetch
  $j = @file_get_contents('http://localhost/api/get_social_accounts.php');
  $data = $j ? json_decode($j, true) : null;
  if ($data && ($data['success'] ?? false)) {
    $connected = array_filter($data['accounts'] ?? [], function($a){ return !empty($a['connected']); });
  }
} catch (Throwable $e) {
  // If HTTP fetch fails, do nothing; this is a simulated publisher
}

// If still empty, simulate two accounts if platforms specify
if (!$connected) {
  $connected = [];
  $all = ['facebook','instagram','twitter','linkedin','youtube','tiktok'];
  $want = $publishAll || !$platforms ? $all : $platforms;
  foreach ($want as $p) {
    $connected[] = [ 'id' => $p, 'platform' => $p, 'account_name' => 'demo', 'connected' => true ];
  }
}

$now = date('c');
$results = [];
foreach ($connected as $acc) {
  $plat = strtolower($acc['platform'] ?? '');
  if (!$publishAll && $platforms && !in_array($plat, $platforms, true)) continue;
  $results[] = [
    'platform' => $plat,
    'account' => $acc['account_name'] ?? '',
    'status' => 'ok',
    'posted_at' => $now,
    'content_preview' => mb_substr($content, 0, 100)
  ];
}

$log = read_log($logFile);
$log[] = [ 'at' => $now, 'content' => $content, 'image_url' => $imageUrl, 'results' => $results ];
write_log($logFile, $log);

echo json_encode([ 'success' => true, 'results' => $results ]);
