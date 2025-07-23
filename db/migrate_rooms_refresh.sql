-- Full refresh of product room settings and assignments
-- Removes hub entries and legacy offsets, then inserts clean mapping for rooms 1–5

START TRANSACTION;

-- Remove all existing product room_settings (and hubs)
DELETE FROM room_settings WHERE room_number IN (0,1,2,3,4,5,6);

-- Insert canonical product rooms 1–5
INSERT INTO room_settings (room_number, room_name, description)
VALUES
  (1, 'T-Shirts',       (SELECT description FROM categories WHERE name = 'T-Shirts')),
  (2, 'Tumblers',       (SELECT description FROM categories WHERE name = 'Tumblers')),
  (3, 'Sublimation',    (SELECT description FROM categories WHERE name = 'Sublimation')),
  (4, 'Artwork',        (SELECT description FROM categories WHERE name = 'Artwork')),
  (5, 'Window Wraps',   (SELECT description FROM categories WHERE name = 'Window Wraps'));

-- Remove all existing category assignments for rooms 1–5 (and hubs)
DELETE FROM room_category_assignments WHERE room_number IN (0,1,2,3,4,5,6);

-- Insert primary category assignments for rooms 1–5
INSERT INTO room_category_assignments (room_number, category_id, is_primary)
SELECT 1, id, 1 FROM categories WHERE name = 'T-Shirts';
INSERT INTO room_category_assignments (room_number, category_id, is_primary)
SELECT 2, id, 1 FROM categories WHERE name = 'Tumblers';
INSERT INTO room_category_assignments (room_number, category_id, is_primary)
SELECT 3, id, 1 FROM categories WHERE name = 'Sublimation';
INSERT INTO room_category_assignments (room_number, category_id, is_primary)
SELECT 4, id, 1 FROM categories WHERE name = 'Artwork';
INSERT INTO room_category_assignments (room_number, category_id, is_primary)
SELECT 5, id, 1 FROM categories WHERE name = 'Window Wraps';

COMMIT;

-- Verify final state
SELECT 'settings' AS table_name, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_settings;
SELECT 'assignments' AS table_name, GROUP_CONCAT(DISTINCT room_number ORDER BY room_number) AS rooms FROM room_category_assignments;
