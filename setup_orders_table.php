<?php
// Set error reporting for maximum debugging information
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'api/config.php';

// Security token to prevent accidental execution
$securityToken = md5('whimsicalfrog_setup_' . date('Ymd'));

// Function to check if a table exists
function tableExists($pdo, $tableName) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        return $result->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to execute SQL safely and return status
function executeSqlSafely($pdo, $sql, $description) {
    try {
        $result = $pdo->exec($sql);
        return [
            'success' => true,
            'message' => "✓ {$description} successfully.",
            'details' => "Affected rows: {$result}"
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => "✗ Failed to {$description}.",
            'details' => $e->getMessage()
        ];
    }
}

// Function to insert sample data safely
function insertSampleData($pdo) {
    $results = [];
    
    // Sample users (if needed)
    if (tableExists($pdo, 'users')) {
        $checkUsers = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $checkUsers->fetchColumn();
        
        if ($userCount < 3) {
            $userSql = "INSERT IGNORE INTO users (id, username, password, email, role, roleType) VALUES 
                ('U001', 'admin', 'pass.123', 'admin@whimsicalfrog.com', 'Admin', 'Admin'),
                ('U002', 'customer', 'pass.123', 'customer@example.com', 'Customer', 'Customer'),
                ('U003', 'testuser', 'pass.123', 'test@example.com', 'Customer', 'Customer')";
                
            $results[] = executeSqlSafely($pdo, $userSql, "insert sample users");
        }
    }
    
    // Sample orders
    if (tableExists($pdo, 'orders') && tableExists($pdo, 'order_items')) {
        $checkOrders = $pdo->query("SELECT COUNT(*) FROM orders");
        $orderCount = $checkOrders->fetchColumn();
        
        if ($orderCount == 0) {
            // Insert sample orders
            $ordersSql = "INSERT INTO orders (id, userId, orderDate, totalAmount, status, paymentStatus, shippingAddress, billingAddress) VALUES 
                ('O001', 'U002', '2025-05-15 10:30:00', 49.99, 'Completed', 'Paid', '{\"street\":\"123 Main St\",\"city\":\"Atlanta\",\"state\":\"GA\",\"zipCode\":\"30301\"}', '{\"street\":\"123 Main St\",\"city\":\"Atlanta\",\"state\":\"GA\",\"zipCode\":\"30301\"}'),
                ('O002', 'U003', '2025-05-28 14:45:00', 34.98, 'Processing', 'Paid', '{\"street\":\"456 Oak Ave\",\"city\":\"Chicago\",\"state\":\"IL\",\"zipCode\":\"60601\"}', '{\"street\":\"456 Oak Ave\",\"city\":\"Chicago\",\"state\":\"IL\",\"zipCode\":\"60601\"}'),
                ('O003', 'U002', '2025-06-05 09:15:00', 24.98, 'Shipped', 'Paid', '{\"street\":\"789 Pine Blvd\",\"city\":\"New York\",\"state\":\"NY\",\"zipCode\":\"10001\"}', '{\"street\":\"789 Pine Blvd\",\"city\":\"New York\",\"state\":\"NY\",\"zipCode\":\"10001\"}')";
                
            $results[] = executeSqlSafely($pdo, $ordersSql, "insert sample orders");
            
            // Insert sample order items
            $orderItemsSql = "INSERT INTO order_items (id, orderId, productId, quantity, price) VALUES 
                ('OI001', 'O001', 'P001', 1, 24.99),
                ('OI002', 'O001', 'P004', 1, 24.99),
                ('OI003', 'O002', 'P002', 1, 19.99),
                ('OI004', 'O002', 'P005', 1, 14.99),
                ('OI005', 'O003', 'P003', 1, 24.98)";
                
            $results[] = executeSqlSafely($pdo, $orderItemsSql, "insert sample order items");
        }
    }
    
    return $results;
}

// Process the form submission
$results = [];
$tablesExist = false;
$pdo = null;

