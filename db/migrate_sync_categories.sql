-- Sync category IDs to match room numbers: 1=T-Shirts, 2=Tumblers, 3=Sublimation, 4=Artwork, 5=Window Wraps
SET FOREIGN_KEY_CHECKS = 0;
-- Temporarily move Artwork (current id=3) to temp id
UPDATE categories SET id = 100 WHERE id = 3;
-- Move Sublimation (current id=4) to id=3
UPDATE categories SET id = 3 WHERE id = 4;
-- Move temp Artwork id=100 to id=4
UPDATE categories SET id = 4 WHERE id = 100;
-- Ensure Window Wraps is id=5
UPDATE categories SET id = 5 WHERE name = 'Window Wraps';
-- Ensure T-Shirts and Tumblers have correct ids (in case)
UPDATE categories SET id = 1 WHERE name = 'T-Shirts';
UPDATE categories SET id = 2 WHERE name = 'Tumblers';
ALTER TABLE categories AUTO_INCREMENT = 6;
SET FOREIGN_KEY_CHECKS = 1;
