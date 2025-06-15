-- Create room_maps table for live database
-- Run this in phpMyAdmin or your database management tool

CREATE TABLE IF NOT EXISTS room_maps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_type VARCHAR(50) NOT NULL,
    map_name VARCHAR(100) NOT NULL,
    coordinates TEXT,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_room_type (room_type),
    INDEX idx_active (is_active),
    INDEX idx_room_active (room_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Insert a test record to verify the table works
-- INSERT INTO room_maps (room_type, map_name, coordinates, is_active) 
-- VALUES ('room_tshirts', 'Test Map', '[]', FALSE); 