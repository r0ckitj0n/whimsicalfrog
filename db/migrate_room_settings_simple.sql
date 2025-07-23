-- Simple remap of room_settings to avoid collisions

-- 1) Move Main Room out of the way (to 6)
UPDATE room_settings SET room_number = 6 WHERE room_number = 1;

-- 2) Shift product rooms down by 1 (2->1,3->2,4->3,5->4,6->5)
UPDATE room_settings
SET room_number = room_number - 1
WHERE room_number BETWEEN 2 AND 6;

-- 3) Insert Window Wraps as room_number 5 if missing
INSERT INTO room_settings (room_number, room_name, description)
SELECT 5, name, description
FROM categories
WHERE name = 'Window Wraps'
  AND NOT EXISTS (
    SELECT 1 FROM room_settings WHERE room_number = 5
);

-- 4) Verify final room settings
SELECT DISTINCT room_number, room_name FROM room_settings ORDER BY room_number;
