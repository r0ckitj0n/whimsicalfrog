<?php
require_once __DIR__ . '/api/config.php';
try {
    $pdo = Database::getInstance();
    $stmt = $pdo->query("DESCRIBE room_settings");
    echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
