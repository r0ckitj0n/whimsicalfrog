<?php
// api/set_landing_coords.php — Upsert and activate landing coordinates for room 'A'
// Accepts JSON or form params: top, left, width, height
// Auth: session admin or admin_token=whimsical_admin_2024

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

function is_authorized(): bool {
  if (AuthHelper::isAdmin()) return true;
  $token = $_GET['admin_token'] ?? $_POST['admin_token'] ?? null;
  if ($token && hash_equals(AuthHelper::ADMIN_TOKEN, (string)$token)) return true;
  $input = json_decode(file_get_contents('php://input'), true) ?? [];
  if (!empty($input['admin_token']) && hash_equals(AuthHelper::ADMIN_TOKEN, (string)$input['admin_token'])) return true;
  return false;
}

if (!is_authorized()) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'forbidden']);
  exit;
}

try {
  $input = json_decode(file_get_contents('php://input'), true);
  if (!is_array($input)) $input = [];
  $top = isset($_POST['top']) ? (int)$_POST['top'] : (isset($input['top']) ? (int)$input['top'] : 411);
  $left = isset($_POST['left']) ? (int)$_POST['left'] : (isset($input['left']) ? (int)$input['left'] : 601);
  $width = isset($_POST['width']) ? (int)$_POST['width'] : (isset($input['width']) ? (int)$input['width'] : 125);
  $height = isset($_POST['height']) ? (int)$_POST['height'] : (isset($input['height']) ? (int)$input['height'] : 77);

  // Bounds
  $top = max(0, min(896, $top));
  $left = max(0, min(1280, $left));
  $width = max(10, min(1280, $width));
  $height = max(10, min(896, $height));

  $payload = json_encode([[ 'selector' => '.area-1', 'top' => $top, 'left' => $left, 'width' => $width, 'height' => $height ]], JSON_UNESCAPED_SLASHES);

  Database::beginTransaction();
  $existing = Database::queryOne("SELECT id FROM room_maps WHERE room_number = 'A' AND map_name = 'Landing Admin' LIMIT 1", []);
  if ($existing) {
    $id = (int)$existing['id'];
    Database::execute("UPDATE room_maps SET coordinates = ?, updated_at = NOW() WHERE id = ?", [$payload, $id]);
    Database::execute("UPDATE room_maps SET is_active = 0 WHERE room_number = 'A' AND id <> ?", [$id]);
    Database::execute("UPDATE room_maps SET is_active = 1 WHERE id = ?", [$id]);
  } else {
    Database::execute("INSERT INTO room_maps (room_number, map_name, coordinates, is_active, created_at, updated_at) VALUES ('A', 'Landing Admin', ?, 1, NOW(), NOW())", [$payload]);
    $id = (int)Database::lastInsertId();
    Database::execute("UPDATE room_maps SET is_active = 0 WHERE room_number = 'A' AND id <> ?", [$id]);
  }
  Database::commit();

  echo json_encode([
    'success' => true,
    'room_number' => 'A',
    'map_name' => 'Landing Admin',
    'coordinates' => [ 'top' => $top, 'left' => $left, 'width' => $width, 'height' => $height ]
  ]);
} catch (Throwable $e) {
  try { Database::rollBack(); } catch (Throwable $__) {}
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
}
