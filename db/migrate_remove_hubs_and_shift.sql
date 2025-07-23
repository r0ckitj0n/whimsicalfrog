-- Remove hub pages and remap product rooms to direct 1-5
START TRANSACTION;

-- 1) Remove hub page assignments and settings
DELETE FROM room_category_assignments WHERE room_number IN (0,1);
DELETE FROM room_settings WHERE room_number IN (0,1);

-- 2) Shift product rooms down by 1: 2->1,3->2,4->3,5->4,6->5
UPDATE room_settings SET room_number = room_number - 1 WHERE room_number >= 2;
UPDATE room_category_assignments SET room_number = room_number - 1 WHERE room_number >= 2;

-- 3) Ensure Window Wraps as room 5 in settings
INSERT INTO room_settings (room_number, room_name, description, background_display_type, door_label, show_search_bar, display_order, is_active)
SELECT 5, name, description, background_display_type, door_label, show_search_bar, 5, 1
FROM categories
LEFT JOIN (SELECT 1) AS dummy ON TRUE
WHERE name = 'Window Wraps'
  AND NOT EXISTS (SELECT 1 FROM room_settings WHERE room_number = 5);

-- 4) Ensure assignment for Window Wraps as primary
INSERT IGNORE INTO room_category_assignments (room_number, category_id, is_primary)
SELECT 5, id, 1 FROM categories WHERE name = 'Window Wraps';

COMMIT;

-- Verify
SELECT 'settings' AS t, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_settings;
SELECT 'assignments' AS t, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_category_assignments;
