<?php
// api/admin_icon_map.php
// Provides CRUD for admin action icon mappings and dynamic CSS generation.

$root = dirname(__DIR__);
$storeFile = $root . '/config/admin_icon_map.json';

// Default headers; may be overridden later for CSS
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once $root . '/api/config.php';

function wf_icon_default_map() {
  return [
    'add' => '➕',
    'edit' => '✏️',
    'duplicate' => '📄',
    'delete' => '🗑️',
    'view' => '👁️',
    'preview' => '👁️',
    'preview-inline' => '🪟',
    'refresh' => '🔄',
    'send' => '📤',
    'save' => '💾',
    'archive' => '🗄️',
    'settings' => '⚙️',
    'download' => '⬇️',
    'upload' => '⬆️',
    'external' => '↗️',
    'link' => '🔗',
    'info' => 'ℹ️',
    'help' => '❓',
    'print' => '🖨️',
    'up' => '▲',
    'down' => '▼'
  ];
}

function wf_icon_load_map($storeFile) {
  if (is_readable($storeFile)) {
    $json = @file_get_contents($storeFile);
    if ($json !== false) {
      $data = json_decode($json, true);
      if (is_array($data)) return $data;
    }
  }
  return wf_icon_default_map();
}

function wf_icon_save_map($storeFile, $map) {
  $dir = dirname($storeFile);
  if (!is_dir($dir)) @mkdir($dir, 0775, true);
  $json = json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  return @file_put_contents($storeFile, $json) !== false;
}

function wf_icon_build_css($map) {
  $css = [];
  $css[] = '@charset "UTF-8";';
  foreach ($map as $key => $emoji) {
    $k = trim((string)$key);
    if ($k === '') continue;
    $e = str_replace(['\\', "'"], ['\\\\', "\\'"], (string)$emoji);
    $cls = '.btn-icon--' . str_replace([' ', '_'], ['-', '-'], strtolower($k));
    $css[] = $cls . '::before { content: \'' . $e . '\'; }';
  }
  return implode("\n", $css);
}

$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
if ($action === '') {
  // Also support POST action param
  if (isset($_POST['action'])) $action = (string)$_POST['action'];
}

try {
  if ($action === 'get_map') {
    header('Content-Type: application/json; charset=utf-8');
    $map = wf_icon_load_map($storeFile);
    echo json_encode(['success' => true, 'map' => $map]);
    return;
  }
  if ($action === 'get_css') {
    $map = wf_icon_load_map($storeFile);
    $css = wf_icon_build_css($map);
    header('Content-Type: text/css; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo $css;
    return;
  }
  if ($action === 'set_map') {
    header('Content-Type: application/json; charset=utf-8');
    // Only allow admins to modify
    try {
      require_once $root . '/includes/auth.php';
      if (function_exists('requireAdmin')) { requireAdmin(false); }
    } catch (Throwable $e) { /* ignore */ }

    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    $map = isset($j['map']) && is_array($j['map']) ? $j['map'] : null;
    if (!$map) { echo json_encode(['success' => false, 'error' => 'Invalid map']); return; }
    // Basic sanitize: keep string->string pairs only
    $clean = [];
    foreach ($map as $k => $v) {
      $key = strtolower(trim((string)$k));
      if ($key === '') continue;
      $val = (string)$v;
      $clean[$key] = $val;
    }
    if (wf_icon_save_map($storeFile, $clean)) {
      echo json_encode(['success' => true]);
    } else {
      echo json_encode(['success' => false, 'error' => 'Failed to save']);
    }
    return;
  }

  echo json_encode(['success' => false, 'error' => 'Unknown action']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Server error']);
}
