<?php
// Migration script to set background_display_type to 'modal' for rooms 1-5
require_once __DIR__ . '/../includes/database.php';

try {
    $pdo = Database::getInstance();
    $sql = "UPDATE room_settings SET background_display_type='modal' WHERE room_number IN (1,2,3,4,5)";
    $count = $pdo->exec($sql);
    echo "✅ Updated {$count} rows to 'modal'." . PHP_EOL;
} catch (Exception $e) {
    echo "❌ Error updating rooms: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
