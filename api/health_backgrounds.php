<?php

// api/health_backgrounds.php
// Reports missing backgrounds and missing background files for rooms 0..5

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try {
    Database::getInstance();
    AuthHelper::requireAdmin();
} catch (Throwable $e) {
    Response::serverError('Database connection failed', $e->getMessage());
}

function bgFileExists(?string $filename): bool
{
    if (!$filename) {
        return false;
    }
    $f = ltrim((string)$filename, '/');
    // Normalize accidental duplicates like images/backgrounds/backgrounds/
    $f = preg_replace('#(?:^|/)backgrounds/backgrounds/#i', 'backgrounds/', $f);
    $f = preg_replace('#^images/backgrounds/backgrounds/#i', 'images/backgrounds/', $f);
    // Accept any of: images/backgrounds/foo.webp, backgrounds/foo.webp, or just foo.webp
    if (stripos($f, 'images/backgrounds/') === 0) {
        $rel = '/' . $f;
    } elseif (stripos($f, 'backgrounds/') === 0) {
        $rel = '/images/' . $f;
    } else {
        $rel = '/images/backgrounds/' . $f;
    }
    $abs = __DIR__ . '/..' . $rel;
    return is_file($abs);
}

try {
    // Collect distinct room identifiers (numeric or alphanumeric)
    $rooms = [];
    $push = function($v) use (&$rooms){ $s = trim((string)$v); if ($s !== '' && !in_array($s, $rooms, true)) $rooms[] = $s; };
    // From backgrounds
    try {
        $rs = Database::query("SELECT DISTINCT room_number FROM backgrounds");
        foreach ($rs as $r) { $push($r['room_number'] ?? ''); }
    } catch (Throwable $e) {
        error_log('[health_backgrounds] rooms from backgrounds query failed: ' . $e->getMessage());
    }
    // From room_settings
    try {
        $rs = Database::query("SELECT DISTINCT room_number FROM room_settings");
        foreach ($rs as $r) { $push($r['room_number'] ?? ''); }
    } catch (Throwable $e) {
        error_log('[health_backgrounds] rooms from room_settings query failed: ' . $e->getMessage());
    }
    // From room_maps
    try {
        $rs = Database::query("SELECT DISTINCT room_number FROM room_maps");
        foreach ($rs as $r) { $push($r['room_number'] ?? ''); }
    } catch (Throwable $e) {
        error_log('[health_backgrounds] rooms from room_maps query failed: ' . $e->getMessage());
    }
    // Ensure common defaults exist
    foreach (['0','A','S','X'] as $d) { $push($d); }
    $missingActive = [];
    $missingFiles = [];
    $details = [];

    foreach ($rooms as $rn) {
        $row = Database::queryOne("SELECT id, name, image_filename, webp_filename, is_active FROM backgrounds WHERE room_number = ? AND is_active = 1 ORDER BY id DESC LIMIT 1", [$rn]);
        if (!$row) {
            $missingActive[] = $rn;
            $details[] = [ 'room_number' => $rn, 'status' => 'no_active' ];
            continue;
        }
        $file = !empty($row['webp_filename']) ? $row['webp_filename'] : $row['image_filename'];
        if (!bgFileExists($file)) {
            $missingFiles[] = $rn;
            $details[] = [ 'room_number' => $rn, 'status' => 'active_missing_file', 'file' => $file ];
        } else {
            $details[] = [ 'room_number' => $rn, 'status' => 'ok', 'file' => $file ];
        }
    }

    Response::success([
        'missingActive' => $missingActive,
        'missingFiles' => $missingFiles,
        'details' => $details,
    ]);
} catch (Throwable $e) {
    Response::serverError('Health backgrounds failed', $e->getMessage());
}
