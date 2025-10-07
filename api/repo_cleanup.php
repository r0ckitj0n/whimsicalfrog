<?php
// API endpoint to orchestrate repository audit & cleanup
// Actions: audit, execute, restore, latest_audit

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/config.php';
} catch (Throwable $e) {
    // continue with minimal env
}

function json_out($arr, $code = 200) {
    http_response_code($code);
    echo json_encode($arr);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? null;

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: 'null', true) ?: [];

$projectRoot = dirname(__DIR__);
$scriptPath = $projectRoot . '/scripts/maintenance/audit_cleanup.mjs';

if (!is_file($scriptPath)) {
    json_out(['success' => false, 'error' => 'Script not found', 'script' => $scriptPath], 500);
}

function run_node_script($scriptPath, $args = []) {
    $parts = [];
    $parts[] = 'node';
    $parts[] = escapeshellarg($scriptPath);
    foreach ($args as $a) { $parts[] = $a; }
    $cmd = implode(' ', $parts);
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);
    $text = implode("\n", $out);
    $json = null;
    if ($text) {
        $dec = json_decode($text, true);
        if (is_array($dec)) { $json = $dec; }
    }
    return [$code, $text, $json];
}

switch ($action) {
    case 'audit': {
        $cats = isset($body['categories']) && is_string($body['categories']) ? $body['categories'] : '';
        $args = ['--root', escapeshellarg($projectRoot)];
        if ($cats) { $args[] = '--categories'; $args[] = escapeshellarg($cats); }
        [$code, $text, $json] = run_node_script($scriptPath, $args);
        if ($json) json_out($json, $code === 0 ? 200 : 500);
        json_out(['success'=>false,'error'=>'Non-JSON output','output'=>$text], 500);
    }
    case 'execute': {
        $cats = isset($body['categories']) && is_string($body['categories']) ? $body['categories'] : '';
        $args = ['--root', escapeshellarg($projectRoot), '--execute'];
        if ($cats) { $args[] = '--categories'; $args[] = escapeshellarg($cats); }
        [$code, $text, $json] = run_node_script($scriptPath, $args);
        if ($json) json_out($json, $code === 0 ? 200 : 500);
        json_out(['success'=>false,'error'=>'Non-JSON output','output'=>$text], 500);
    }
    case 'restore': {
        $ts = isset($body['timestamp']) ? trim((string)$body['timestamp']) : '';
        if (!$ts) json_out(['success'=>false,'error'=>'timestamp required'], 400);
        $args = ['--root', escapeshellarg($projectRoot), '--restore', escapeshellarg($ts)];
        [$code, $text, $json] = run_node_script($scriptPath, $args);
        if ($json) json_out($json, $code === 0 ? 200 : 500);
        json_out(['success'=>false,'error'=>'Non-JSON output','output'=>$text], 500);
    }
    case 'latest_audit': {
        $dir = $projectRoot . '/reports/cleanup';
        $latest = null;
        if (is_dir($dir)) {
            $entries = scandir($dir, SCANDIR_SORT_DESCENDING);
            foreach ($entries as $e) {
                if ($e === '.' || $e === '..') continue;
                if (!is_dir($dir . '/' . $e)) continue;
                // expects audit.json inside each timestamp folder
                $p = $dir . '/' . $e . '/audit.json';
                if (is_file($p)) { $latest = $p; break; }
            }
        }
        if (!$latest) json_out(['success'=>true, 'data'=>null]);
        $json = json_decode(file_get_contents($latest) ?: 'null', true);
        json_out(['success'=>true, 'data'=>$json]);
    }
    default:
        json_out(['success'=>false,'error'=>'Unknown or missing action'], 400);
}
