<?php
// Migration script to add background_display_type column to room_settings table
require_once __DIR__ . '/../includes/database.php';

try {
    $pdo = Database::getInstance();
    $sql = "ALTER TABLE room_settings ADD COLUMN background_display_type VARCHAR(20) NOT NULL DEFAULT 'fullscreen' AFTER room_number";
    $pdo->exec($sql);
    echo "✅ Column background_display_type added successfully." . PHP_EOL;
} catch (Exception $e) {
    echo "❌ Error adding column: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
