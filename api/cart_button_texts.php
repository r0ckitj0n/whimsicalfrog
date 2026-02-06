<?php
/**
 * API: Manage Add-to-Cart button text variations
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/CartButtonTextManager.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = strtolower(trim($_GET['action'] ?? ''));

try {
    if ($method === 'OPTIONS') {
        http_response_code(200);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($method === 'GET' || ($method === 'GET' && $action === 'list')) {
        echo json_encode(['success' => true, 'texts' => CartButtonTextManager::load()]);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload))
        throw new Exception('Invalid JSON');

    switch ($action) {
        case 'add':
            $id = CartButtonTextManager::add($payload['text'] ?? '');
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'update':
            CartButtonTextManager::update((int) ($payload['id'] ?? 0), $payload['text'] ?? '');
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            CartButtonTextManager::delete((int) ($payload['id'] ?? 0));
            echo json_encode(['success' => true]);
            break;

        default:
            // Legacy / Replace All
            $count = CartButtonTextManager::replaceAll($payload['texts'] ?? []);
            echo json_encode(['success' => true, 'count' => $count]);
            break;
    }

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
