-- Create backgrounds table for managing website backgrounds
CREATE TABLE IF NOT EXISTS backgrounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_type VARCHAR(50) NOT NULL,
    background_name VARCHAR(100) NOT NULL,
    image_filename VARCHAR(255) NOT NULL,
    webp_filename VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_room_type (room_type),
    INDEX idx_active (is_active),
    UNIQUE KEY unique_active_per_room (room_type, is_active, background_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Original backgrounds based on current system
INSERT INTO backgrounds (room_type, background_name, image_filename, webp_filename, is_active) VALUES
('landing', 'Original', 'home_background.png', 'home_background.webp', 1),
('room_main', 'Original', 'room_main.png', 'room_main.webp', 1),
('room_artwork', 'Original', 'room_artwork.png', 'room_artwork.webp', 1),
('room_tshirts', 'Original', 'room_tshirts.png', 'room_tshirts.webp', 1),
('room_tumblers', 'Original', 'room_tumblers.png', 'room_tumblers.webp', 1),
('room_sublimation', 'Original', 'room_sublimation.png', 'room_sublimation.webp', 1),
('room_windowwraps', 'Original', 'room_windowwraps.png', 'room_windowwraps.webp', 1); 