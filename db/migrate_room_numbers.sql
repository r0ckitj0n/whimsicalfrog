-- Normalize room_number in room_settings and room_category_assignments
-- Map legacy DB room_numbers 2-6 to UI room_numbers 1-5

-- room_settings
UPDATE room_settings
SET room_number = room_number - 1
WHERE room_number BETWEEN 2 AND 6;

-- room_category_assignments
UPDATE room_category_assignments
SET room_number = room_number - 1
WHERE room_number BETWEEN 2 AND 6;

-- Remove any leftover mappings beyond 5
DELETE FROM room_settings WHERE room_number NOT BETWEEN 1 AND 5;
DELETE FROM room_category_assignments WHERE room_number NOT BETWEEN 1 AND 5;

-- Verify distinct room numbers
SELECT 'settings' AS table_name, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_settings;
SELECT 'assignments' AS table_name, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_category_assignments;
