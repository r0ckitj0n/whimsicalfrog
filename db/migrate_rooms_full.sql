-- Comprehensive migration for room_settings and room_category_assignments
-- Remove hub pages (Landing and Main Room)
DELETE FROM room_category_assignments WHERE room_number IN (0,1);
DELETE FROM room_settings WHERE room_number IN (0,1);

-- Remap product rooms
-- Map room_numbers 2-6 down by 1
UPDATE room_settings SET room_number = room_number - 1 WHERE room_number BETWEEN 2 AND 6;
UPDATE room_category_assignments SET room_number = room_number - 1 WHERE room_number BETWEEN 2 AND 6;

-- Insert Window Wraps as room 5
-- room_settings
INSERT INTO room_settings (room_number, room_name, description)
SELECT 5, name, description
FROM categories
WHERE name = 'Window Wraps'
ON DUPLICATE KEY UPDATE room_name = VALUES(room_name), description = VALUES(description);
-- room_category_assignments: assign as primary category
INSERT INTO room_category_assignments (room_number, category_id, is_primary)
SELECT 5, id, 1
FROM categories
WHERE name = 'Window Wraps'
ON DUPLICATE KEY UPDATE is_primary = 1;

-- Verify
SELECT 'settings' AS table_name, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_settings;
SELECT 'assignments' AS table_name, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_category_assignments;
