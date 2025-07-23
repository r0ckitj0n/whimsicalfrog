-- Phase 2 two-step remap for room_settings and room_category_assignments to avoid collisions
START TRANSACTION;

-- 1) Temp shift product rooms (2-6) up by +10
UPDATE room_settings
SET room_number = room_number + 10
WHERE room_number BETWEEN 2 AND 6;
UPDATE room_category_assignments
SET room_number = room_number + 10
WHERE room_number BETWEEN 2 AND 6;

-- 2) Map temp values back to UI rooms 1-5
UPDATE room_settings SET room_number = 1 WHERE room_number = 12;
UPDATE room_settings SET room_number = 2 WHERE room_number = 13;
UPDATE room_settings SET room_number = 3 WHERE room_number = 14;
UPDATE room_settings SET room_number = 4 WHERE room_number = 15;
UPDATE room_settings SET room_number = 5 WHERE room_number = 16;

UPDATE room_category_assignments SET room_number = 1 WHERE room_number = 12;
UPDATE room_category_assignments SET room_number = 2 WHERE room_number = 13;
UPDATE room_category_assignments SET room_number = 3 WHERE room_number = 14;
UPDATE room_category_assignments SET room_number = 4 WHERE room_number = 15;
UPDATE room_category_assignments SET room_number = 5 WHERE room_number = 16;

-- 3) Ensure Window Wraps assignment exists for room 5
INSERT IGNORE INTO room_category_assignments (room_number, category_id, is_primary)
SELECT 5, id, 1
FROM categories
WHERE name = 'Window Wraps';

COMMIT;

-- Verify
SELECT 'settings' AS table_name, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_settings;
SELECT 'assignments' AS table_name, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_category_assignments;
