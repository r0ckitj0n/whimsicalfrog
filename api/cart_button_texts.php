<?php
// API: Manage Add-to-Cart button text variations
// GET  -> { success: true, texts: string[] }
// POST -> { success: true } with JSON { texts: string[] }

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/business_settings_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($method === 'GET') {
        // Read from business_settings (key: cart_button_texts)
        $raw = BusinessSettings::get('cart_button_texts', '[]');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $texts = is_array($decoded) ? $decoded : [];
        } else {
            $texts = is_array($raw) ? $raw : [];
        }
        echo json_encode(['success' => true, 'texts' => array_values(array_filter(array_map('trim', $texts), fn($s) => $s !== ''))]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new Exception('Invalid JSON');
    }
    $texts = $payload['texts'] ?? [];
    if (!is_array($texts)) {
        throw new Exception('texts must be an array');
    }
    // Normalize: trim, dedupe, limit 100
    $norm = [];
    foreach ($texts as $t) {
        $s = trim((string)$t);
        if ($s !== '' && !in_array($s, $norm, true)) {
            $norm[] = $s;
        }
        if (count($norm) >= 100) break;
    }

    // Upsert into business_settings with schema detection (supports older schemas missing optional columns)
    $stored = json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $baseParams = [
        ':category' => 'messages',
        ':key' => 'cart_button_texts',
        ':value' => $stored,
        ':type' => 'json',
        ':display_name' => 'Cart Button Text Variations',
        ':description' => 'Phrases randomly used for Add to Cart buttons',
    ];

    // Probe table columns to adapt statements
    $cols = [];
    try {
        $rows = Database::queryAll('SHOW COLUMNS FROM business_settings');
        foreach ($rows as $r) { if (isset($r['Field'])) { $cols[] = (string)$r['Field']; } }
    } catch (Throwable $e) {
        // If SHOW COLUMNS fails, fallback to safest minimal set
    }
    $hasType = in_array('setting_type', $cols, true);
    $hasDisplay = in_array('display_name', $cols, true);
    $hasDesc = in_array('description', $cols, true);
    $hasUpdated = in_array('updated_at', $cols, true);

    // Decide path by existence to avoid duplicate insert when UPDATE affects 0 rows
    $exists = false;
    try {
        $rowExists = Database::queryOne('SELECT 1 AS present FROM business_settings WHERE setting_key = :key LIMIT 1', [':key' => $baseParams[':key']]);
        $exists = isset($rowExists['present']);
    } catch (Throwable $e) { /* if probe fails, fall back to UPDATE->INSERT flow */ }

    // Build dynamic SET for UPDATE
    $setParts = ['setting_value = :value'];
    if ($hasType) { $setParts[] = 'setting_type = :type'; }
    if ($hasDisplay) { $setParts[] = 'display_name = :display_name'; }
    if ($hasDesc) { $setParts[] = 'description = :description'; }
    if ($hasUpdated) { $setParts[] = 'updated_at = CURRENT_TIMESTAMP'; }

    if ($exists) {
        // Update by setting_key only (works with UNIQUE(setting_key))
        $updateSqlKeyOnly = 'UPDATE business_settings SET ' . implode(', ', $setParts) . ' WHERE setting_key = :key';
        $updParamsKeyOnly = [ ':key' => $baseParams[':key'], ':value' => $baseParams[':value'] ];
        if ($hasType) { $updParamsKeyOnly[':type'] = $baseParams[':type']; }
        if ($hasDisplay) { $updParamsKeyOnly[':display_name'] = $baseParams[':display_name']; }
        if ($hasDesc) { $updParamsKeyOnly[':description'] = $baseParams[':description']; }
        Database::execute($updateSqlKeyOnly, $updParamsKeyOnly);
    } else {
        // Insert new row (respecting available columns)
        $insCols = ['category','setting_key','setting_value'];
        $insVals = [':category',':key',':value'];
        if ($hasType) { $insCols[] = 'setting_type'; $insVals[] = ':type'; }
        if ($hasDisplay) { $insCols[] = 'display_name'; $insVals[] = ':display_name'; }
        if ($hasDesc) { $insCols[] = 'description'; $insVals[] = ':description'; }
        if ($hasUpdated) { $insCols[] = 'updated_at'; $insVals[] = 'CURRENT_TIMESTAMP'; }

        $insertSql = 'INSERT INTO business_settings (' . implode(', ', $insCols) . ') VALUES (' . implode(', ', $insVals) . ')';
        $insParams = [ ':category' => $baseParams[':category'], ':key' => $baseParams[':key'], ':value' => $baseParams[':value'] ];
        if ($hasType) { $insParams[':type'] = $baseParams[':type']; }
        if ($hasDisplay) { $insParams[':display_name'] = $baseParams[':display_name']; }
        if ($hasDesc) { $insParams[':description'] = $baseParams[':description']; }
        Database::execute($insertSql, $insParams);
    }

    if (class_exists('BusinessSettings')) {
        BusinessSettings::clearCache();
    }

    echo json_encode(['success' => true, 'count' => count($norm)]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
