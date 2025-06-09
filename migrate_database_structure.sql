-- WhimsicalFrog Database Migration Script
-- Purpose: Create or update orders table with payment functionality
-- Date: June 9, 2025

-- ========== ROLLBACK STATEMENTS ==========
-- WARNING: Execute these statements to undo the migration
-- 
-- DROP TABLE IF EXISTS orders;
-- 
-- Or to remove only the payment columns:
-- 
-- ALTER TABLE orders 
--   DROP COLUMN paymentMethod,
--   DROP COLUMN checkNumber,
--   DROP COLUMN paymentStatus,
--   DROP COLUMN paymentDate,
--   DROP COLUMN paymentNotes;
-- 
-- ==========================================

-- Create orders table if it doesn't exist
CREATE TABLE IF NOT EXISTS orders (
  id VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  userId VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  paymentMethod VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Credit Card',
  checkNumber VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  shippingAddress TEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  status VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  trackingNumber VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  paymentStatus VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  paymentDate DATE NULL DEFAULT NULL,
  paymentNotes TEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (id),
  INDEX idx_orders_userId (userId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing payment columns if table exists but columns don't
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS paymentMethod VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Credit Card',
  ADD COLUMN IF NOT EXISTS checkNumber VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS paymentStatus VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  ADD COLUMN IF NOT EXISTS paymentDate DATE NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS paymentNotes TEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

-- Fix collation issues for existing columns
ALTER TABLE orders 
  MODIFY COLUMN id VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  MODIFY COLUMN userId VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  MODIFY COLUMN paymentMethod VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Credit Card',
  MODIFY COLUMN checkNumber VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  MODIFY COLUMN shippingAddress TEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  MODIFY COLUMN status VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  MODIFY COLUMN trackingNumber VARCHAR(100) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  MODIFY COLUMN paymentStatus VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  MODIFY COLUMN paymentNotes TEXT COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

-- Add foreign key constraint if it doesn't exist
-- Note: This will only work if the users table exists with the correct structure
SET @fk_exists = (
  SELECT COUNT(1) FROM information_schema.TABLE_CONSTRAINTS 
  WHERE CONSTRAINT_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'orders' 
  AND CONSTRAINT_NAME = 'fk_orders_users' 
  AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql = IF(@fk_exists = 0, 
  'ALTER TABLE orders ADD CONSTRAINT fk_orders_users FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE', 
  'SELECT "Foreign key already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for payment status if it doesn't exist
SET @idx_exists = (
  SELECT COUNT(1) FROM information_schema.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'orders' 
  AND INDEX_NAME = 'idx_payment_status'
);

SET @sql = IF(@idx_exists = 0, 
  'ALTER TABLE orders ADD INDEX idx_payment_status (paymentStatus)', 
  'SELECT "Payment status index already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Insert sample data for testing (only if table is empty)
INSERT INTO orders (id, userId, total, paymentMethod, checkNumber, shippingAddress, status, date, trackingNumber, paymentStatus, paymentDate, paymentNotes)
SELECT * FROM (
  SELECT 
    'SAMPLE001' AS id,
    (SELECT id FROM users LIMIT 1) AS userId,
    45.99 AS total,
    'Check' AS paymentMethod,
    '1234' AS checkNumber,
    '123 Test Street, Test City, TS 12345' AS shippingAddress,
    'Processing' AS status,
    NOW() AS date,
    'TRK123456789' AS trackingNumber,
    'Received' AS paymentStatus,
    CURDATE() AS paymentDate,
    'Check cleared on payment date' AS paymentNotes
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM orders WHERE id = 'SAMPLE001');

INSERT INTO orders (id, userId, total, paymentMethod, checkNumber, shippingAddress, status, date, trackingNumber, paymentStatus, paymentDate, paymentNotes)
SELECT * FROM (
  SELECT 
    'SAMPLE002' AS id,
    (SELECT id FROM users LIMIT 1) AS userId,
    29.99 AS total,
    'Cash' AS paymentMethod,
    NULL AS checkNumber,
    '456 Example Ave, Sample Town, ST 67890' AS shippingAddress,
    'Pending' AS status,
    NOW() AS date,
    NULL AS trackingNumber,
    'Received' AS paymentStatus,
    CURDATE() AS paymentDate,
    'Cash payment received in store' AS paymentNotes
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM orders WHERE id = 'SAMPLE002');

INSERT INTO orders (id, userId, total, paymentMethod, checkNumber, shippingAddress, status, date, trackingNumber, paymentStatus, paymentDate, paymentNotes)
SELECT * FROM (
  SELECT 
    'SAMPLE003' AS id,
    (SELECT id FROM users LIMIT 1) AS userId,
    67.50 AS total,
    'Check' AS paymentMethod,
    '5678' AS checkNumber,
    '789 Demo Road, Test Village, TV 54321' AS shippingAddress,
    'Pending' AS status,
    NOW() AS date,
    NULL AS trackingNumber,
    'Pending' AS paymentStatus,
    NULL AS paymentDate,
    'Check #5678 - customer promises to deliver tomorrow' AS paymentNotes
) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM orders WHERE id = 'SAMPLE003');

-- Create migration helper PHP file to run this script
-- This will be created separately as migrate_database.php
