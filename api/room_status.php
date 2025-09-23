<?php

// api/room_status.php
// Lists per-room active background and active map summary for quick admin verification.
// GET params:
//   rooms=all (default) – scans active rooms from room_settings
//   rooms=1,2,3 – optional CSV to restrict
//
// Response:
// {
//   success: true,
//   rooms: [
//     { room_number: "1", background: {...} | null, map: { id, map_name, area_count } | null }
//   ]
// }

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';

try {
    Database::getInstance();

    $param = isset($_GET['rooms']) ? trim((string)$_GET['rooms']) : 'all';

    // Determine room list
    $rooms = [];
    if ($param === '' || strtolower($param) === 'all') {
        $rows = Database::queryAll(
            "SELECT room_number FROM room_settings WHERE is_active = 1 ORDER BY display_order, room_number"
        );
        foreach ($rows as $r) {
            $rooms[] = (string)$r['room_number'];
        }
    } else {
        foreach (explode(',', $param) as $r) {
            $rooms[] = preg_replace('/\s+/', '', (string)$r);
        }
    }

    // Fetch status per room
    $result = [];
    foreach ($rooms as $rn) {
        // Active background by room_number first, fallback by room_type shorthand
        $bg = Database::queryOne(
            "SELECT background_name, image_filename, webp_filename, created_at, updated_at FROM backgrounds WHERE room_number = ? AND is_active = 1 LIMIT 1",
            [$rn]
        );
        if (!$bg) {
            // fallback legacy – compose room_type
            $rt = preg_match('/^\d+$/', (string)$rn) ? ('room' . $rn) : $rn;
            $bg = Database::queryOne(
                "SELECT background_name, image_filename, webp_filename, created_at, updated_at FROM backgrounds WHERE room_type = ? AND is_active = 1 LIMIT 1",
                [$rt]
            );
        }

        // Active map by room_number first, then fallback
        $map = Database::queryOne(
            "SELECT id, map_name, coordinates, updated_at FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1",
            [$rn]
        );
        if (!$map) {
            $rt = preg_match('/^\d+$/', (string)$rn) ? ('room' . $rn) : $rn;
            $map = Database::queryOne(
                "SELECT id, map_name, coordinates, updated_at FROM room_maps WHERE room_type = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1",
                [$rt]
            );
        }
        $mapOut = null;
        if ($map) {
            $coords = json_decode($map['coordinates'] ?? '[]', true);
            $mapOut = [
                'id' => (int)$map['id'],
                'map_name' => (string)($map['map_name'] ?? ''),
                'area_count' => is_array($coords) ? count($coords) : 0,
                'updated_at' => $map['updated_at'] ?? null,
            ];
        }

        $result[] = [
            'room_number' => (string)$rn,
            'background' => $bg ?: null,
            'map' => $mapOut,
        ];
    }

    echo json_encode(['success' => true, 'rooms' => $result], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
