-- Adjust product room settings and assignments to direct mapping (1-5), remove Main Room
START TRANSACTION;

-- 1) Remove Main Room (room_number=1)
DELETE FROM room_settings WHERE room_number = 1;

-- 2) Shift product rooms down by 1: 2->1,3->2,4->3,5->4
UPDATE room_settings SET room_number = room_number - 1 WHERE room_number BETWEEN 2 AND 5;

-- 3) Insert Window Wraps as room 5 if missing
INSERT INTO room_settings (room_number, background_display_type, room_name, door_label, description, show_search_bar, display_order, is_active)
SELECT 5, background_display_type, name, name, description, show_search_bar, 5, 1
FROM categories
WHERE name = 'Window Wraps'
  AND NOT EXISTS (SELECT 1 FROM room_settings WHERE room_number = 5);

-- 4) Update category assignments: shift room_number for assignments
UPDATE room_category_assignments SET room_number = room_number - 1 WHERE room_number BETWEEN 2 AND 5;
-- 5) Insert Window Wraps assignment
INSERT INTO room_category_assignments (room_number, category_id, is_primary)
SELECT 5, id, 1 FROM categories WHERE name = 'Window Wraps'
  AND NOT EXISTS (SELECT 1 FROM room_category_assignments WHERE room_number = 5 AND is_primary = 1);

COMMIT;

-- Verify
SELECT 'settings' AS table, GROUP_CONCAT(room_number ORDER BY room_number) AS rooms FROM room_settings;
SELECT 'assignments' AS table, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_category_assignments;
