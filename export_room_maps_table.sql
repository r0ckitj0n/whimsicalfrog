-- Export of room_maps table structure and data
-- Safe to run on live server - will only affect room_maps table

-- Drop table if exists (be careful - this will remove existing data)
DROP TABLE IF EXISTS `room_maps`;

-- Create room_maps table structure
CREATE TABLE `room_maps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_type` varchar(50) NOT NULL,
  `map_name` varchar(100) NOT NULL,
  `coordinates` text NOT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_room_type` (`room_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert any existing data (currently empty, but structure is ready)
-- Data will be inserted here when you save maps through the Room Mapper

-- Ensure proper permissions and settings
ALTER TABLE `room_maps` AUTO_INCREMENT=1; 