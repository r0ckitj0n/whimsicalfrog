<?php
// Initialize room settings database table
header('Content-Type: application/json');

// Include database configuration
require_once __DIR__ . '/config.php';

try {
    try {
        Database::getInstance();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw $e;
    }

    // Create room_settings table
    $createTable = "
    CREATE TABLE IF NOT EXISTS room_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        room_number VARCHAR(10) NOT NULL UNIQUE,
        room_name VARCHAR(100) NOT NULL,
        door_label VARCHAR(100) NOT NULL,
        description TEXT,
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_room_number (room_number),
        INDEX idx_display_order (display_order),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    Database::execute($createTable);
    // Ensure background_display_type column exists
    try {
        Database::execute("ALTER TABLE room_settings ADD COLUMN background_display_type ENUM('fullscreen','modal') NOT NULL DEFAULT 'fullscreen'");
    } catch (Exception $e) {
        // Column already exists, skip
    }
    // Ensure icons_white_background column exists (controls white panels behind room icons)
    try {
        Database::execute("ALTER TABLE room_settings ADD COLUMN icons_white_background TINYINT(1) NOT NULL DEFAULT 1");
    } catch (Exception $e) {
        // Column already exists, skip
    }

    // Set initial preference: turn off white icon backgrounds for room 1
    try {
        Database::execute("UPDATE room_settings SET icons_white_background = 0 WHERE room_number = '1'");
    } catch (Exception $e) {
        // Ignore failures silently
    }

    // Insert default room settings
    $defaultRooms = [
        [
            'room_number' => 'A',
            'room_name' => 'Landing Page',
            'door_label' => 'Welcome',
            'description' => 'Main landing page with featured items',
            'display_order' => 0
        ],
        [
            'room_number' => 'B',
            'room_name' => 'Main Room',
            'door_label' => 'Explore Rooms',
            'description' => 'Central hub with access to all product rooms',
            'display_order' => 1
        ],
        [
            'room_number' => '1',
            'room_name' => 'T-Shirts & Apparel',
            'door_label' => 'T-Shirts & Apparel',
            'description' => 'Custom t-shirts, hoodies, and apparel',
            'display_order' => 2
        ],
        [
            'room_number' => '2',
            'room_name' => 'Tumblers & Drinkware',
            'door_label' => 'Tumblers & Drinkware',
            'description' => 'Custom tumblers, mugs, and drinkware',
            'display_order' => 3
        ],
        [
            'room_number' => '3',
            'room_name' => 'Custom Artwork',
            'door_label' => 'Custom Artwork',
            'description' => 'Personalized artwork and designs',
            'display_order' => 4
        ],
        [
            'room_number' => '4',
            'room_name' => 'Sublimation Items',
            'door_label' => 'Sublimation Items',
            'description' => 'Sublimation printing on various items',
            'display_order' => 5
        ],
        [
            'room_number' => '5',
            'room_name' => 'Window Wraps',
            'door_label' => 'Window Wraps',
            'description' => 'Custom window wraps and vehicle graphics',
            'display_order' => 6
        ]
    ];

    // Check if data already exists
    $rowCount = Database::queryOne("SELECT COUNT(*) as c FROM room_settings");
    $count = $rowCount ? (int)$rowCount['c'] : 0;

    if ($count == 0) {
        foreach ($defaultRooms as $room) {
            Database::execute("\n            INSERT INTO room_settings (room_number, room_name, door_label, description, display_order) \n            VALUES (?, ?, ?, ?, ?)\n            ", [
                $room['room_number'],
                $room['room_name'],
                $room['door_label'],
                $room['description'],
                $room['display_order']
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Room settings table created and populated with default data',
            'rooms_created' => count($defaultRooms)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Room settings table already exists with data',
            'existing_rooms' => $count
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 