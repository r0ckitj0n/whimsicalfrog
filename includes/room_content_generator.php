<?php
/**
 * Room Content Generator Helper
 * Extracted from api/load_room_content.php to reduce size.
 */

require_once __DIR__ . '/functions.php';

function wf_normalize_icon_panel_color_value($value)
{
    if ($value === null)
        return null;
    $raw = trim((string) $value);
    if ($raw === '' || strcasecmp($raw, 'transparent') === 0 || strcasecmp($raw, 'none') === 0)
        return 'transparent';

    if (strpos($raw, 'var(') === 0 || preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $raw)) {
        if (strpos($raw, '#') === 0 && strlen($raw) === 4) {
            $raw = '#' . $raw[1] . $raw[1] . $raw[2] . $raw[2] . $raw[3] . $raw[3];
        }
        return $raw;
    }
    return null;
}

function wf_cache_get($key)
{
    if (function_exists('apcu_fetch')) {
        $ok = false;
        $v = apcu_fetch($key, $ok);
        return $ok ? $v : null;
    }
    $f = sys_get_temp_dir() . '/wf_cache_' . md5($key) . '.json';
    if (is_file($f) && (time() - filemtime($f)) < 300) {
        $s = @file_get_contents($f);
        if ($s !== false) {
            $j = json_decode($s, true);
            if (is_array($j))
                return $j;
        }
    }
    return null;
}

function wf_cache_set($key, $val)
{
    if (function_exists('apcu_store')) {
        apcu_store($key, $val, 300);
        return;
    }
    $f = sys_get_temp_dir() . '/wf_cache_' . md5($key) . '.json';
    @file_put_contents($f, json_encode($val));
}

function getRoomMetadata($room_number, $pdo)
{
    $rs = Database::queryOne("SELECT room_name, description, render_context, background_url, target_aspect_ratio FROM room_settings WHERE room_number = ?", [$room_number]) ?: [];
    $categoryRows = Database::queryAll(
        "SELECT c.id AS category_id, c.name AS category_name, rca.is_primary, rca.display_order
        FROM room_category_assignments rca
        JOIN categories c ON rca.category_id = c.id
        WHERE rca.room_number = ?
        ORDER BY rca.is_primary DESC, rca.display_order ASC, c.id ASC",
        [$room_number]
    );
    $categoryIds = [];
    $categoryNames = [];
    foreach ($categoryRows as $row) {
        $catId = (int) ($row['category_id'] ?? 0);
        if ($catId > 0 && !in_array($catId, $categoryIds, true)) {
            $categoryIds[] = $catId;
        }
        $catName = trim((string) ($row['category_name'] ?? ''));
        if ($catName !== '' && !in_array($catName, $categoryNames, true)) {
            $categoryNames[] = $catName;
        }
    }
    return [
        'room_number' => $room_number,
        'room_name' => $rs['room_name'] ?? '',
        'description' => $rs['description'] ?? '',
        'render_context' => $rs['render_context'] ?? 'modal',
        'background_url' => $rs['background_url'] ?? '',
        'target_aspect_ratio' => $rs['target_aspect_ratio'] ?? null,
        'category' => implode(', ', $categoryNames),
        'category_id' => $categoryIds[0] ?? null,
        'category_ids' => $categoryIds,
        'categories' => $categoryNames
    ];
}

function loadRoomCoordinates($roomType, $pdo)
{
    try {
        $raw = trim((string) $roomType);
        $lv = strtolower($raw);
        if (in_array($lv, ['main', 'room_main', 'room-main', 'roommain'], true)) {
            $room_number = '0';
        } elseif (in_array($lv, ['landing', 'room_landing', 'room-landing'], true)) {
            $room_number = 'A';
        } elseif (preg_match('/^room(\d+)$/i', $raw, $m)) {
            $room_number = (string) ((int) $m[1]);
        } elseif (preg_match('/^room([A-Za-z])$/', $raw, $m)) {
            $room_number = strtoupper($m[1]);
        } else {
            $room_number = $raw;
        }
        $row = Database::queryOne(
            "SELECT coordinates FROM room_maps WHERE room_number = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT 1",
            [$room_number]
        );
        if ($row) {
            $coords = json_decode($row['coordinates'], true);
            if (!empty($coords))
                return ['coordinates' => $coords];
        }
    } catch (Exception $e) {
        error_log('coords error: ' . $e->getMessage());
    }
    return ['coordinates' => []];
}


function getImageUrl($path, $dir, $ext = 'webp')
{
    if (empty($path))
        return '';
    $cleanPath = ltrim($path, '/');
    if (strpos($cleanPath, 'images/' . $dir . '/') === 0) {
        return '/' . preg_replace('/\.[^\.]+$/', '.' . (($ext === 'png') ? 'png' : 'webp'), $cleanPath);
    }
    return '/images/' . trim($dir, '/') . '/' . preg_replace('/\.[^\.]+$/', '.' . (($ext === 'png') ? 'png' : 'webp'), $cleanPath);
}
