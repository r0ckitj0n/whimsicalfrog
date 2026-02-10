<?php
// api/publish_social.php — Publish social posts to connected accounts (simulated)
// Uses get_social_accounts.php to fetch configured accounts; logs results to reports/social_publish_log.json

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
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
  http_response_code(405);
  echo json_encode([ 'success' => false, 'error' => 'Unsupported action' ]); exit;
}

requireAdmin(true);

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$content = trim((string)($body['content'] ?? ''));
$image_url = trim((string)($body['image_url'] ?? ''));
$platforms = is_array($body['platforms'] ?? null) ? $body['platforms'] : [];
$publishAll = !!($body['publish_all'] ?? false);

if ($content === '' || strlen($content) > 5000) {
  http_response_code(422);
  echo json_encode([ 'success' => false, 'error' => 'Invalid content' ]);
  exit;
}
if ($image_url !== '' && strlen($image_url) > 2000) {
  http_response_code(422);
  echo json_encode([ 'success' => false, 'error' => 'Invalid image URL' ]);
  exit;
}

$allowedPlatforms = ['facebook','instagram','twitter','linkedin','youtube','tiktok'];
$platforms = array_values(array_unique(array_filter(array_map(
  static fn($p) => strtolower(trim((string) $p)),
  $platforms
), static fn($p) => in_array($p, $allowedPlatforms, true))));

// Load connected accounts directly from database to avoid HTTP loopback deadlocks
$connected = [];
try {
  $accounts = Database::queryAll(
    "SELECT id, platform, account_name, connected, last_updated 
     FROM social_accounts 
     WHERE connected = 1
     ORDER BY platform, account_name"
  );
  $connected = $accounts ?: [];
} catch (Throwable $e) {
  // If query fails, fallback to empty array; this is a simulated publisher
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
$log[] = [ 'at' => $now, 'content' => $content, 'image_url' => $image_url, 'results' => $results ];
write_log($logFile, $log);

echo json_encode([ 'success' => true, 'results' => $results ]);
