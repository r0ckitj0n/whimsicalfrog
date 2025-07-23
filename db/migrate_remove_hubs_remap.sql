-- Remove room_number from hub pages and remap product rooms to 1–5 cleanly
START TRANSACTION;

-- 1) Null out hub pages room_number (Landing Page 0, Main Room 1)
UPDATE room_settings
SET room_number = NULL
WHERE room_number IN (0,1);

-- 2) Temp shift product rooms (2–5) up by +10
UPDATE room_settings
SET room_number = room_number + 10
WHERE room_number BETWEEN 2 AND 5;

-- 3) Map temp values back down to 1–4
UPDATE room_settings SET room_number = 1 WHERE room_number = 12; -- T-Shirts
UPDATE room_settings SET room_number = 2 WHERE room_number = 13; -- Tumblers
UPDATE room_settings SET room_number = 3 WHERE room_number = 14; -- Sublimation
UPDATE room_settings SET room_number = 4 WHERE room_number = 15; -- Artwork

-- 4) Ensure Window Wraps exists as room 5
INSERT INTO room_settings (room_number, room_name, description)
SELECT 5, name, description FROM categories WHERE name = 'Window Wraps'
  AND NOT EXISTS (SELECT 1 FROM room_settings WHERE room_number = 5);

COMMIT;

-- Verify final room_settings
SELECT DISTINCT room_number, room_name FROM room_settings ORDER BY room_number;
