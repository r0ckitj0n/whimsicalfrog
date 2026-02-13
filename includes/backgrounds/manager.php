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
        $room = normalizeRoomNumber($room);
        if ($room === '' || !ctype_digit((string)$id)) {
            throw new Exception('Missing room or background id');
        }

        $bg = Database::queryOne(
            "SELECT id, room_number, image_filename, png_filename, webp_filename FROM backgrounds WHERE id = ? LIMIT 1",
            [$id]
        );
        if (!$bg) {
            throw new Exception('Background not found');
        }
        if (normalizeRoomNumber((string)($bg['room_number'] ?? '')) !== $room) {
            throw new Exception('Background does not belong to the selected room');
        }

        Database::execute("UPDATE backgrounds SET is_active = 0 WHERE room_number = ?", [$room]);
        $updated = Database::execute("UPDATE backgrounds SET is_active = 1 WHERE id = ? AND room_number = ? LIMIT 1", [$id, $room]);
        if ($updated <= 0) {
            throw new Exception('Failed to activate background for room');
        }

        // Sync room_settings.background_url so room modal rendering stays consistent with the deployed background.
        $pickRel = trim((string)($bg['webp_filename'] ?? ''));
        if ($pickRel === '') $pickRel = trim((string)($bg['png_filename'] ?? ''));
        if ($pickRel === '') $pickRel = trim((string)($bg['image_filename'] ?? ''));
        $url = '';
        if ($pickRel !== '') {
            if (preg_match('/^https?:\\/\\//i', $pickRel)) {
                $url = $pickRel;
            } elseif (str_starts_with($pickRel, '/images/')) {
                $url = $pickRel;
            } elseif (str_starts_with($pickRel, 'images/')) {
                $url = '/' . $pickRel;
            } else {
                $url = '/images/' . ltrim($pickRel, '/');
            }
        }
        Database::execute(
            "UPDATE room_settings SET background_url = ? WHERE room_number = ?",
            [$url, $room]
        );
        Database::commit();

        // Invalidate get_background microcache (APCu or filesystem).
        $ck = 'room_bg:' . $room;
        if (function_exists('apcu_delete')) {
            @apcu_delete($ck);
        }
        $cacheFile = sys_get_temp_dir() . '/wf_cache_' . md5($ck) . '.json';
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    } catch (Exception $e) {
        Database::rollBack();
        throw $e;
    }
}
