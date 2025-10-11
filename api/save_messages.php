<?php
// Save site messages (e.g., shop encouragement phrases) into BusinessSettings
// POST JSON: { phrases: string[] }
// Persists under category 'messages', key 'shop_encouragement_phrases' as JSON

declare(strict_types=1);

ob_start();
error_reporting(0);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/business_settings_helper.php';

// Handle CORS preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    if (function_exists('ob_get_level') && ob_get_level() > 0) {@ob_clean();}
    echo json_encode(['success' => true]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    if (function_exists('ob_get_level') && ob_get_level() > 0) {@ob_clean();}
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        // Also accept form-encoded 'phrases' as newline/comma separated
        $fallback = isset($_POST['phrases']) ? (string)$_POST['phrases'] : '';
        if ($fallback !== '') {
            $parts = preg_split('/[\r\n,]+/', $fallback);
            $data = ['phrases' => $parts];
        } else {
            throw new Exception('Invalid request body');
        }
    }

    $phrases = $data['phrases'] ?? [];
    if (!is_array($phrases)) {
        throw new Exception('phrases must be an array');
    }

    // Normalize: trim, remove empties, de-duplicate, cap at 50
    $norm = [];
    foreach ($phrases as $p) {
        $t = trim((string)$p);
        if ($t !== '' && !in_array($t, $norm, true)) {
            $norm[] = $t;
        }
        if (count($norm) >= 50) break;
    }

    // Store JSON array
    $stored = json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Upsert into business_settings
    $params = [
        ':category' => 'messages',
        ':key' => 'shop_encouragement_phrases',
        ':value' => $stored,
        ':type' => 'json',
        ':display_name' => 'Shop Encouragement Phrases',
        ':description' => 'Phrases shown as badges on recommended items when search has no exact matches',
    ];

    $affected = Database::execute("UPDATE business_settings
        SET setting_value = :value, setting_type = :type, display_name = :display_name, description = :description, updated_at = CURRENT_TIMESTAMP
        WHERE category = :category AND setting_key = :key", $params);

    if ($affected <= 0) {
        Database::execute("INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description, updated_at)
            VALUES (:category, :key, :value, :type, :display_name, :description, CURRENT_TIMESTAMP)", $params);
    }

    if (class_exists('BusinessSettings')) {
        BusinessSettings::clearCache();
    }

    if (function_exists('ob_get_level') && ob_get_level() > 0) {@ob_clean();}
    echo json_encode(['success' => true, 'count' => count($norm)]);
} catch (Throwable $e) {
    if (function_exists('ob_get_level') && ob_get_level() > 0) {@ob_clean();}
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
