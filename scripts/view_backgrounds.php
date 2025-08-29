<?php
require_once __DIR__ . '/../api/config.php';

echo "--- Fetching current entries from 'backgrounds' table ---\n\n";

try {
    $pdo = Database::getInstance();
    $stmt = $pdo->query('SELECT * FROM backgrounds ORDER BY room_type');
    $backgrounds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($backgrounds) > 0) {
        foreach ($backgrounds as $bg) {
            echo "ID: {$bg['id']}\n";
            echo "  Room Type: {$bg['room_type']}\n";
            echo "  Name: {$bg['background_name']}\n";
            echo "  PNG File: {$bg['image_filename']}\n";
            echo "  WebP File: {$bg['webp_filename']}\n";
            echo "  Is Active: {$bg['is_active']}\n";
            echo "-----------------------------------------------------\n";
        }
    } else {
        echo "No entries found in the 'backgrounds' table.\n";
    }

} catch (Exception $e) {
    die("An error occurred: " . $e->getMessage() . "\n");
}
