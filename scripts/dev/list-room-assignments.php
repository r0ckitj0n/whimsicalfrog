<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');
require_once __DIR__ . '/../../api/config.php';
try {
    Database::getInstance();
    $sql = "SELECT rca.room_number, rca.category_id, rca.is_primary, c.name AS category_name
            FROM room_category_assignments rca
            LEFT JOIN categories c ON c.id = rca.category_id
            ORDER BY rca.room_number, rca.is_primary DESC, rca.category_id";
    $rows = Database::queryAll($sql);
    echo json_encode(['ok' => true, 'rows' => $rows], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
