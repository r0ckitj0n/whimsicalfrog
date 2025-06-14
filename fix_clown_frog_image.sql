-- Fix Clown Frog Image Path
-- This script ensures the clown frog t-shirt (TS002) has the correct image path

-- Update inventory table
UPDATE inventory SET imageUrl = 'images/products/TS002A.webp' WHERE productId = 'TS002';

-- Update products table  
UPDATE products SET image = 'images/products/TS002A.webp' WHERE id = 'TS002';

-- Verify the updates
SELECT 'Inventory Table:' as table_name, productId, imageUrl FROM inventory WHERE productId = 'TS002'
UNION ALL
SELECT 'Products Table:' as table_name, id as productId, image as imageUrl FROM products WHERE id = 'TS002'; 