<?php

require_once __DIR__ . '/../../api/config.php';
Database::getInstance();
$rooms = isset($argv[1]) && $argv[1] !== '' ? explode(',', $argv[1]) : ['0','1','2','3','4','5'];
$out = [];
foreach ($rooms as $rn) {
    $rn = trim($rn);
    $row = Database::queryOne(
        "SELECT map_name, coordinates FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY (map_name = 'Original') DESC, updated_at DESC, id DESC LIMIT 1",
        [$rn]
    );
    $coords = [];
    if ($row && isset($row['coordinates'])) {
        $coords = json_decode($row['coordinates'], true) ?: [];
    }
    $out[] = [ 'room_number' => $rn, 'map_name' => $row['map_name'] ?? null, 'coordinates' => $coords ];
}
echo json_encode($out, JSON_PRETTY_PRINT), "\n";
