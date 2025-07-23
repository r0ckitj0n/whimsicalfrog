-- Reassign items to match room categories
START TRANSACTION;

-- Map old categories to room categories
UPDATE items SET category = 'T-Shirts' WHERE category = 'Hats';
UPDATE items SET category = 'Sublimation' WHERE category = 'Fluid Art';
UPDATE items SET category = 'Window Wraps' WHERE category = 'Decor';

COMMIT;

-- Verify
SELECT sku, category FROM items WHERE category IN ('T-Shirts','Sublimation','Window Wraps');
