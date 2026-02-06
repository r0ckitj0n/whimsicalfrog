<?php
/**
 * Save Email Configuration API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/secret_store.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../includes/business_settings_helper.php';
require_once __DIR__ . '/../includes/email/ConfigManager.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    Database::getInstance();

    if ($action === 'test') {
        EmailConfigManager::handleTestEmail();
    } elseif ($action === 'save') {
        $existing = BusinessSettings::getByCategory('email');
        EmailConfigManager::handleSaveConfig($_POST, $existing);
    } else {
        Response::error('Invalid action', null, 400);
    }
} catch (Exception $e) {
    Response::serverError($e->getMessage());
}
