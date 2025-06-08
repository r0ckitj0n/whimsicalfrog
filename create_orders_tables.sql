-- Create Orders Table
CREATE TABLE IF NOT EXISTS orders (
    id VARCHAR(16) PRIMARY KEY,
    userId VARCHAR(16) NOT NULL,
    total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    paymentMethod VARCHAR(50) NOT NULL DEFAULT 'Credit Card',
    shippingAddress TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    trackingNumber VARCHAR(100),
    paymentStatus VARCHAR(20) NOT NULL DEFAULT 'Pending',
    INDEX (userId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Order Items Table
CREATE TABLE IF NOT EXISTS order_items (
    id VARCHAR(16) PRIMARY KEY,
    orderId VARCHAR(16) NOT NULL,
    productId VARCHAR(16) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    INDEX (orderId),
    INDEX (productId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create Sample Order Data (Optional - for testing)
INSERT INTO orders (id, userId, total, paymentMethod, shippingAddress, status, date, paymentStatus)
VALUES 
('O12345', 'U001', 59.97, 'Credit Card', '{"name":"Admin User","street":"123 Main St","city":"Dawsonville","state":"GA","zip":"30534"}', 'Processing', NOW(), 'Received'),
('O23456', 'U002', 24.99, 'PayPal', '{"name":"Test Customer","street":"456 Oak Ave","city":"Atlanta","state":"GA","zip":"30303"}', 'Shipped', DATE_SUB(NOW(), INTERVAL 2 DAY), 'Received'),
('O34567', 'U002', 149.95, 'Credit Card', '{"name":"Test Customer","street":"456 Oak Ave","city":"Atlanta","state":"GA","zip":"30303"}', 'Delivered', DATE_SUB(NOW(), INTERVAL 7 DAY), 'Received');

-- Insert Sample Order Items
INSERT INTO order_items (id, orderId, productId, quantity, price)
VALUES 
('OI12345', 'O12345', 'P001', 2, 19.99),
('OI23456', 'O12345', 'P002', 1, 19.99),
('OI34567', 'O23456', 'P005', 1, 24.99),
('OI45678', 'O34567', 'P004', 3, 49.99);
