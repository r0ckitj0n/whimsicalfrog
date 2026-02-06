<?php
require_once __DIR__ . '/api/config.php';
try {
    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT area_selector, link_label, content_target FROM area_mappings WHERE room_number = '0' AND is_active = 1");
    $stmt->execute();
    echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
