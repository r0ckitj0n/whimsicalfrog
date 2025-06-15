-- WhimsicalFrog Database Migration Script
-- This script updates the database structure to support the payment system
-- It creates or modifies the orders table and fixes collation issues

-- Create orders table if it doesn't exist
CREATE TABLE IF NOT EXISTS orders (
    id VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL,
    userId VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    paymentMethod VARCHAR(50) NOT NULL DEFAULT 'Credit Card',
    checkNumber VARCHAR(64) NULL DEFAULT NULL,
    shippingAddress TEXT NULL DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    trackingNumber VARCHAR(100) NULL DEFAULT NULL,
    paymentStatus VARCHAR(20) NOT NULL DEFAULT 'Pending',
    paymentDate DATE NULL DEFAULT NULL,
    paymentNotes TEXT NULL DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure columns exist with proper collation (these will be ignored if columns already exist)
ALTER TABLE orders 
    MODIFY COLUMN id VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY COLUMN userId VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL,
    MODIFY COLUMN paymentMethod VARCHAR(50) NOT NULL DEFAULT 'Credit Card',
    MODIFY COLUMN paymentStatus VARCHAR(20) NOT NULL DEFAULT 'Pending';

-- Add columns if they don't exist (these will be ignored if columns already exist)
ALTER TABLE orders 
    ADD COLUMN IF NOT EXISTS checkNumber VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS paymentStatus VARCHAR(20) NOT NULL DEFAULT 'Pending',
    ADD COLUMN IF NOT EXISTS paymentDate DATE NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS paymentNotes TEXT NULL DEFAULT NULL;

-- Add indexes directly (will fail silently if they already exist)
-- We'll create them with unique names to avoid conflicts
ALTER TABLE orders 
    ADD INDEX IF NOT EXISTS idx_orders_userId (userId),
    ADD INDEX IF NOT EXISTS idx_orders_payment_status (paymentStatus),
    ADD INDEX IF NOT EXISTS idx_orders_payment_method (paymentMethod);

-- Try to add foreign key constraint if it doesn't exist
-- This is a simple approach that will fail silently if constraint already exists
ALTER TABLE orders 
    ADD CONSTRAINT IF NOT EXISTS fk_orders_users
    FOREIGN KEY (userId) REFERENCES users(id)
    ON DELETE CASCADE;

-- Insert sample test data for payment system testing
-- Using INSERT IGNORE to avoid errors if records already exist
INSERT IGNORE INTO orders 
(id, userId, total, paymentMethod, checkNumber, shippingAddress, status, date, paymentStatus, paymentDate, paymentNotes)
VALUES 
('TEST001', 'U001', 45.99, 'Check', '1234', '123 Test St, Testville, TS 12345', 'Completed', NOW(), 'Received', CURDATE(), 'Check cleared on 6/9/25');

INSERT IGNORE INTO orders 
(id, userId, total, paymentMethod, checkNumber, shippingAddress, status, date, paymentStatus, paymentDate, paymentNotes)
VALUES 
('TEST002', 'U001', 29.99, 'Cash', NULL, '123 Test St, Testville, TS 12345', 'Completed', NOW(), 'Received', CURDATE(), 'Cash payment received in store');

INSERT IGNORE INTO orders 
(id, userId, total, paymentMethod, checkNumber, shippingAddress, status, date, paymentStatus, paymentNotes)
VALUES 
('TEST003', 'U001', 67.50, 'Check', '5678', '123 Test St, Testville, TS 12345', 'Processing', NOW(), 'Pending', 'Check #5678 - customer promises to deliver tomorrow');
