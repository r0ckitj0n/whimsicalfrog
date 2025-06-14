<?php
/**
 * WhimsicalFrog Database Migration Script
 * 
 * This script synchronizes the live database structure with the local development database.
 * WARNING: This is a destructive operation that will DROP the existing orders table!
 * 
 * Visit this file in your browser to run the migration on your live site.
 * 
 * IMPORTANT: Always backup your database before running migrations!
 */

// Security check - change this password
define('MIGRATION_PASSWORD', 'migrate2025');

// Database configuration
require_once 'api/config.php';

// Check if password is provided
$password = $_POST['password'] ?? $_GET['password'] ?? '';
$runMigration = $_POST['run_migration'] ?? false;
$confirmed = $_POST['confirmed'] ?? false;
$backupFirst = $_POST['backup_first'] ?? false;

// Embedded SQL for table structure and data (exact copy from local database)
$embeddedSQL = <<<SQL
-- Drop existing table
DROP TABLE IF EXISTS `orders`;

-- Create table with exact local structure
CREATE TABLE `orders` (
  `id` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `userId` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `paymentMethod` varchar(50) NOT NULL DEFAULT 'Credit Card',
  `checkNumber` varchar(64) DEFAULT NULL COMMENT 'Check number if payment method is Check',
  `shippingAddress` text,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `trackingNumber` varchar(100) DEFAULT NULL,
  `paymentStatus` varchar(20) NOT NULL DEFAULT 'Pending',
  `paymentDate` date DEFAULT NULL COMMENT 'Date when the payment was received or processed',
  `paymentNotes` text COMMENT 'Specific notes related to the payment transaction',
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Insert sample data from local database
INSERT INTO `orders` VALUES 
('O12345', 'U001', 59.97, 'Credit Card', NULL, '{"name":"Admin User","street":"123 Main St","city":"Dawsonville","state":"GA","zip":"30534"}', 'Completed', '2025-06-08 10:25:15', '', 'Received', NULL, NULL),
('O23456', 'U002', 24.99, 'PayPal', NULL, '{"name":"Test Customer","street":"456 Oak Ave","city":"Atlanta","state":"GA","zip":"30303"}', 'Shipped', '2025-06-06 10:25:15', '1234', 'Received', NULL, NULL),
('O34567', 'U002', 149.95, 'Credit Card', NULL, '{"name":"Test Customer","street":"456 Oak Ave","city":"Atlanta","state":"GA","zip":"30303"}', 'Delivered', '2025-06-01 10:25:15', NULL, 'Received', NULL, NULL),
('TEST001', 'U001', 45.99, 'Check', '1234', NULL, 'Pending', '2025-06-09 15:27:21', NULL, 'Pending', NULL, 'Check cleared on 6/9/25'),
('TEST002', 'U001', 29.99, 'Cash', NULL, NULL, 'Pending', '2025-06-09 15:27:21', NULL, 'Received', '2025-06-09', 'Cash payment received in store'),
('TEST003', 'U001', 67.50, 'Check', '5678', NULL, 'Pending', '2025-06-09 15:27:21', NULL, 'Pending', NULL, 'Check #5678 - customer promises to deliver tomorrow');
SQL;

// Function to get table structure
function getTableStructure($pdo, $tableName) {
    try {
        $columns = [];
        $stmt = $pdo->query("DESCRIBE `{$tableName}`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row;
        }
        
        // Get collation info
        $collationStmt = $pdo->query("SELECT COLUMN_NAME, COLLATION_NAME 
                                      FROM information_schema.COLUMNS 
                                      WHERE TABLE_SCHEMA = DATABASE() 
                                      AND TABLE_NAME = '{$tableName}'");
        $collations = [];
        while ($row = $collationStmt->fetch(PDO::FETCH_ASSOC)) {
            $collations[$row['COLUMN_NAME']] = $row['COLLATION_NAME'];
        }
        
        // Add collation info to columns
        foreach ($columns as &$column) {
            $column['Collation'] = $collations[$column['Field']] ?? 'N/A';
        }
        
        return $columns;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to backup table data
function backupTableData($pdo, $tableName) {
    try {
        $backup = [];
        $stmt = $pdo->query("SELECT * FROM `{$tableName}`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $backup[] = $row;
        }
        return $backup;
    } catch (PDOException $e) {
        return false;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhimsicalFrog Database Synchronization</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background-color: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #87ac3a; margin-bottom: 20px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .danger { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .info { background: #e2f0fb; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .form-group { margin: 20px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, button { padding: 10px; font-size: 16px; border: 1px solid #ddd; border-radius: 5px; }
        input[type="password"] { width: 300px; }
        button { background: #87ac3a; color: white; cursor: pointer; border: none; }
        button:hover { background: #6b8e23; }
        .migration-results { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; font-family: monospace; }
        .step { margin: 10px 0; padding: 10px; border-left: 4px solid #87ac3a; background: #f8f9fa; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .backup-data { max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐸 WhimsicalFrog Database Synchronization</h1>
        
        <?php if (empty($password) || $password !== MIGRATION_PASSWORD): ?>
            <div class="warning">
                <h3>⚠️ Security Check Required</h3>
                <p>Enter the migration password to proceed. This prevents unauthorized database modifications.</p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="password">Migration Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Authenticate</button>
            </form>
            
        <?php elseif (!$confirmed): ?>
            <div class="danger">
                <h3>⚠️ DESTRUCTIVE DATABASE OPERATION</h3>
                <p><strong>WARNING:</strong> This operation will completely replace your live orders table with the local database structure.</p>
                <p><strong>ALL EXISTING DATA IN THE ORDERS TABLE WILL BE LOST!</strong></p>
                <h4>Changes that will be made:</h4>
                <ul>
                    <li>Drop the existing <code>orders</code> table</li>
                    <li>Create a new <code>orders</code> table with the exact local structure</li>
                    <li>Insert sample data from the local database</li>
                    <li>Fix all column types and collations</li>
                </ul>
                <p><strong>BACKUP RECOMMENDATION:</strong> You should backup your database before proceeding!</p>
            </div>
            
            <div class="info">
                <h3>ℹ️ Database Connection</h3>
                <p>Connecting to database: <code><?= htmlspecialchars($dsn) ?></code></p>
            </div>
            
            <?php
            try {
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ]);
                
                echo '<div class="success"><h3>✅ Database Connection Successful</h3></div>';
                
                // Check if orders table exists
                $tableExists = false;
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
                    $tableExists = ($stmt->rowCount() > 0);
                } catch (PDOException $e) {
                    // Table doesn't exist
                }
                
                if ($tableExists) {
                    echo '<div class="info"><h3>📋 Current Orders Table Structure</h3>';
                    echo '<table>';
                    echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th><th>Collation</th></tr>';
                    
                    $structure = getTableStructure($pdo, 'orders');
                    foreach ($structure as $column) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($column['Field']) . '</td>';
                        echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
                        echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
                        echo '<td>' . htmlspecialchars($column['Default'] ?? 'NULL') . '</td>';
                        echo '<td>' . htmlspecialchars($column['Collation'] ?? 'N/A') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '</div>';
                    
                    // Count rows
                    $rowCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                    echo '<div class="info"><p>The orders table contains ' . $rowCount . ' order(s).</p></div>';
                } else {
                    echo '<div class="warning"><h3>⚠️ Orders Table Not Found</h3><p>The orders table does not exist in your database.</p></div>';
                }
                
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h3>❌ Database Connection Failed</h3>';
                echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            ?>
            
            <form method="POST">
                <input type="hidden" name="password" value="<?= htmlspecialchars($password) ?>">
                <input type="hidden" name="confirmed" value="1">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="backup_first" value="1" checked>
                        Backup existing orders data before replacing the table
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="run_migration" value="1" required>
                        I understand this will DELETE ALL EXISTING ORDERS and I have backed up my database
                    </label>
                </div>
                <button type="submit">Run Database Synchronization</button>
            </form>
            
        <?php else: ?>
            <?php
            // Run the migration
            try {
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ]);
                
                echo '<div class="info"><h3>ℹ️ Database Connection</h3>';
                echo '<p>Connecting to database: <code>' . htmlspecialchars($dsn) . '</code></p>';
                echo '</div>';
                
                echo '<div class="success"><h3>✅ Migration Started</h3></div>';
                
                // Backup existing data if requested
                $backupData = null;
                if ($backupFirst) {
                    try {
                        $tableExists = false;
                        $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
                        $tableExists = ($stmt->rowCount() > 0);
                        
                        if ($tableExists) {
                            $backupData = backupTableData($pdo, 'orders');
                            $backupCount = count($backupData);
                            echo '<div class="success">';
                            echo '<h3>💾 Backup Created</h3>';
                            echo "<p>Successfully backed up {$backupCount} order(s).</p>";
                            echo '</div>';
                            
                            echo '<div class="backup-data">';
                            echo '<h4>Backup Data:</h4>';
                            echo '<pre>' . htmlspecialchars(json_encode($backupData, JSON_PRETTY_PRINT)) . '</pre>';
                            echo '</div>';
                        } else {
                            echo '<div class="warning">';
                            echo '<h3>⚠️ No Backup Created</h3>';
                            echo "<p>The orders table does not exist, so no backup was created.</p>";
                            echo '</div>';
                        }
                    } catch (PDOException $e) {
                        echo '<div class="warning">';
                        echo '<h3>⚠️ Backup Failed</h3>';
                        echo '<p>Could not create backup: ' . htmlspecialchars($e->getMessage()) . '</p>';
                        echo '<p>Continuing with migration anyway...</p>';
                        echo '</div>';
                    }
                }
                
                echo '<div class="migration-results">';
                
                // Split into individual statements
                $statements = array_filter(
                    array_map('trim', 
                        explode(';', $embeddedSQL)
                    )
                );
                
                $successCount = 0;
                $errors = [];
                
                foreach ($statements as $statement) {
                    if (empty($statement) || substr(trim($statement), 0, 2) === '--') {
                        continue; // Skip empty lines and comments
                    }
                    
                    try {
                        echo "<div class='step'>Executing: " . htmlspecialchars(substr(trim($statement), 0, 80)) . "...</div>";
                        $pdo->exec($statement);
                        $successCount++;
                    } catch (PDOException $e) {
                        $errors[] = "Error in statement: " . $e->getMessage();
                        echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                    }
                }
                
                echo '</div>';
                
                if (count($errors) === 0) {
                    echo '<div class="success">';
                    echo '<h3>✅ Migration Completed Successfully!</h3>';
                    echo "<p>Executed $successCount database statements successfully.</p>";
                    echo '<h4>What was updated:</h4>';
                    echo '<ul>';
                    echo '<li>Orders table structure completely replaced</li>';
                    echo '<li>Sample data inserted</li>';
                    echo '<li>Collations standardized</li>';
                    echo '<li>Payment system columns added</li>';
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div class="warning">';
                    echo '<h3>⚠️ Migration Completed with Warnings</h3>';
                    echo "<p>Executed $successCount statements successfully, but " . count($errors) . " had issues.</p>";
                    echo '<h4>Errors encountered:</h4>';
                    echo '<ul>';
                    foreach ($errors as $error) {
                        echo '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
                
                // Show new table structure
                echo '<div class="success">';
                echo '<h3>📋 New Orders Table Structure</h3>';
                echo '<table>';
                echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th><th>Collation</th></tr>';
                
                $structure = getTableStructure($pdo, 'orders');
                foreach ($structure as $column) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($column['Field']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Default'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($column['Collation'] ?? 'N/A') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                echo '</div>';
                
                // Count rows
                $rowCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                echo '<div class="info"><p>The orders table now contains ' . $rowCount . ' order(s).</p></div>';
                
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h3>❌ Migration Failed</h3>';
                echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            ?>
            
            <div style="margin-top: 30px;">
                <h3>Next Steps:</h3>
                <ol>
                    <li>Test the admin orders page to ensure the payment system works</li>
                    <li>Check that you can view, edit, and update order payments</li>
                    <li>Delete this migration file for security: <code>migrate_database.php</code></li>
                    <li>Access your admin orders at: <a href="?page=admin_orders">Admin Orders</a></li>
                </ol>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
