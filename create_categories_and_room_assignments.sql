-- Drop the old room_categories table to start fresh
DROP TABLE IF EXISTS room_categories;

-- Create categories table with the actual product categories
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert the actual product categories from the site
INSERT INTO categories (name, description, display_order, is_active) VALUES
('T-Shirts', 'Custom printed t-shirts and apparel', 1, 1),
('Tumblers', 'Custom tumblers and drinkware', 2, 1),
('Artwork', 'Custom artwork and prints', 3, 1),
('Sublimation', 'Sublimation printed products', 4, 1),
('Window Wraps', 'Custom window wraps and decals', 5, 1);

-- Create room_category_assignments table for numbered rooms
CREATE TABLE IF NOT EXISTS room_category_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number INT NOT NULL,
    room_name VARCHAR(100) NOT NULL,
    category_id INT NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_room_number (room_number),
    INDEX idx_category_id (category_id),
    INDEX idx_primary (is_primary),
    UNIQUE KEY unique_room_category (room_number, category_id),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default room-category assignments with numbered rooms
-- Room 0: Landing Page (featured categories)
INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) 
SELECT 0, 'Landing Page', id, 0, display_order FROM categories WHERE name IN ('T-Shirts', 'Tumblers', 'Artwork');

-- Room 1: Main Room (all categories for browsing)
INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) 
SELECT 1, 'Main Room', id, 0, display_order FROM categories;

-- Room 2: T-Shirts Room
INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) 
SELECT 2, 'T-Shirts Room', id, 1, 1 FROM categories WHERE name = 'T-Shirts';

-- Room 3: Tumblers Room  
INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) 
SELECT 3, 'Tumblers Room', id, 1, 1 FROM categories WHERE name = 'Tumblers';

-- Room 4: Artwork Room
INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) 
SELECT 4, 'Artwork Room', id, 1, 1 FROM categories WHERE name = 'Artwork';

-- Room 5: Sublimation Room
INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) 
SELECT 5, 'Sublimation Room', id, 1, 1 FROM categories WHERE name = 'Sublimation';

-- Room 6: Window Wraps Room
INSERT INTO room_category_assignments (room_number, room_name, category_id, is_primary, display_order) 
SELECT 6, 'Window Wraps Room', id, 1, 1 FROM categories WHERE name = 'Window Wraps'; 