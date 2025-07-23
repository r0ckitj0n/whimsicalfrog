-- Definitive refresh of product rooms 1–5 in room_settings and room_category_assignments
START TRANSACTION;

-- 1) Remove all existing room_category_assignments and room_settings
DELETE FROM room_category_assignments;
DELETE FROM room_settings;

-- 2) Insert canonical product rooms into settings
INSERT INTO room_settings (room_number, background_display_type, room_name, door_label, description, show_search_bar, display_order, is_active)
SELECT 1, 'fullscreen', 'T-Shirts', 'T-Shirts', description, 1, 1, 1 FROM categories WHERE name = 'T-Shirts'
UNION ALL
SELECT 2, 'fullscreen', 'Tumblers', 'Tumblers', description, 1, 2, 1 FROM categories WHERE name = 'Tumblers'
UNION ALL
SELECT 3, 'fullscreen', 'Sublimation', 'Sublimation', description, 1, 3, 1 FROM categories WHERE name = 'Sublimation'
UNION ALL
SELECT 4, 'fullscreen', 'Artwork', 'Artwork', description, 1, 4, 1 FROM categories WHERE name = 'Artwork'
UNION ALL
SELECT 5, 'fullscreen', 'Window Wraps', 'Window Wraps', description, 1, 5, 1 FROM categories WHERE name = 'Window Wraps';

-- 3) Insert primary category assignments for rooms 1–5
INSERT INTO room_category_assignments (room_number, category_id, is_primary)
SELECT 1, id, 1 FROM categories WHERE name = 'T-Shirts'
UNION ALL
SELECT 2, id, 1 FROM categories WHERE name = 'Tumblers'
UNION ALL
SELECT 3, id, 1 FROM categories WHERE name = 'Sublimation'
UNION ALL
SELECT 4, id, 1 FROM categories WHERE name = 'Artwork'
UNION ALL
SELECT 5, id, 1 FROM categories WHERE name = 'Window Wraps';

COMMIT;

-- Verify
SELECT 'settings' AS table_name, GROUP_CONCAT(CONCAT(room_number,':',room_name) ORDER BY room_number) AS rooms FROM room_settings;
SELECT 'assignments' AS table_name, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_category_assignments;
