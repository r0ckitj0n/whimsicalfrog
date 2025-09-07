<?php
// scripts/dev/list-categories.php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../api/config.php';
try {
    Database::getInstance();
    $rows = Database::queryAll('SELECT id, name FROM categories ORDER BY id');
    echo json_encode(['ok' => true, 'count' => count($rows), 'categories' => $rows], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