try {
    // Create database connection
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Check if tables already exist
    $ordersExists = tableExists($pdo, 'orders');
    $orderItemsExists = tableExists($pdo, 'order_items');
    $tablesExist = $ordersExists && $orderItemsExists;
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_token']) && $_POST['setup_token'] === $securityToken) {
        
        // If tables don't exist, create them
        if (!$ordersExists) {
            // Create orders table
            $createOrdersTable = "CREATE TABLE orders (
                id VARCHAR(16) PRIMARY KEY,
                userId VARCHAR(16) NOT NULL,
                orderDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                totalAmount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                status VARCHAR(32) NOT NULL DEFAULT 'Pending',
                paymentStatus VARCHAR(32) NOT NULL DEFAULT 'Pending',
                shippingAddress JSON,
                billingAddress JSON,
                notes TEXT,
                INDEX (userId),
                INDEX (orderDate),
                INDEX (status)
            )";
            
            $results[] = executeSqlSafely($pdo, $createOrdersTable, "create orders table");
        } else {
            $results[] = [
                'success' => true,
                'message' => "ℹ️ Orders table already exists.",
                'details' => "Skipping creation."
            ];
        }
        
        if (!$orderItemsExists) {
            // Create order_items table
            $createOrderItemsTable = "CREATE TABLE order_items (
                id VARCHAR(16) PRIMARY KEY,
                orderId VARCHAR(16) NOT NULL,
                productId VARCHAR(16) NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                INDEX (orderId),
                INDEX (productId)
            )";
            
            $results[] = executeSqlSafely($pdo, $createOrderItemsTable, "create order_items table");
        } else {
            $results[] = [
                'success' => true,
                'message' => "ℹ️ Order items table already exists.",
                'details' => "Skipping creation."
            ];
        }
        
        // Insert sample data if tables were created successfully
        if ((isset($results[0]['success']) && $results[0]['success']) || 
            (isset($results[1]['success']) && $results[1]['success']) || 
            $tablesExist) {
            
            $sampleDataResults = insertSampleData($pdo);
            $results = array_merge($results, $sampleDataResults);
        }
    }
    
} catch (PDOException $e) {
    $results[] = [
        'success' => false,
        'message' => "✗ Database connection failed.",
        'details' => $e->getMessage()
    ];
} catch (Exception $e) {
    $results[] = [
        'success' => false,
        'message' => "✗ An unexpected error occurred.",
        'details' => $e->getMessage()
    ];
}

// Count successes and failures
$successCount = 0;
$failureCount = 0;

foreach ($results as $result) {
    if ($result['success']) {
        $successCount++;
    } else {
        $failureCount++;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Whimsical Frog - Orders Database Setup</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #87ac3a;
            border-bottom: 2px solid #87ac3a;
            padding-bottom: 10px;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-box {
            background-color: #e8f4fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .warning-box {
            background-color: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .success-box {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .error-box {
            background-color: #fdecea;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .button {
            display: inline-block;
            background-color: #87ac3a;
            color: white;
            padding: 12px 20px;
            text-align: center;
            text-decoration: none;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #a3cc4a;
        }
        .button.delete {
            background-color: #f44336;
        }
        .button.delete:hover {
            background-color: #e53935;
        }
        .result-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
        }
        .result-item.success {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
        }
        .result-item.error {
            background-color: #fdecea;
            border-left: 4px solid #f44336;
        }
        .result-item.info {
            background-color: #e8f4fd;
            border-left: 4px solid #2196F3;
        }
        .details {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        code {
            background-color: #f5f5f5;
            padding: 2px 4px;
            border-radius: 3px;
            font-family: monospace;
        }
        .summary {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Orders Database Setup</h1>
        
        <div class="info-box">
            <h3>Database Information</h3>
            <p><strong>Environment:</strong> <?php echo $isLocalhost ? 'LOCAL' : 'PRODUCTION'; ?></p>
            <p><strong>Database Host:</strong> <?php echo htmlspecialchars($host); ?></p>
            <p><strong>Database Name:</strong> <?php echo htmlspecialchars($db); ?></p>
        </div>
        
        <?php if ($tablesExist): ?>
            <div class="success-box">
                <h3>✓ Tables Already Exist</h3>
                <p>The orders and order_items tables already exist in your database.</p>
                <p>You can still run the setup to add sample data if needed.</p>
            </div>
        <?php else: ?>
            <div class="warning-box">
                <h3>⚠️ Tables Need to be Created</h3>
                <p>The following tables will be created:</p>
                <ul>
                    <?php if (!$ordersExists): ?><li><code>orders</code> - Stores order information</li><?php endif; ?>
                    <?php if (!$orderItemsExists): ?><li><code>order_items</code> - Stores items within each order</li><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($results)): ?>
            <div class="<?php echo $failureCount > 0 ? 'error-box' : 'success-box'; ?>">
                <h3>Setup Results</h3>
                
                <?php foreach ($results as $result): ?>
                    <div class="result-item <?php echo $result['success'] ? 'success' : 'error'; ?>">
                        <p><?php echo $result['message']; ?></p>
                        <p class="details"><?php echo htmlspecialchars($result['details']); ?></p>
                    </div>
                <?php endforeach; ?>
                
                <div class="summary">
                    <p>
                        <strong>Summary:</strong> 
                        <?php echo $successCount; ?> operation(s) succeeded, 
                        <?php echo $failureCount; ?> operation(s) failed
                    </p>
                </div>
                
                <?php if ($failureCount === 0): ?>
                    <div class="warning-box">
                        <h3>🔒 Security Warning</h3>
                        <p>Setup completed successfully. For security reasons, please delete this file now.</p>
                        <p>You can safely delete <code>setup_orders_table.php</code> from your server.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($results) || $failureCount > 0): ?>
            <form method="post" action="">
                <input type="hidden" name="setup_token" value="<?php echo $securityToken; ?>">
                <button type="submit" class="button">Create Orders Tables</button>
            </form>
        <?php endif; ?>
        
        <p style="margin-top: 20px; text-align: center;">
            <a href="/" class="button">Return to Homepage</a>
        </p>
    </div>
</body>
</html>
