<?php
// API: Manage receipt sales verbiage messages stored in business_settings category 'sales'
// GET  -> { success: true, messages: { receipt_thank_you_message, receipt_next_steps, receipt_social_sharing, receipt_return_customer } }
// POST -> { success: true }

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
        $settings = BusinessSettings::getByCategory('sales');
        $keys = [
            'receipt_thank_you_message' => '',
            'receipt_next_steps' => '',
            'receipt_social_sharing' => '',
            'receipt_return_customer' => ''
        ];
        $out = [];
        foreach ($keys as $k => $def) {
            $out[$k] = isset($settings[$k]) ? (string)$settings[$k] : $def;
        }
        echo json_encode(['success' => true, 'messages' => $out]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) { throw new Exception('Invalid JSON'); }

    $allowed = [
        'receipt_thank_you_message',
        'receipt_next_steps',
        'receipt_social_sharing',
        'receipt_return_customer'
    ];

    Database::beginTransaction();
    try {
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $payload)) continue;
            $val = trim((string)$payload[$k]);
            $params = [
                ':category' => 'sales',
                ':key' => $k,
                ':value' => $val,
                ':type' => 'text',
                ':display_name' => ucwords(str_replace('_',' ', $k)),
                ':description' => 'Receipt message: ' . $k
            ];
            $affected = Database::execute("UPDATE business_settings
                SET setting_value = :value, setting_type = :type, display_name = :display_name, description = :description, updated_at = CURRENT_TIMESTAMP
                WHERE category = :category AND setting_key = :key", $params);
            if ($affected <= 0) {
                Database::execute("INSERT INTO business_settings (category, setting_key, setting_value, setting_type, display_name, description, updated_at)
                    VALUES (:category, :key, :value, :type, :display_name, :description, CURRENT_TIMESTAMP)", $params);
            }
        }
        Database::commit();
    } catch (Throwable $e) {
        Database::rollBack();
        throw $e;
    }

    if (class_exists('BusinessSettings')) { BusinessSettings::clearCache(); }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
