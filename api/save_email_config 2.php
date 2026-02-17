<?php
/**
 * Save Email Configuration API
 * Following .windsurfrules: < 300 lines.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/response.php';
require_once __DIR__ . '/../includes/auth_helper.php';
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

AuthHelper::requireAdmin();

$input = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
    if (is_array($jsonInput)) {
        $input = array_merge($input, $jsonInput);
        $_POST = array_merge($_POST, $jsonInput);
    }
}

$action = trim((string) ($input['action'] ?? 'save'));

try {
    Database::getInstance();

    if ($action === 'test') {
        EmailConfigManager::handleTestEmail();
    } elseif ($action === 'save') {
        $existing = BusinessSettings::getByCategory('email');
        EmailConfigManager::handleSaveConfig($input, $existing);
    } else {
        Response::error('Invalid action', null, 400);
    }
} catch (Throwable $e) {
    Response::serverError($e->getMessage());
}
