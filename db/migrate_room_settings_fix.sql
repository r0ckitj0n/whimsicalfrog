-- Simple remap for room_settings: move hub pages and product rooms to direct mapping

-- 1) Move Main Room to room_number 6 (free up 1 for T-Shirts)
UPDATE room_settings SET room_number = 6 WHERE room_number = 1;

-- 2) Shift product rooms down by 1: 2->1,3->2,4->3,5->4
UPDATE room_settings SET room_number = room_number - 1 WHERE room_number BETWEEN 2 AND 5;

-- 3) Insert Window Wraps as room 5
INSERT INTO room_settings (room_number, room_name, description)
SELECT 5, name, description FROM categories WHERE name = 'Window Wraps';

-- 4) Verify final state
SELECT DISTINCT room_number, room_name FROM room_settings ORDER BY room_number;
