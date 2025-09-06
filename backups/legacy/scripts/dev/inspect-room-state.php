<?php
// scripts/dev/inspect-room-state.php
// Dumps active backgrounds, room_settings, and active room_maps for quick diagnosis
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../api/config.php';

    $pdo = Database::getInstance();

    // Active backgrounds
    $stmt = $pdo->prepare("SELECT room_type, background_name, image_filename, webp_filename, is_active FROM backgrounds WHERE is_active = 1 ORDER BY room_type");
    $stmt->execute();
    $backgrounds = $stmt->fetchAll();
    // All backgrounds per room_type too
    $stmt = $pdo->prepare("SELECT room_type, background_name, image_filename, webp_filename, is_active FROM backgrounds ORDER BY room_type, is_active DESC, background_name");
    $stmt->execute();
    $backgroundsAll = $stmt->fetchAll();

    // Room settings (active) - handle optional column background_display_type
    $hasBgDisplayType = false;
    try {
        $pdo->query("SELECT background_display_type FROM room_settings LIMIT 1");
        $hasBgDisplayType = true;
    } catch (Throwable $e) {
        $hasBgDisplayType = false;
    }
    if ($hasBgDisplayType) {
        $stmt = $pdo->prepare("SELECT room_number, room_name, door_label, description, display_order, background_display_type, is_active FROM room_settings WHERE is_active = 1 ORDER BY display_order, room_number");
    } else {
        $stmt = $pdo->prepare("SELECT room_number, room_name, door_label, description, display_order, is_active FROM room_settings WHERE is_active = 1 ORDER BY display_order, room_number");
    }
    $stmt->execute();
    $rooms = $stmt->fetchAll();

    // Active room maps
    $stmt = $pdo->prepare("SELECT room_type, map_name, id FROM room_maps WHERE is_active = 1 ORDER BY room_type");
    $stmt->execute();
    $activeMaps = $stmt->fetchAll();
    // Count per room_type
    $stmt = $pdo->prepare("SELECT room_type, COUNT(*) as cnt, SUM(is_active = TRUE) as active_count FROM room_maps GROUP BY room_type ORDER BY room_type");
    $stmt->execute();
    $mapCounts = $stmt->fetchAll();

    echo json_encode([
        'ok' => true,
        'backgrounds_active' => $backgrounds,
        'backgrounds_all' => $backgroundsAll,
        'room_settings_active' => $rooms,
        'room_maps_active' => $activeMaps,
        'room_maps_counts' => $mapCounts,
    ], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
