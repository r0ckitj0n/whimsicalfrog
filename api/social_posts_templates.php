<?php
// Lightweight JSON-backed API for Social Media Post Templates
// Stores data at reports/social_templates.json to avoid DB migrations.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';
header('Content-Type: application/json');

$store = dirname(__DIR__) . '/reports/social_templates.json';
if (!file_exists(dirname($store))) @mkdir(dirname($store), 0777, true);

function read_store($path) {
  if (!file_exists($path)) return [];
  $raw = @file_get_contents($path);
  if ($raw === false || trim($raw) === '') return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

function write_store($path, $data) {
  $ok = @file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
  return $ok !== false;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string) ($_GET['action'] ?? ($_POST['action'] ?? 'list')));
$allowedActions = ['list', 'get', 'create', 'update', 'delete'];
if (!in_array($action, $allowedActions, true)) {
  http_response_code(400);
  echo json_encode([ 'success' => false, 'error' => 'Unsupported action' ]);
  exit;
}
if (($action === 'list' || $action === 'get') && $method !== 'GET') {
  http_response_code(405);
  echo json_encode([ 'success' => false, 'error' => 'GET required' ]);
  exit;
}
if (($action === 'create' || $action === 'update' || $action === 'delete') && $method !== 'POST') {
  http_response_code(405);
  echo json_encode([ 'success' => false, 'error' => 'POST required' ]);
  exit;
}

requireAdmin(true);

try {
  if ($method === 'GET' && $action === 'list') {
    $items = read_store($store);
    echo json_encode([ 'success' => true, 'templates' => $items ]);
    exit;
  }

  if ($method === 'GET' && $action === 'get') {
    $id = isset($_GET['id']) ? (string)$_GET['id'] : '';
    $items = read_store($store);
    $found = null;
    foreach ($items as $it) {
      if ((string)($it['id'] ?? '') === $id) { $found = $it; break; }
    }
    echo json_encode([ 'success' => !!$found, 'template' => $found ]);
    exit;
  }

  if ($method === 'POST' && ($action === 'create' || $action === 'update')) {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $items = read_store($store);
    $allowedPlatforms = ['facebook','instagram','twitter','linkedin','youtube','tiktok'];
    $normalizePlatforms = static function ($value) use ($allowedPlatforms): array {
      $arr = is_array($value) ? $value : [];
      return array_values(array_unique(array_filter(array_map(
        static fn($p) => strtolower(trim((string) $p)),
        $arr
      ), static fn($p) => in_array($p, $allowedPlatforms, true))));
    };

    if ($action === 'create') {
      $id = (string)(time() . rand(100,999));
      $name = trim((string)($body['name'] ?? 'Untitled'));
      $content = (string)($body['content'] ?? '');
      if ($name === '' || strlen($name) > 120 || strlen($content) > 5000) {
        http_response_code(422);
        echo json_encode([ 'success' => false, 'error' => 'Invalid template payload' ]);
        exit;
      }
      $tpl = [
        'id' => $id,
        'name' => $name,
        'content' => $content,
        'image_url' => substr((string)($body['image_url'] ?? ''), 0, 2000),
        'platforms' => $normalizePlatforms($body['platforms'] ?? []),
        'is_active' => !!($body['is_active'] ?? true),
        'created_at' => date('c'),
        'updated_at' => date('c'),
      ];
      $items[] = $tpl;
      write_store($store, $items);
      echo json_encode([ 'success' => true, 'template' => $tpl ]);
      exit;
    }

    if ($action === 'update') {
      $id = (string)($body['id'] ?? '');
      if ($id === '') {
        http_response_code(422);
        echo json_encode([ 'success' => false, 'error' => 'Template id required' ]);
        exit;
      }
      $updated = null;
      foreach ($items as &$it) {
        if ((string)($it['id'] ?? '') === $id) {
          $it['name'] = substr(trim((string)($body['name'] ?? $it['name'])), 0, 120);
          $it['content'] = substr((string)($body['content'] ?? $it['content']), 0, 5000);
          $it['image_url'] = substr((string)($body['image_url'] ?? $it['image_url']), 0, 2000);
          $it['platforms'] = $normalizePlatforms($body['platforms'] ?? ($it['platforms'] ?? []));
          $it['is_active'] = isset($body['is_active']) ? !!$body['is_active'] : ($it['is_active'] ?? true);
          $it['updated_at'] = date('c');
          $updated = $it;
          break;
        }
      }
      unset($it);
      write_store($store, $items);
      echo json_encode([ 'success' => !!$updated, 'template' => $updated ]);
      exit;
    }
  }

  if ($method === 'POST' && $action === 'delete') {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $id = (string)($body['id'] ?? '');
    $items = read_store($store);
    $before = count($items);
    $items = array_values(array_filter($items, function($it) use ($id){ return (string)($it['id'] ?? '') !== $id; }));
    write_store($store, $items);
    echo json_encode([ 'success' => count($items) < $before ]);
    exit;
  }

  echo json_encode([ 'success' => false, 'error' => 'Unsupported action' ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([ 'success' => false, 'error' => 'Server error' ]);
}
