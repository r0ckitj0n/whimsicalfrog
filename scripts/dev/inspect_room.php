<?php
// scripts/dev/inspect_room.php
// Usage (CLI): php scripts/dev/inspect_room.php 2
// Usage (web/cli with GET): php -r '$_GET=["room"=>"2"]; include "scripts/dev/inspect_room.php";'

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain');

require_once __DIR__ . '/../../api/config.php';

try { Database::getInstance(); } catch (Throwable $e) { echo "DB connect failed: {$e->getMessage()}\n"; exit(1); }

$roomInput = $_GET['room'] ?? ($argv[1] ?? null);
if ($roomInput === null || $roomInput === '') { echo "Usage: inspect_room.php <room> (e.g., 2, 0, A)\n"; exit(1); }
$room = trim((string)$roomInput);

function getBgPath(string $room): string {
  $base = '';
  if (preg_match('/^[Aa]$/', $room)) { $base = 'background-home'; }
  elseif ($room === '0') { $base = 'background-room-main'; }
  elseif (preg_match('/^\d+$/', $room)) { $base = 'background-room' . $room; }
  else { $base = 'background-room-main'; }
  $p = __DIR__ . "/../../images/backgrounds/{$base}.webp";
  if (!file_exists($p)) $p = __DIR__ . "/../../images/backgrounds/{$base}.png";
  return $p;
}

function tryImageInfo(string $path): array {
  if (!file_exists($path)) return ['exists'=>false,'path'=>$path];
  $info = @getimagesize($path);
  if (!$info) return ['exists'=>true,'path'=>$path,'error'=>'getimagesize failed'];
  return ['exists'=>true,'path'=>$path,'w'=>$info[0]??0,'h'=>$info[1]??0,'mime'=>$info['mime']??''];
}

// room_settings meta
$meta = Database::queryOne('SELECT room_number, room_name, render_context, target_aspect_ratio FROM room_settings WHERE room_number = ?', [$room]);

// room_maps active
$map = Database::queryOne('SELECT id, map_name, updated_at, coordinates FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1', [$room]);
$coords = [];
if ($map && isset($map['coordinates'])) { $arr = json_decode((string)$map['coordinates'], true); if (is_array($arr)) $coords = $arr; }

$bgInfo = tryImageInfo(getBgPath($room));

echo "Room: {$room}\n";
if ($meta) {
  $ta = isset($meta['target_aspect_ratio']) && $meta['target_aspect_ratio'] !== null ? (float)$meta['target_aspect_ratio'] : null;
  echo "room_settings: render_context=" . ($meta['render_context'] ?? 'null') . ", target_aspect_ratio=" . ($ta !== null ? $ta : 'null') . ", room_name=" . ($meta['room_name'] ?? '') . "\n";
} else {
  echo "room_settings: not found\n";
}
if ($map) {
  echo "room_maps(active): id={$map['id']}, name='{$map['map_name']}', updated_at={$map['updated_at']}, coords_count=" . count($coords) . "\n";
  if (!empty($coords)) {
    $c0 = $coords[0];
    echo "first_rect: selector=" . ($c0['selector']??'') . ", top=" . ($c0['top']??'') . ", left=" . ($c0['left']??'') . ", width=" . ($c0['width']??'') . ", height=" . ($c0['height']??'') . "\n";
  }
} else {
  echo "room_maps(active): none\n";
}

echo "background: " . json_encode($bgInfo) . "\n";

// Quick sanity: detect authoring baseline vs image size
if (!empty($coords) && !empty($bgInfo['w']) && !empty($bgInfo['h'])) {
  // Heuristic: find max right/bottom against typical 1280x896 baseline
  $maxRight = 0; $maxBottom = 0;
  foreach ($coords as $r) {
    $maxRight = max($maxRight, (float)$r['left'] + (float)$r['width']);
    $maxBottom = max($maxBottom, (float)$r['top'] + (float)$r['height']);
  }
  echo "coords_max_right={$maxRight}, coords_max_bottom={$maxBottom}\n";
  echo "image_wh={$bgInfo['w']}x{$bgInfo['h']} (aspect=" . ($bgInfo['w']/(float)$bgInfo['h']) . ")\n";
}
