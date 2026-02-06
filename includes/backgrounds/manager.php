<?php
/**
 * Backgrounds Manager Logic
 */

function normalizeRoomNumber($room) {
    if ($room === null || $room === '') return '';
    $val = trim((string)$room);
    if (preg_match('/^room([0-9a-zA-Z]+)$/i', $val, $m)) return strtoupper((string)$m[1]);
    if (preg_match('/^[0-9]+$/', $val)) return ltrim($val, '+');
    if (preg_match('/^[a-zA-Z]$/', $val)) return strtoupper($val);
    return $val;
}

function getBackgrounds($room_number, $activeOnly = false) {
    $sql = "SELECT * FROM backgrounds WHERE room_number = ?";
    $params = [$room_number];
    if ($activeOnly) $sql .= " AND is_active = 1";
    $sql .= " ORDER BY name = 'Original' DESC, created_at DESC";
    $rows = Database::queryAll($sql, $params);
    
    if ($room_number === '0' && count($rows) === 0) {
        $rows = Database::queryAll("SELECT * FROM backgrounds WHERE (room_number = '0' OR room_number IS NULL OR room_number = '') ORDER BY name = 'Original' DESC, created_at DESC");
    }
    return $rows;
}

function saveBackground($data) {
    $room = normalizeRoomNumber($data['room'] ?? $data['room_number'] ?? '');
    $name = $data['name'] ?? '';
    $img = $data['image_filename'] ?? '';
    if (!$room || !$name || !$img) throw new Exception('Missing fields');
    
    $exists = Database::queryOne("SELECT id FROM backgrounds WHERE room_number = ? AND name = ? LIMIT 1", [$room, $name]);
    if ($exists) throw new Exception('Name exists for this room');
    
    return Database::execute("INSERT INTO backgrounds (room_number, name, image_filename, webp_filename, is_active) VALUES (?, ?, ?, ?, 0)", [$room, $name, $img, $data['webp_filename'] ?? null]);
}

function applyBackground($room, $id) {
    Database::beginTransaction();
    try {
        Database::execute("UPDATE backgrounds SET is_active = 0 WHERE room_number = ?", [$room]);
        Database::execute("UPDATE backgrounds SET is_active = 1 WHERE id = ?", [$id]);
        Database::commit();
    } catch (Exception $e) {
        Database::rollBack();
        throw $e;
    }
}
