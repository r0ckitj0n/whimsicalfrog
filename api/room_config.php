<?php

// Room configuration API: get/save per-room settings as JSON
// Storage: project_root/data/room_configs/<room>.json
// Auth: admin required for save; read requires admin as this is an admin tool

require_once __DIR__ . '/api_bootstrap.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';

header('Content-Type: application/json; charset=utf-8');

function respond($arr, $code = 200)
{
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

// Basic admin guard (prefer AuthHelper)
$IS_ADMIN = false;
try {
    if (class_exists('AuthHelper')) {
        $IS_ADMIN = AuthHelper::isAdmin();
    } elseif (function_exists('isAdmin')) {
        $IS_ADMIN = isAdmin();
    }
} catch (Throwable $e) {
    $IS_ADMIN = false;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Normalize JSON body for POST
$input = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) {
        $parsed = json_decode($raw, true);
        if (is_array($parsed)) {
            $input = $parsed;
        }
        if (!$action && isset($parsed['action'])) {
            $action = (string)$parsed['action'];
        }
    }
}

$room = '';
if (isset($_GET['room'])) {
    $room = (string)$_GET['room'];
}
if (!$room && isset($input['room'])) {
    $room = (string)$input['room'];
}
$room = trim($room);

$storageDir = dirname(__DIR__) . '/data/room_configs';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0775, true);
}

function get_default_config()
{
    return [
        'show_delay' => 50,
        'hide_delay' => 150,
        'max_width' => 450,
        'min_width' => 280,
        'max_quantity' => 999,
        'min_quantity' => 1,
        'debounce_time' => 50,
        'popup_animation' => 'fade',
        'modal_animation' => 'scale',
        'enable_sales_check' => true,
        'show_category' => true,
        'show_description' => true,
        'enable_image_fallback' => true,
        'enable_colors' => true,
        'enable_sizes' => true,
        'show_unit_price' => true,
        'enable_stock_checking' => true,
        'click_to_details' => true,
        'hover_to_popup' => true,
        'popup_add_to_cart' => true,
        'enable_touch_events' => true,
    ];
}

if ($action === 'get') {
    if ($room === '') {
        respond(['success' => false, 'message' => 'Missing room parameter'], 400);
    }
    $file = $storageDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $room) . '.json';
    if (!file_exists($file)) {
        respond(['success' => true, 'config' => get_default_config()]);
    }
    $json = @file_get_contents($file);
    if ($json === false) {
        respond(['success' => false, 'message' => 'Failed to read room config'], 500);
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        // corrupt file fallback
        respond(['success' => true, 'config' => get_default_config(), 'warning' => 'Corrupt config; using defaults']);
    }
    respond(['success' => true, 'config' => $data]);
}

if ($action === 'save') {
    if (!$IS_ADMIN) {
        respond(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    if ($room === '') {
        respond(['success' => false, 'message' => 'Missing room parameter'], 400);
    }
    $config = $input['config'] ?? null;
    if (!is_array($config)) {
        respond(['success' => false, 'message' => 'Invalid config payload'], 400);
    }
    // Sanitize keys/values lightly
    $defaults = get_default_config();
    $out = $defaults;
    foreach ($config as $k => $v) {
        if (!array_key_exists($k, $defaults)) {
            continue;
        }
        // Coerce types
        if (is_bool($defaults[$k])) {
            $out[$k] = (bool)$v;
        } elseif (is_int($defaults[$k])) {
            $out[$k] = (int)$v;
        } else {
            $out[$k] = (string)$v;
        }
    }
    $file = $storageDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $room) . '.json';
    $ok = @file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if ($ok === false) {
        respond(['success' => false, 'message' => 'Failed to save room config'], 500);
    }
    respond(['success' => true, 'config' => $out]);
}

respond(['success' => false, 'message' => 'Unknown action'], 400);
