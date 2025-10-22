<?php
// scripts/dev/rebase_room_maps.php
// Rebase room_maps coordinates from a source baseline (from_w x from_h) to 1280x896.
// Safe-by-default: dry-run unless confirm=1&apply=1 provided.
// Usage examples:
//  - Dry run (CLI): php scripts/dev/rebase_room_maps.php  --rooms=1,2,4 --from=1184x864
//  - Apply (CLI):   php scripts/dev/rebase_room_maps.php  --rooms=1,2,4 --from=1184x864 --apply --confirm
//  - Web/CLI eval:  php -r '$_GET=["rooms"=>"1,2,4","from"=>"1184x864","apply"=>1,"confirm"=>1]; include "scripts/dev/rebase_room_maps.php";'

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');

require_once __DIR__ . '/../../api/config.php';

try { Database::getInstance(); } catch (Throwable $e) { echo "DB connect failed: {$e->getMessage()}\n"; exit(1); }

// Parse args from CLI or GET
function arg_has(string $name): bool {
  global $argv; $flag = "--$name"; if (isset($_GET[$name])) return true; if (isset($argv) && in_array($flag, $argv, true)) return true; return false;
}
function arg_val(string $name, ?string $def=null): ?string {
  global $argv; if (isset($_GET[$name])) return (string)$_GET[$name]; if (!isset($argv)) return $def; foreach ($argv as $i=>$a){ if (strpos($a, "--$name=")===0) return substr($a, strlen($name)+3); } return $def;
}

$roomsStr = arg_val('rooms', null);
$from = arg_val('from', null);
$apply = arg_has('apply');
$confirm = arg_has('confirm');

if ($roomsStr === null || $from === null) {
  echo "Usage: --rooms=1,2,4 --from=1184x864 [--apply --confirm]\n";
  exit(1);
}

if (!preg_match('/^(\d+|[A-Za-z])(,(\d+|[A-Za-z]))*$/', $roomsStr)) {
  echo "Invalid rooms list. Expect comma-separated like 1,2,4 or A,0.\n"; exit(1);
}
if (!preg_match('/^(\d+)x(\d+)$/', $from, $m)) {
  echo "Invalid --from format; expected <w>x<h>, e.g., 1184x864\n"; exit(1);
}
$fromW = (int)$m[1]; $fromH = (int)$m[2];
$toW = 1280; $toH = 896;
// Match ImageMagick -resize WxH^ -gravity center -extent WxH
// Use cover scale (max to fill both), then crop offsets equally from center
$scale = max($toW / max(1,$fromW), $toH / max(1,$fromH));
$offsetX = ($fromW * $scale - $toW) / 2.0; // pixels cropped from left
$offsetY = ($fromH * $scale - $toH) / 2.0; // pixels cropped from top

$rooms = array_map('trim', explode(',', $roomsStr));

$results = [];
foreach ($rooms as $room) {
  // Load active (or latest) map
  $map = Database::queryOne('SELECT * FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1', [$room]);
  if (!$map) {
    $map = Database::queryOne('SELECT * FROM room_maps WHERE room_number = ? ORDER BY updated_at DESC, created_at DESC LIMIT 1', [$room]);
  }
  if (!$map) {
    $results[$room] = ['status'=>'no_map'];
    continue;
  }
  $coords = json_decode((string)$map['coordinates'], true);
  if (!is_array($coords)) $coords = [];

  // Transform
  $rebased = [];
  foreach ($coords as $r) {
    $l = (float)($r['left'] ?? 0) * $scale - $offsetX;
    $t = (float)($r['top'] ?? 0) * $scale - $offsetY;
    $w = (float)($r['width'] ?? 0) * $scale;
    $h = (float)($r['height'] ?? 0) * $scale;
    // Clamp to image bounds [0..toW/H]
    $l = max(0.0, min((float)$toW, $l));
    $t = max(0.0, min((float)$toH, $t));
    $w = max(1.0, min((float)$toW - $l, $w));
    $h = max(1.0, min((float)$toH - $t, $h));
    $rebased[] = [
      'selector' => $r['selector'] ?? '',
      'left' => $l,
      'top' => $t,
      'width' => $w,
      'height' => $h,
    ];
  }

  $preview = [
    'map_id' => $map['id'],
    'map_name' => $map['map_name'],
    'from' => [ 'w'=>$fromW, 'h'=>$fromH ],
    'to' => [ 'w'=>$toW, 'h'=>$toH ],
    'scale' => [ 'cover'=>$scale, 'offsetX'=>$offsetX, 'offsetY'=>$offsetY ],
    'count' => count($rebased),
    'first_before' => isset($coords[0]) ? $coords[0] : null,
    'first_after' => isset($rebased[0]) ? $rebased[0] : null,
  ];

  if ($apply && $confirm) {
    Database::beginTransaction();
    try {
      $newName = ($map['map_name'] ?? 'Map') . ' — Rebased to 1280x896 ' . date('Y-m-d H:i');
      $json = json_encode($rebased, JSON_UNESCAPED_SLASHES);
      Database::execute('INSERT INTO room_maps (room_number, map_name, coordinates, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())', [$room, $newName, $json]);
      $newId = (int)Database::lastInsertId();
      Database::execute('UPDATE room_maps SET is_active = 0 WHERE room_number = ? AND id <> ?', [$room, $newId]);
      Database::commit();
      $results[$room] = ['status'=>'applied', 'new_map_id'=>$newId] + $preview;
    } catch (Throwable $e) {
      Database::rollBack();
      $results[$room] = ['status'=>'error', 'error'=>$e->getMessage()] + $preview;
    }
  } else {
    $results[$room] = ['status'=>'dry_run'] + $preview;
  }
}

echo json_encode(['success'=>true,'rooms'=>$results], JSON_PRETTY_PRINT) . "\n";
