<?php

// scripts/dev/update-room-maps.php
// Updates room_maps coordinates for the active 'Original' map per room_type.
// Safe: runs in a transaction; creates map if missing and applies as active.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../api/config.php';
    Database::getInstance();

    // Create JSON backup of current room_maps
    $backupDir = realpath(__DIR__ . '/../../backups/sql_migrations');
    if ($backupDir === false) {
        $backupDir = __DIR__ . '/../../backups/sql_migrations';
        @mkdir($backupDir, 0775, true);
    }
    $ts = date('Ymd_His');
    $backupPath = rtrim($backupDir, '/')."/room_maps_pre_update_{$ts}.json";
    $allMaps = Database::queryAll("SELECT * FROM room_maps ORDER BY room_type, created_at DESC");
    // No mutation yet, safe to back up raw rows
    file_put_contents($backupPath, json_encode($allMaps, JSON_PRETTY_PRINT));

    // Helper to resolve room_type synonyms based on what's present in DB
    $resolveRoomType = function (array $candidates) use ($pdo): string {
        foreach ($candidates as $cand) {
            $row = Database::queryOne("SELECT 1 AS c FROM room_maps WHERE room_type = ? LIMIT 1", [$cand]);
            if ($row) {
                return $cand;
            }
        }
        // If none exist, return the first candidate (will create rows under it)
        return $candidates[0];
    };

    // Determine concrete room_type keys
    $landingType = $resolveRoomType(['landing', 'roomA']);
    $room0Type = $resolveRoomType(['room_main', 'room0']);

    // Provided coordinates (latest spec)
    $maps = [
        // Landing (auto-detected: 'landing' or 'roomA')
        $landingType => [
            ['selector' => '.area-1', 'top' => 411, 'left' => 601, 'width' => 125, 'height' => 77],
        ],
        // Room 0 (main)
        $room0Type => [
            ['selector' => '.area-1', 'top' => 243, 'left' => 30, 'width' => 234, 'height' => 233],
            ['selector' => '.area-2', 'top' => 403, 'left' => 390, 'width' => 202, 'height' => 241],
            ['selector' => '.area-3', 'top' => 271, 'left' => 753, 'width' => 170, 'height' => 235],
            ['selector' => '.area-4', 'top' => 291, 'left' => 1001, 'width' => 197, 'height' => 255],
            ['selector' => '.area-5', 'top' => 157, 'left' => 486, 'width' => 190, 'height' => 230],
        ],
        // Artwork -> room4
        'room4' => [
            ['selector' => '.area-1', 'top' => 235, 'left' => 193, 'width' => 115, 'height' => 77],
            ['selector' => '.area-2', 'top' => 235, 'left' => 378, 'width' => 67, 'height' => 114],
            ['selector' => '.area-3', 'top' => 205, 'left' => 499, 'width' => 103, 'height' => 81],
            ['selector' => '.area-4', 'top' => 399, 'left' => 242, 'width' => 68, 'height' => 97],
            ['selector' => '.area-5', 'top' => 426, 'left' => 375, 'width' => 89, 'height' => 61],
            ['selector' => '.area-6', 'top' => 371, 'left' => 511, 'width' => 54, 'height' => 105],
            ['selector' => '.area-7', 'top' => 339, 'left' => 621, 'width' => 58, 'height' => 77],
            ['selector' => '.area-8', 'top' => 346, 'left' => 1051, 'width' => 90, 'height' => 73],
        ],
        // T-shirts -> room1
        'room1' => [
            ['selector' => '.area-1', 'top' => 332, 'left' => 104, 'width' => 121, 'height' => 137],
            ['selector' => '.area-2', 'top' => 345, 'left' => 289, 'width' => 92, 'height' => 122],
            ['selector' => '.area-3', 'top' => 347, 'left' => 385, 'width' => 83, 'height' => 122],
            ['selector' => '.area-4', 'top' => 344, 'left' => 474, 'width' => 90, 'height' => 125],
            ['selector' => '.area-5', 'top' => 345, 'left' => 569, 'width' => 83, 'height' => 124],
            ['selector' => '.area-6', 'top' => 466, 'left' => 911, 'width' => 96, 'height' => 133],
            ['selector' => '.area-7', 'top' => 469, 'left' => 1067, 'width' => 107, 'height' => 149],
        ],
        // Tumblers -> room2
        'room2' => [
            ['selector' => '.area-1', 'top' => 176, 'left' => 447, 'width' => 74, 'height' => 146],
            ['selector' => '.area-2', 'top' => 170, 'left' => 543, 'width' => 74, 'height' => 144],
            ['selector' => '.area-3', 'top' => 162, 'left' => 634, 'width' => 76, 'height' => 148],
            ['selector' => '.area-4', 'top' => 355, 'left' => 241, 'width' => 82, 'height' => 175],
            ['selector' => '.area-5', 'top' => 352, 'left' => 333, 'width' => 86, 'height' => 164],
            ['selector' => '.area-6', 'top' => 352, 'left' => 426, 'width' => 77, 'height' => 156],
            ['selector' => '.area-7', 'top' => 355, 'left' => 508, 'width' => 68, 'height' => 143],
            ['selector' => '.area-8', 'top' => 348, 'left' => 611, 'width' => 70, 'height' => 138],
            ['selector' => '.area-9', 'top' => 345, 'left' => 691, 'width' => 64, 'height' => 126],
            ['selector' => '.area-10', 'top' => 572, 'left' => 241, 'width' => 83, 'height' => 162],
            ['selector' => '.area-11', 'top' => 564, 'left' => 333, 'width' => 79, 'height' => 154],
            ['selector' => '.area-12', 'top' => 546, 'left' => 420, 'width' => 74, 'height' => 153],
            ['selector' => '.area-13', 'top' => 533, 'left' => 502, 'width' => 64, 'height' => 143],
            ['selector' => '.area-14', 'top' => 523, 'left' => 575, 'width' => 64, 'height' => 139],
            ['selector' => '.area-15', 'top' => 511, 'left' => 647, 'width' => 64, 'height' => 127],
        ],
        // Sublimation -> room3
        'room3' => [
            ['selector' => '.area-1', 'top' => 242, 'left' => 261, 'width' => 108, 'height' => 47],
            ['selector' => '.area-2', 'top' => 241, 'left' => 375, 'width' => 89, 'height' => 48],
            ['selector' => '.area-3', 'top' => 258, 'left' => 486, 'width' => 65, 'height' => 38],
            ['selector' => '.area-4', 'top' => 303, 'left' => 184, 'width' => 102, 'height' => 60],
            ['selector' => '.area-5', 'top' => 306, 'left' => 293, 'width' => 110, 'height' => 57],
            ['selector' => '.area-6', 'top' => 309, 'left' => 409, 'width' => 160, 'height' => 53],
            ['selector' => '.area-7', 'top' => 385, 'left' => 203, 'width' => 137, 'height' => 54],
            ['selector' => '.area-8', 'top' => 388, 'left' => 346, 'width' => 111, 'height' => 42],
            ['selector' => '.area-9', 'top' => 388, 'left' => 461, 'width' => 105, 'height' => 39],
            ['selector' => '.area-10', 'top' => 300, 'left' => 855, 'width' => 124, 'height' => 35],
            ['selector' => '.area-11', 'top' => 289, 'left' => 990, 'width' => 173, 'height' => 42],
            ['selector' => '.area-12', 'top' => 364, 'left' => 842, 'width' => 140, 'height' => 85],
            ['selector' => '.area-13', 'top' => 367, 'left' => 990, 'width' => 170, 'height' => 91],
        ],
        // Window Wraps -> room5
        'room5' => [
            ['selector' => '.area-1', 'top' => 215, 'left' => 238, 'width' => 213, 'height' => 317],
            ['selector' => '.area-2', 'top' => 235, 'left' => 550, 'width' => 148, 'height' => 265],
            ['selector' => '.area-3', 'top' => 567, 'left' => 1109, 'width' => 43, 'height' => 44],
            ['selector' => '.area-4', 'top' => 276, 'left' => 1026, 'width' => 189, 'height' => 198],
        ],
    ];

    // Optional filter by room via GET or env: prefer 'room' (number) then 'room_type' (roomN)
    $filterRoom = null;
    if (isset($_GET['room']) && $_GET['room'] !== '') {
        $r = $_GET['room'];
        if (preg_match('/^room(\d+)$/i', (string)$r, $m)) {
            $filterRoom = 'room' . (int)$m[1];
        } else {
            $filterRoom = 'room' . (int)$r;
        }
    } elseif (isset($_GET['room_type'])) {
        $filterRoom = $_GET['room_type'];
    } elseif (($env = getenv('ROOM')) !== false && $env !== '') {
        if (preg_match('/^room(\d+)$/i', (string)$env, $m)) {
            $filterRoom = 'room' . (int)$m[1];
        } else {
            $filterRoom = 'room' . (int)$env;
        }
    }

    if ($filterRoom && isset($maps[$filterRoom])) {
        $maps = [$filterRoom => $maps[$filterRoom]];
    }

    Database::beginTransaction();

    $applied = [];
    foreach ($maps as $roomType => $coords) {
        // Ensure a map row exists; prefer 'Original' name
        $row = Database::queryOne("SELECT id FROM room_maps WHERE room_type = ? AND map_name = 'Original' LIMIT 1", [$roomType]);

        if ($row) {
            // Update coordinates and ensure it's active
            $mapId = (int)$row['id'];
            Database::execute("UPDATE room_maps SET coordinates = ? WHERE id = ?", [json_encode($coords, JSON_UNESCAPED_SLASHES), $mapId]);
            // Activate this map and deactivate others for the room
            Database::execute("UPDATE room_maps SET is_active = 0 WHERE room_type = ? AND id <> ?", [$roomType, $mapId]);
            Database::execute("UPDATE room_maps SET is_active = 1 WHERE id = ?", [$mapId]);
            $applied[] = ['room_type' => $roomType, 'map_id' => $mapId, 'updated' => true];
        } else {
            // If any active map exists, update that one; else insert new
            $active = Database::queryOne("SELECT id FROM room_maps WHERE room_type = ? AND is_active = 1 LIMIT 1", [$roomType]);
            if ($active) {
                $mapId = (int)$active['id'];
                Database::execute("UPDATE room_maps SET map_name = 'Original', coordinates = ? WHERE id = ?", [json_encode($coords, JSON_UNESCAPED_SLASHES), $mapId]);
                $applied[] = ['room_type' => $roomType, 'map_id' => $mapId, 'updated' => true];
            } else {
                // Insert a new Original map and set active
                Database::execute("INSERT INTO room_maps (room_type, map_name, coordinates, is_active) VALUES (?, 'Original', ?, 1)", [$roomType, json_encode($coords, JSON_UNESCAPED_SLASHES)]);
                $mapId = (int)Database::lastInsertId();
                // Deactivate any others just in case
                Database::execute("UPDATE room_maps SET is_active = 0 WHERE room_type = ? AND id <> ?", [$roomType, $mapId]);
                $applied[] = ['room_type' => $roomType, 'map_id' => $mapId, 'inserted' => true];
            }
        }
    }

    Database::commit();
    echo json_encode(['ok' => true, 'applied' => $applied], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    try {
        Database::rollBack();
    } catch (Throwable $t) {
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
