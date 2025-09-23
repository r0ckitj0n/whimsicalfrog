<?php
/**
 * Database Migration Script: Change Room Numbering from 2-6 to 1-5
 *
 * This script safely migrates all room-related data from the old numbering system
 * (rooms 2-6) to the new numbering system (rooms 1-5).
 */

header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

try {
    Database::getInstance();

    // Start transaction to ensure data integrity
    Database::beginTransaction();

    echo "Starting room numbering migration from 2-6 to 1-5...\n";

    // Create a temporary table to store the mapping
    $mappingTable = "
    CREATE TEMPORARY TABLE room_number_mapping (
        old_room_number INT,
        new_room_number INT
    )";
    Database::execute($mappingTable);

    // Insert mapping data
    $mappings = [
        [2, 1], // T-shirts: room2 → room1
        [3, 2], // Tumblers: room3 → room2
        [4, 3], // Artwork: room4 → room3
        [5, 4], // Sublimation: room5 → room4
        [6, 5]  // Window wraps: room6 → room5
    ];

    foreach ($mappings as $mapping) {
        Database::execute("INSERT INTO room_number_mapping (old_room_number, new_room_number) VALUES (?, ?)", $mapping);
    }

    // First, temporarily move the Main Room to avoid conflicts
    echo "Temporarily moving Main Room to avoid conflicts...\n";
    Database::execute("UPDATE room_settings SET room_number = ? WHERE room_number = ?", [999, 1]);
    Database::execute("UPDATE room_category_assignments SET room_number = ? WHERE room_number = ?", [999, 1]);
    $tableCheck = Database::queryAll("SHOW TABLES LIKE 'room_config'");
    if (count($tableCheck) > 0) {
        Database::execute("UPDATE room_config SET room_number = ? WHERE room_number = ?", [999, 1]);
    }

    // Update product rooms to temporary negative numbers to avoid conflicts
    echo "Updating to temporary room numbers...\n";
    $tempMappings = [
        [2, -1], // T-shirts: room2 → temp -1
        [3, -2], // Tumblers: room3 → temp -2
        [4, -3], // Artwork: room4 → temp -3
        [5, -4], // Sublimation: room5 → temp -4
        [6, -5]  // Window wraps: room6 → temp -5
    ];

    foreach ($tempMappings as $mapping) {
        // Update room_settings
        Database::execute("UPDATE room_settings SET room_number = ? WHERE room_number = ?", [$mapping[1], $mapping[0]]);

        // Update room_category_assignments
        Database::execute("UPDATE room_category_assignments SET room_number = ? WHERE room_number = ?", [$mapping[1], $mapping[0]]);

        // Update room_config if it exists
        $tableCheck = Database::queryAll("SHOW TABLES LIKE 'room_config'");
        if (count($tableCheck) > 0) {
            Database::execute("UPDATE room_config SET room_number = ? WHERE room_number = ?", [$mapping[1], $mapping[0]]);
        }
    }

    // Now update from temporary negative numbers to final positive numbers
    echo "Updating to final room numbers...\n";
    $finalMappings = [
        [-1, 1], // temp -1 → room1
        [-2, 2], // temp -2 → room2
        [-3, 3], // temp -3 → room3
        [-4, 4], // temp -4 → room4
        [-5, 5]  // temp -5 → room5
    ];

    foreach ($finalMappings as $mapping) {
        // Update room_settings
        Database::execute("UPDATE room_settings SET room_number = ? WHERE room_number = ?", [$mapping[1], $mapping[0]]);

        // Update room_category_assignments
        Database::execute("UPDATE room_category_assignments SET room_number = ? WHERE room_number = ?", [$mapping[1], $mapping[0]]);

        // Update room_config if it exists
        $tableCheck = Database::queryAll("SHOW TABLES LIKE 'room_config'");
        if (count($tableCheck) > 0) {
            Database::execute("UPDATE room_config SET room_number = ? WHERE room_number = ?", [$mapping[1], $mapping[0]]);
        }
    }

    // Update room_maps table (room_type column)
    echo "Updating room_maps table...\n";
    $roomTypeUpdates = [
        ['room2', 'room1'],
        ['room3', 'room2'],
        ['room4', 'room3'],
        ['room5', 'room4'],
        ['room6', 'room5']
    ];

    foreach ($roomTypeUpdates as $update) {
        Database::execute("UPDATE room_maps SET room_type = ? WHERE room_type = ?", [$update[1], $update[0]]);
    }

    // Update backgrounds table (room_type column)
    echo "Updating backgrounds table...\n";
    foreach ($roomTypeUpdates as $update) {
        Database::execute("UPDATE backgrounds SET room_type = ? WHERE room_type = ?", [$update[1], $update[0]]);
    }

    // Room config was already updated above

    // Move the Main Room to room 6 (next available number)
    echo "Moving Main Room to room 6...\n";
    Database::execute("UPDATE room_settings SET room_number = ? WHERE room_number = ?", [6, 999]);
    Database::execute("UPDATE room_category_assignments SET room_number = ? WHERE room_number = ?", [6, 999]);
    $tableCheck = Database::queryAll("SHOW TABLES LIKE 'room_config'");
    if (count($tableCheck) > 0) {
        Database::execute("UPDATE room_config SET room_number = ? WHERE room_number = ?", [6, 999]);
    }

    // Commit the transaction
    Database::commit();

    echo "Migration completed successfully!\n";
    echo json_encode([
        'success' => true,
        'message' => 'Room numbering migration from 2-6 to 1-5 completed successfully',
        'mappings' => $mappings
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    try {
        Database::rollBack();
    } catch (Throwable $t) { /* ignore */
    }

    echo "Migration failed: " . $e->getMessage() . "\n";
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage()
    ]);
}
?> 