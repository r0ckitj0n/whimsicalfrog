-- Migration script to normalize room_maps.room_type values
-- Maps DB room types room2–room6 to direct UI room types room1–room5

UPDATE room_maps
SET room_type = CASE room_type
    WHEN 'room2' THEN 'room1'
    WHEN 'room3' THEN 'room2'
    WHEN 'room4' THEN 'room3'
    WHEN 'room5' THEN 'room4'
    WHEN 'room6' THEN 'room5'
    ELSE room_type
END;

-- Ensure no leftover room6 entries
DELETE FROM room_maps WHERE room_type = 'room6';

-- Verify
SELECT DISTINCT room_type FROM room_maps;
