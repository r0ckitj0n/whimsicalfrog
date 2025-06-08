-- Create Orders Table SQL for Whimsical Frog E-commerce Site
-- This script creates the necessary tables for order management

-- Create orders table
CREATE TABLE IF NOT EXISTS `orders` (
  `id` VARCHAR(16) NOT NULL PRIMARY KEY,
  `userId` VARCHAR(16) NOT NULL,
  `date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` VARCHAR(32) NOT NULL DEFAULT 'Pending',
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shippingAddress` TEXT,
  `billingAddress` TEXT,
  `trackingNumber` VARCHAR(64),
  `paymentMethod` VARCHAR(32) DEFAULT 'Credit Card',
  `paymentStatus` VARCHAR(32) DEFAULT 'Pending',
  `notes` TEXT,
  FOREIGN KEY (`userId`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create order_items table
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` VARCHAR(16) NOT NULL PRIMARY KEY,
  `orderId` VARCHAR(16) NOT NULL,
  `productId` VARCHAR(16) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `price` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`orderId`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`productId`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create indexes for better performance
CREATE INDEX idx_orders_userId ON orders(userId);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_date ON orders(date);
CREATE INDEX idx_order_items_orderId ON order_items(orderId);
CREATE INDEX idx_order_items_productId ON order_items(productId);

-- Add alias columns for compatibility
ALTER TABLE `orders` ADD COLUMN `order_id` VARCHAR(16) GENERATED ALWAYS AS (id) STORED;
ALTER TABLE `orders` ADD COLUMN `order_date` DATETIME GENERATED ALWAYS AS (date) STORED;
ALTER TABLE `orders` ADD COLUMN `payment_status` VARCHAR(32) GENERATED ALWAYS AS (paymentStatus) STORED;

-- Sample order for testing (optional, comment out in production)
INSERT INTO `orders` (`id`, `userId`, `date`, `status`, `total`, `shippingAddress`, `billingAddress`, `paymentMethod`, `paymentStatus`)
VALUES ('ORD001', 'U001', NOW(), 'Processing', 124.99, 
  '{"name":"John Doe","line1":"123 Main St","city":"Atlanta","state":"GA","zip":"30303","country":"USA"}',
  '{"name":"John Doe","line1":"123 Main St","city":"Atlanta","state":"GA","zip":"30303","country":"USA"}',
  'Credit Card', 'Received');

INSERT INTO `order_items` (`id`, `orderId`, `productId`, `quantity`, `price`)
VALUES 
  ('OI001', 'ORD001', 'P001', 2, 24.99),
  ('OI002', 'ORD001', 'P003', 3, 24.99);
