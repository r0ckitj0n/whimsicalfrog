<?php
// Lightweight bootstrap (api_bootstrap/config handle JSON/CORS in this project)
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
// Capture earliest request time and expose for DevTools
$__wf_req_start = microtime(true);
header('X-WF-Request-Start: ' . number_format($__wf_req_start, 6, '.', ''));
require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/room_helpers.php';
require_once __DIR__ . '/../includes/response.php';

// Get active background for a room

// Use centralized configuration (api/config.php) for Database::getInstance()

// IMPORTANT: release PHP session lock ASAP so API calls don't serialize
if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    @session_write_close();
}

// Simple microcache helpers (APCu preferred, fallback to filesystem)
function wf_cache_get($key) {
    if (function_exists('apcu_fetch')) {
        $ok = false; $v = apcu_fetch($key, $ok); return $ok ? $v : null;
    }
    $f = sys_get_temp_dir() . '/wf_cache_' . md5($key) . '.json';
    if (is_file($f) && (time() - filemtime($f)) < 60) { $s = @file_get_contents($f); if ($s !== false) { $j = json_decode($s, true); if (is_array($j)) return $j; } }
    return null;
}
function wf_cache_set($key, $val) {
    if (function_exists('apcu_store')) { apcu_store($key, $val, 60); return; }
    $f = sys_get_temp_dir() . '/wf_cache_' . md5($key) . '.json';
    @file_put_contents($f, json_encode($val));
}

/**
 * Generate dynamic fallback backgrounds based on room data
 */
function generateDynamicFallbacks()
{
    $fallbacks = [
        'landing' => ['png' => 'background_home.png', 'webp' => 'background_home.webp'],
        'room_main' => ['png' => 'background_room_main.png', 'webp' => 'background_room_main.webp']
    ];

    // Get all valid rooms from database
    $validRooms = getAllValidRooms();

    foreach ($validRooms as $roomNumber) {
        if (!in_array($roomNumber, ['A', 'B'])) {
            // Generate default filenames for product rooms
            $fallbacks["room{$roomNumber}"] = [
                'png' => "background_room{$roomNumber}.png",
                'webp' => "background_room{$roomNumber}.webp"
            ];
        }
    }

    return $fallbacks;
}

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }
} catch (PDOException $e) {
    // Fail fast: no fallback backgrounds on DB error
    Response::serverError('Database unavailable: ' . $e->getMessage());
}

// New contract: use 'room' (0..5) or 'room_number' - include 0 for landing page
$roomParam = $_GET['room'] ?? $_GET['room_number'] ?? '';
if ($roomParam === '') {
    Response::error('Room is required (use room=0..5, where 0=landing)', null, 400);
}
if (preg_match('/^room(\d+)$/i', (string)$roomParam, $m)) {
    $roomNumber = (string)((int)$m[1]);
} else {
    $roomNumber = (string)((int)$roomParam);
}
if (!preg_match('/^[0-5]$/', $roomNumber)) {
    Response::error('Invalid room. Expected 0-5 (where 0=landing).', null, 400);
}

try {
    // Try microcache first
    $ck = 'room_bg:' . $roomNumber;
    $cached = wf_cache_get($ck);
    if ($cached) { Response::json($cached); }

    // Get active background for the room by room_number
    $rn = $roomNumber;
    $t0 = microtime(true);
    $background = Database::queryOne(
        "SELECT background_name, image_filename, webp_filename, created_at 
         FROM backgrounds 
         WHERE room_number = ? AND is_active = 1 
         LIMIT 1",
        [$rn]
    );
    $t1 = microtime(true);

    if ($background) {
        $resp = ['success' => true, 'background' => $background];
        if ((isset($_GET['perf']) && $_GET['perf'] == '1')) {
            $dur = (int)round(($t1 - $t0) * 1000);
            $resp['perf'] = [
                'query_ms' => $dur,
                'server_received_at' => number_format($__wf_req_start, 6, '.', ''),
                'server_finished_at' => number_format($t1, 6, '.', ''),
            ];
            header('Server-Timing: bg;desc="get_background";dur=' . $dur);
            header('X-WF-Server-Perf: query_ms=' . $dur);
            header('X-WF-Request-Finish: ' . number_format($t1, 6, '.', ''));
        }
        wf_cache_set($ck, $resp);
        Response::json($resp);
    } else {
        // Strict: no background configured for this room
        Response::notFound('No active background found for room ' . $roomNumber);
    }
} catch (PDOException $e) {
    // Strict: surface DB errors to caller
    Response::serverError('Database error: ' . $e->getMessage());
}
?> 