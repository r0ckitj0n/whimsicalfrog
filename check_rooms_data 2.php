<?php
require_once __DIR__ . '/api/config.php';
try {
    $pdo = Database::getInstance();
    $stmt = $pdo->query("SELECT room_number, icon_panel_color, has_icons_white_background FROM room_settings");
    echo json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
