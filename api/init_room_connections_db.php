<?php
/**
 * Room Connections Database Initialization
 * Creates and seeds the room_connections table for navigation management
 */
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

try {
    Database::getInstance();

    // Create room_connections table
    $createTable = "CREATE TABLE IF NOT EXISTS room_connections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source_room VARCHAR(10) NOT NULL,
        target_room VARCHAR(10) NOT NULL,
        connection_type ENUM('one_way', 'bidirectional') DEFAULT 'bidirectional',
        link_created BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_connection (source_room, target_room),
        INDEX idx_source (source_room),
        INDEX idx_target (target_room)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    Database::execute($createTable);

    // Check if table is empty (needs seeding)
    $count = Database::queryOne("SELECT COUNT(*) as cnt FROM room_connections");

    if ($count && $count['cnt'] == 0) {
        // Seed default navigation structure
        $defaultConnections = [
            // Landing Page connects to Main Room
            ['A', '0', 'one_way'],

            // Main Room connects to all category rooms (bidirectional)
            ['0', '1', 'bidirectional'],  // T-Shirts & Apparel
            ['0', '2', 'bidirectional'],  // Tumblers
            ['0', '3', 'bidirectional'],  // Custom Artwork
            ['0', '4', 'bidirectional'],  // Sublimation Items
            ['0', '5', 'bidirectional'],  // Window Wraps

            // Main Room to Shop
            ['0', 'S', 'bidirectional'],

            // Category rooms to Shop (one-way for checkout flow)
            ['1', 'S', 'one_way'],
            ['2', 'S', 'one_way'],
            ['3', 'S', 'one_way'],
            ['4', 'S', 'one_way'],
            ['5', 'S', 'one_way'],
        ];

        foreach ($defaultConnections as $conn) {
            try {
                Database::execute(
                    "INSERT INTO room_connections (source_room, target_room, connection_type, link_created) VALUES (?, ?, ?, TRUE)",
                    [$conn[0], $conn[1], $conn[2]]
                );

                // For bidirectional, also create the reverse connection
                if ($conn[2] === 'bidirectional') {
                    Database::execute(
                        "INSERT IGNORE INTO room_connections (source_room, target_room, connection_type, link_created) VALUES (?, ?, 'bidirectional', TRUE)",
                        [$conn[1], $conn[0]]
                    );
                }
            } catch (Exception $e) {
                // Ignore duplicate key errors
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Room connections table created and seeded',
            'connections_created' => count($defaultConnections)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Room connections table already exists',
            'existing_connections' => $count['cnt'] ?? 0
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to initialize room connections: ' . $e->getMessage()
    ]);
}
