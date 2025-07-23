-- Clean migration: remove hubs, shift product rooms, ensure window wraps
START TRANSACTION;

-- 1) Remove hub pages from settings and assignments
DELETE FROM room_category_assignments WHERE room_number IN (0,1);
DELETE FROM room_settings WHERE room_number IN (0,1);

-- 2) Shift all remaining rooms down by 1: 2->1,3->2,4->3,5->4,6->5
UPDATE room_settings SET room_number = room_number - 1 WHERE room_number >= 2;
UPDATE room_category_assignments SET room_number = room_number - 1 WHERE room_number >= 2;

-- 3) Remove any out-of-range rooms
DELETE FROM room_settings WHERE room_number NOT BETWEEN 1 AND 5;
DELETE FROM room_category_assignments WHERE room_number NOT BETWEEN 1 AND 5;

-- 4) Insert Window Wraps as room 5 if missing in settings
INSERT INTO room_settings (room_number, background_display_type, room_name, door_label, description, show_search_bar, display_order, is_active)
SELECT 5, 'fullscreen', name, name, description, 1, 5, 1
FROM categories
WHERE name = 'Window Wraps'
  AND NOT EXISTS (SELECT 1 FROM room_settings WHERE room_number = 5);

-- 5) Insert Window Wraps assignment if missing
INSERT IGNORE INTO room_category_assignments (room_number, category_id, is_primary)
SELECT 5, id, 1 FROM categories WHERE name = 'Window Wraps';

COMMIT;
