<?php
require_once __DIR__ . '/api/config.php';
try {
    $pdo = Database::getInstance();
    $stmt = $pdo->query("SELECT room_number, background_image FROM room_maps WHERE is_active = 1");
    echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
