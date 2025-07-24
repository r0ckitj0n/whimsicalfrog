-- db/migrate_swap_tshirts_tumblers.sql
-- Swap room numbers for T-Shirts and Tumblers

START TRANSACTION;

-- store current room numbers
SET @rt = (SELECT room_number FROM room_settings WHERE door_label = 'T-Shirts');
SET @rb = (SELECT room_number FROM room_settings WHERE door_label = 'Tumblers');

-- swap in room_settings
UPDATE room_settings
SET room_number = CASE
    WHEN door_label = 'T-Shirts' THEN @rb
    WHEN door_label = 'Tumblers' THEN @rt
    ELSE room_number
END;

-- swap in room_category_assignments
UPDATE room_category_assignments
SET room_number = CASE
    WHEN room_number = @rt THEN @rb
    WHEN room_number = @rb THEN @rt
    ELSE room_number
END;

COMMIT;
