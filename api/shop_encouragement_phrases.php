<?php
// API: Manage Shop Encouragement Phrases
// GET  -> { success: true, phrases: string[] }
// POST -> { success: true } with JSON { phrases: string[] }

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
        // Read from business_settings (key: shop_encouragement_phrases)
        $raw = BusinessSettings::get('shop_encouragement_phrases', '[]');
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $phrases = is_array($decoded) ? $decoded : [];
        } else {
            $phrases = is_array($raw) ? $raw : [];
        }
        echo json_encode([
            'success' => true,
            'phrases' => array_values(array_filter(array_map('trim', $phrases), fn($s) => $s !== '')),
        ]);
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

    $phrases = $payload['phrases'] ?? [];
    if (!is_array($phrases)) {
        throw new Exception('phrases must be an array');
    }

    // Normalize: trim, dedupe, limit 100
    $norm = [];
    foreach ($phrases as $p) {
        $s = trim((string)$p);
        if ($s !== '' && !in_array($s, $norm, true)) {
            $norm[] = $s;
        }
        if (count($norm) >= 100) break;
    }

    // Upsert into business_settings
    $stored = json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $params = [
        ':category' => 'messages',
        ':key' => 'shop_encouragement_phrases',
        ':value' => $stored,
        ':type' => 'json',
        ':display_name' => 'Shop Encouragement Phrases',
        ':description' => 'Phrases shown as badges on recommended items',
    ];

    $affected = Database::execute("UPDATE business_settings
        SET setting_value = :value, setting_type = :type, display_name = :display_name, description = :description, updated_at = CURRENT_TIMESTAMP
        WHERE category = :category AND setting_key = :key", $params);
    if ($affected <= 0) {
        Database::execute("INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description, updated_at)
            VALUES (:category, :key, :value, :type, :display_name, :description, CURRENT_TIMESTAMP)", $params);
    }

    BusinessSettings::clearCache();

    echo json_encode(['success' => true, 'count' => count($norm)]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
