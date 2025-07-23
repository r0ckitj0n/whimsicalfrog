-- Correct item category mapping: align existing items to room categories
START TRANSACTION;

-- Hats should map to Artwork
UPDATE items SET category = 'Artwork' WHERE category = 'Hats';
-- Fluid Art should map to Sublimation
UPDATE items SET category = 'Sublimation' WHERE category = 'Fluid Art';
-- Decor should map to Window Wraps
UPDATE items SET category = 'Window Wraps' WHERE category = 'Decor';

COMMIT;
