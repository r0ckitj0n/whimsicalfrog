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
$sqlErrors = [];

// Execute SQL when confirmed
if ($isConfirmed) {
    try {
        // Create database connection using config
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // SQL commands to create tables - each as a separate statement
        $sqlStatements = [
            // Create orders table
            "CREATE TABLE IF NOT EXISTS `orders` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            
            // Create order_items table
            "CREATE TABLE IF NOT EXISTS `order_items` (
              `id` VARCHAR(16) NOT NULL PRIMARY KEY,
              `orderId` VARCHAR(16) NOT NULL,
              `productId` VARCHAR(16) NOT NULL,
              `quantity` INT NOT NULL DEFAULT 1,
              `price` DECIMAL(10,2) NOT NULL,
              FOREIGN KEY (`orderId`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
              FOREIGN KEY (`productId`) REFERENCES `products`(`id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            
            // Create indexes for better performance
            "CREATE INDEX IF NOT EXISTS idx_orders_userId ON orders(userId)",
            "CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)",
            "CREATE INDEX IF NOT EXISTS idx_orders_date ON orders(date)",
            "CREATE INDEX IF NOT EXISTS idx_order_items_orderId ON order_items(orderId)",
            "CREATE INDEX IF NOT EXISTS idx_order_items_productId ON order_items(productId)",
            
            // Add alias columns for compatibility - these might fail on older MySQL versions, so we'll handle errors
            "ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `order_id` VARCHAR(16) GENERATED ALWAYS AS (id) STORED",
            "ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `order_date` DATETIME GENERATED ALWAYS AS (date) STORED",
            "ALTER TABLE `orders` ADD COLUMN IF NOT EXISTS `payment_status` VARCHAR(32) GENERATED ALWAYS AS (paymentStatus) STORED",
            
            // Sample order for testing (optional)
            "INSERT INTO `orders` (`id`, `userId`, `date`, `status`, `total`, `shippingAddress`, `billingAddress`, `paymentMethod`, `paymentStatus`)
            VALUES ('ORD001', 'U001', NOW(), 'Processing', 124.99, 
              '{\"name\":\"John Doe\",\"line1\":\"123 Main St\",\"city\":\"Atlanta\",\"state\":\"GA\",\"zip\":\"30303\",\"country\":\"USA\"}',
              '{\"name\":\"John Doe\",\"line1\":\"123 Main St\",\"city\":\"Atlanta\",\"state\":\"GA\",\"zip\":\"30303\",\"country\":\"USA\"}',
              'Credit Card', 'Received')",
              
            "INSERT INTO `order_items` (`id`, `orderId`, `productId`, `quantity`, `price`)
            VALUES ('OI001', 'ORD001', 'P001', 2, 24.99)",
            
            "INSERT INTO `order_items` (`id`, `orderId`, `productId`, `quantity`, `price`)
            VALUES ('OI002', 'ORD001', 'P003', 3, 24.99)"
        ];
        
        // Execute each SQL statement separately and track errors
        $successCount = 0;
        foreach ($sqlStatements as $index => $statement) {
            try {
                $pdo->exec($statement);
                $successCount++;
            } catch (PDOException $e) {
                // Store the error but continue with other statements
                $sqlErrors[] = [
                    'statement' => $index + 1,
                    'sql' => $statement,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Set success or partial success message
        if (empty($sqlErrors)) {
            $isExecuted = true;
            $successMessage = "All orders tables and data created successfully! Please delete this file now.";
        } else {
            // Some statements failed but others might have succeeded
            $isExecuted = true;
            $successMessage = "$successCount of " . count($sqlStatements) . " SQL statements executed successfully.";
            $errorMessage = "Some SQL statements failed. See details below.";
        }
        
    } catch (PDOException $e) {
        $errorMessage = "Database Connection Error: " . $e->getMessage();
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
    <style>
        /* Basic styling without relying on Tailwind */
        body {
            font-family: Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h1 {
            font-family: 'Merienda', cursive, Arial, sans-serif;
            color: #556B2F;
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #ccc;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .alert-info {
            background-color: #d1ecf1;
            border-color: #17a2b8;
            color: #0c5460;
        }
        .btn {
            display: inline-block;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            cursor: pointer;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 4px;
            text-decoration: none;
            margin: 5px;
            border: none;
        }
        .btn-primary {
            background-color: #6B8E23;
            color: white;
        }
        .btn-primary:hover {
            background-color: #556B2F;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 14px;
            color: #777;
        }
        code {
            background-color: #f7f7f9;
            padding: 3px 5px;
            border-radius: 3px;
            font-family: monospace;
            display: block;
            padding: 10px;
            margin: 10px 0;
            white-space: pre-wrap;
            word-break: break-all;
        }
        ul {
            list-style-type: disc;
            margin-left: 20px;
        }
        .error-details {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: 20px;
            border-radius: 4px;
        }
        .error-item {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .error-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Orders Table Setup</h1>
        
        <?php if ($isExecuted): ?>
            <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <p><strong>Success!</strong></p>
                    <p><?php echo $successMessage; ?></p>
                </div>
                
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-warning">
                        <p><strong>Warning:</strong></p>
                        <p><?php echo $errorMessage; ?></p>
                    </div>
                    
                    <?php if (!empty($sqlErrors)): ?>
                        <div class="error-details">
                            <h3>SQL Error Details:</h3>
                            <?php foreach ($sqlErrors as $error): ?>
                                <div class="error-item">
                                    <p><strong>Statement #<?php echo $error['statement']; ?>:</strong></p>
                                    <code><?php echo htmlspecialchars($error['sql']); ?></code>
                                    <p><strong>Error:</strong> <?php echo htmlspecialchars($error['error']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="alert alert-warning">
                    <p><strong>Important Security Notice</strong></p>
                    <p>For security reasons, please delete this file immediately:</p>
                    <code><?php echo __FILE__; ?></code>
                </div>
                <div class="text-center">
                    <a href="/?page=admin" class="btn btn-primary">
                        Go to Admin Dashboard
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <p><strong>Error!</strong></p>
                    <p><?php echo $errorMessage; ?></p>
                </div>
                <form method="post" class="text-center">
                    <input type="hidden" name="token" value="<?php echo $securityToken; ?>">
                    <button type="submit" name="confirm" value="1" class="btn btn-primary">
                        Try Again
                    </button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <p><strong>Information</strong></p>
                <p>This script will create the necessary tables for order management in your database.</p>
                <p><strong>Environment:</strong> <?php echo $isLocalhost ? 'LOCAL' : 'PRODUCTION'; ?></p>
                <p><strong>Database:</strong> <?php echo $db; ?> on <?php echo $host; ?></p>
            </div>
            
            <div class="alert alert-warning">
                <p><strong>Warning!</strong></p>
                <p>This script will create the following tables:</p>
                <ul>
                    <li><code>orders</code> - For storing order information</li>
                    <li><code>order_items</code> - For storing order line items</li>
                </ul>
                <p>If these tables already exist, they will not be modified.</p>
            </div>
            
            <form method="post" class="text-center">
                <input type="hidden" name="token" value="<?php echo $securityToken; ?>">
                <button type="submit" name="confirm" value="1" class="btn btn-primary">
                    Create Orders Tables
                </button>
            </form>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <p>Whimsical Frog E-commerce - <?php echo date('Y'); ?></p>
        <p>For security, delete this file after use.</p>
    </div>
</body>
</html>
