-- Phase 2 two-step remap for room_settings to avoid collision
-- 1) Temp shift product rooms (2-5) up by +10
UPDATE room_settings
SET room_number = room_number + 10
WHERE room_number BETWEEN 2 AND 5;

-- 2) Map temp values back to UI room numbers (1-4)
UPDATE room_settings SET room_number = 1 WHERE room_number = 12; -- T-Shirts & Apparel
UPDATE room_settings SET room_number = 2 WHERE room_number = 13; -- Tumblers & Drinkware
UPDATE room_settings SET room_number = 3 WHERE room_number = 14; -- Custom Artwork
UPDATE room_settings SET room_number = 4 WHERE room_number = 15; -- Sublimation Items

-- 3) Insert Window Wraps as room 5
INSERT INTO room_settings (room_number, room_name, description)
SELECT 5, name, description FROM categories WHERE name = 'Window Wraps';

-- Verify final state
SELECT DISTINCT room_number, room_name FROM room_settings ORDER BY room_number;
