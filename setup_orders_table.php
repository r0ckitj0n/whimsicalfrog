<?php
// Orders Table Setup Script for Whimsical Frog
// This script creates the necessary tables for order management
// IMPORTANT: Delete this file after successful execution for security!

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the database configuration
require_once 'api/config.php';

// Security token to prevent accidental execution
$securityToken = md5('whimsicalfrog_setup_' . date('Ymd'));

// Check if form is submitted with correct token
$isConfirmed = isset($_POST['confirm']) && isset($_POST['token']) && $_POST['token'] === $securityToken;
$isExecuted = false;
$errorMessage = '';
$successMessage = '';

// Execute SQL when confirmed
if ($isConfirmed) {
    try {
        // Create database connection using config
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // SQL commands to create tables
        $sql = "
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
        ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `order_id` VARCHAR(16) GENERATED ALWAYS AS (id) STORED;
        ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `order_date` DATETIME GENERATED ALWAYS AS (date) STORED;
        ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `payment_status` VARCHAR(32) GENERATED ALWAYS AS (paymentStatus) STORED;

        -- Sample order for testing (optional)
        INSERT INTO `orders` (`id`, `userId`, `date`, `status`, `total`, `shippingAddress`, `billingAddress`, `paymentMethod`, `paymentStatus`)
        VALUES ('ORD001', 'U001', NOW(), 'Processing', 124.99, 
          '{\"name\":\"John Doe\",\"line1\":\"123 Main St\",\"city\":\"Atlanta\",\"state\":\"GA\",\"zip\":\"30303\",\"country\":\"USA\"}',
          '{\"name\":\"John Doe\",\"line1\":\"123 Main St\",\"city\":\"Atlanta\",\"state\":\"GA\",\"zip\":\"30303\",\"country\":\"USA\"}',
          'Credit Card', 'Received');

        INSERT INTO `order_items` (`id`, `orderId`, `productId`, `quantity`, `price`)
        VALUES 
          ('OI001', 'ORD001', 'P001', 2, 24.99),
          ('OI002', 'ORD001', 'P003', 3, 24.99);
        ";
        
        // Execute multiple SQL statements
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        $isExecuted = true;
        $successMessage = "Orders tables created successfully! Please delete this file now.";
        
    } catch (PDOException $e) {
        $errorMessage = "Database Error: " . $e->getMessage();
    } catch (Exception $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whimsical Frog - Orders Table Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Merienda:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .font-merienda {
            font-family: 'Merienda', cursive;
        }
        body {
            background-color: #f3f4f6;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col items-center justify-center p-4">
    <div class="max-w-lg w-full bg-white rounded-lg shadow-md p-6">
        <h1 class="text-3xl font-merienda text-center text-[#556B2F] mb-6">Orders Table Setup</h1>
        
        <?php if ($isExecuted): ?>
            <?php if (!empty($successMessage)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Success!</p>
                    <p><?php echo $successMessage; ?></p>
                </div>
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Important Security Notice</p>
                    <p>For security reasons, please delete this file immediately:</p>
                    <code class="block bg-gray-100 p-2 mt-2 rounded"><?php echo __FILE__; ?></code>
                </div>
                <div class="text-center">
                    <a href="/?page=admin" class="inline-block bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
                        Go to Admin Dashboard
                    </a>
                </div>
            <?php else: ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Error!</p>
                    <p><?php echo $errorMessage; ?></p>
                </div>
                <form method="post" class="text-center">
                    <input type="hidden" name="token" value="<?php echo $securityToken; ?>">
                    <button type="submit" name="confirm" value="1" class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
                        Try Again
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
                <p class="font-bold">Information</p>
                <p>This script will create the necessary tables for order management in your database.</p>
                <p class="mt-2"><strong>Environment:</strong> <?php echo $isLocalhost ? 'LOCAL' : 'PRODUCTION'; ?></p>
                <p><strong>Database:</strong> <?php echo $db; ?> on <?php echo $host; ?></p>
            </div>
            
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
                <p class="font-bold">Warning!</p>
                <p>This script will create the following tables:</p>
                <ul class="list-disc list-inside ml-4 mt-2">
                    <li><code>orders</code> - For storing order information</li>
                    <li><code>order_items</code> - For storing order line items</li>
                </ul>
                <p class="mt-2">If these tables already exist, they will not be modified.</p>
            </div>
            
            <form method="post" class="text-center">
                <input type="hidden" name="token" value="<?php echo $securityToken; ?>">
                <button type="submit" name="confirm" value="1" class="bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded-md transition-colors">
                    Create Orders Tables
                </button>
            </form>
        <?php endif; ?>
    </div>
    
    <div class="mt-6 text-center text-gray-500 text-sm">
        <p>Whimsical Frog E-commerce - <?php echo date('Y'); ?></p>
        <p class="text-xs mt-1">For security, delete this file after use.</p>
    </div>
</body>
</html>
