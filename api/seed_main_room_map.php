<?php
// api/seed_main_room_map.php — Seed/activate a pixel map for room '0' (Main Room) using 1280×896 baseline
// Auth: Admin session required

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try {
    // Require admin
    AuthHelper::requireAdmin();

    Database::beginTransaction();

    $baselineW = 1280.0;
    $baselineH = 896.0;

    // Percent-based positions from legacy CSS, converted at runtime to pixels
    $defs = [
        [ 'selector' => '.area-1', 'topPct' => 19.2, 'leftPct' => 2.3, 'widthPct' => 18.3, 'heightPct' => 26.0 ],
        [ 'selector' => '.area-2', 'topPct' => 45.0, 'leftPct' => 30.5, 'widthPct' => 15.8, 'heightPct' => 26.9 ],
        [ 'selector' => '.area-3', 'topPct' => 30.2, 'leftPct' => 58.8, 'widthPct' => 13.3, 'heightPct' => 26.2 ],
        [ 'selector' => '.area-4', 'topPct' => 17.5, 'leftPct' => 38.0, 'widthPct' => 14.8, 'heightPct' => 25.7 ],
        [ 'selector' => '.area-5', 'topPct' => 32.5, 'leftPct' => 78.2, 'widthPct' => 15.4, 'heightPct' => 28.5 ],
    ];

    $coords = [];
    foreach ($defs as $d) {
        $coords[] = [
            'selector' => $d['selector'],
            'top' => ($d['topPct'] / 100.0) * $baselineH,
            'left' => ($d['leftPct'] / 100.0) * $baselineW,
            'width' => ($d['widthPct'] / 100.0) * $baselineW,
            'height' => ($d['heightPct'] / 100.0) * $baselineH,
        ];
    }
    $payload = json_encode($coords, JSON_UNESCAPED_SLASHES);

    // Upsert a named seed map for room '0'
    $mapName = 'Main Room Seed';
    $existing = Database::queryOne("SELECT id FROM room_maps WHERE room_number = '0' AND map_name = ? LIMIT 1", [$mapName]);
    if ($existing && isset($existing['id'])) {
        $id = (int)$existing['id'];
        Database::execute("UPDATE room_maps SET coordinates = ?, updated_at = NOW() WHERE id = ?", [$payload, $id]);
        // Activate this map and deactivate others for room 0
        Database::execute("UPDATE room_maps SET is_active = 0 WHERE room_number = '0' AND id <> ?", [$id]);
        Database::execute("UPDATE room_maps SET is_active = 1 WHERE id = ?", [$id]);
    } else {
        Database::execute(
            "INSERT INTO room_maps (room_number, map_name, coordinates, is_active, created_at, updated_at)
             VALUES ('0', ?, ?, 1, NOW(), NOW())",
            [$mapName, $payload]
        );
        $id = (int)Database::lastInsertId();
        // Deactivate any other maps for room 0
        Database::execute("UPDATE room_maps SET is_active = 0 WHERE room_number = '0' AND id <> ?", [$id]);
    }

    Database::commit();

    echo json_encode([
        'success' => true,
        'room_number' => '0',
        'map_name' => $mapName,
        'coordinates' => $coords,
    ]);
} catch (Throwable $e) {
    try { Database::rollBack(); } catch (Throwable $__e) {}
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
}
