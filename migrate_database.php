<?php
/**
 * WhimsicalFrog Database Migration Script
 * 
 * This script migrates the database structure to support the payment system.
 * Visit this file in your browser to run the migration on your live site.
 * 
 * IMPORTANT: Always backup your database before running migrations!
 */

// Security check - change this password
define('MIGRATION_PASSWORD', 'migrate2025');

// Database configuration - fixed path to use api/config.php
require_once 'api/config.php';

// Add buffered query option to prevent "unbuffered queries" error
if (!isset($options[PDO::ATTR_PERSISTENT])) {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true // Add buffered query option
    ];
}

// Check if password is provided
$password = $_POST['password'] ?? $_GET['password'] ?? '';
$runMigration = $_POST['run_migration'] ?? false;
$confirmed = $_POST['confirmed'] ?? false;

// Function to find the SQL migration file
function findMigrationFile() {
    $possibleLocations = [
        __DIR__ . '/migrate_database_structure.sql', // Same directory
        __DIR__ . '/../migrate_database_structure.sql', // Parent directory
        dirname(__FILE__) . '/migrate_database_structure.sql', // Using dirname
        'migrate_database_structure.sql', // Relative path
    ];
    
    foreach ($possibleLocations as $location) {
        if (file_exists($location)) {
            return $location;
        }
    }
    
    return false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhimsicalFrog Database Migration</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background-color: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #87ac3a; margin-bottom: 20px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .form-group { margin: 20px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, button { padding: 10px; font-size: 16px; border: 1px solid #ddd; border-radius: 5px; }
        input[type="password"] { width: 300px; }
        button { background: #87ac3a; color: white; cursor: pointer; border: none; }
        button:hover { background: #6b8e23; }
        .migration-results { background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; font-family: monospace; }
        .step { margin: 10px 0; padding: 10px; border-left: 4px solid #87ac3a; background: #f8f9fa; }
        .info { margin: 10px 0; padding: 10px; border-left: 4px solid #17a2b8; background: #f8f9fa; }
        pre { white-space: pre-wrap; word-break: break-all; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🐸 WhimsicalFrog Database Migration</h1>
        
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
            <div class="warning">
                <h3>⚠️ Database Migration Confirmation</h3>
                <p><strong>IMPORTANT:</strong> This migration will modify your database structure to support the payment system.</p>
                <h4>Changes that will be made:</h4>
                <ul>
                    <li>Create or update the <code>orders</code> table</li>
                    <li>Add payment-related columns: <code>paymentMethod</code>, <code>checkNumber</code>, <code>paymentStatus</code>, <code>paymentDate</code>, <code>paymentNotes</code></li>
                    <li>Fix collation issues between tables</li>
                    <li>Add proper indexes and constraints</li>
                    <li>Insert sample test data</li>
                </ul>
                <p><strong>Backup Recommendation:</strong> Please backup your database before proceeding!</p>
            </div>
            
            <form method="POST">
                <input type="hidden" name="password" value="<?= htmlspecialchars($password) ?>">
                <input type="hidden" name="confirmed" value="1">
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="run_migration" value="1" required>
                        I have backed up my database and want to run the migration
                    </label>
                </div>
                <button type="submit">Run Migration</button>
            </form>
            
        <?php else: ?>
            <?php
            // Run the migration
            try {
                // Show database connection info (without password)
                echo '<div class="info">';
                echo '<h3>ℹ️ Database Connection</h3>';
                echo '<p>Connecting to database: <code>' . (parse_url($dsn, PHP_URL_PATH) ?? 'Unknown') . '</code></p>';
                echo '</div>';
                
                // Use the database connection variables from api/config.php with buffered query option
                $pdo = new PDO($dsn, $user, $pass, $options);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                echo '<div class="success"><h3>✅ Migration Started</h3></div>';
                
                // Find the SQL migration file
                $sqlFile = findMigrationFile();
                if (!$sqlFile) {
                    // If file not found, output the SQL directly in the script
                    echo '<div class="warning">';
                    echo '<h3>⚠️ Migration file not found</h3>';
                    echo '<p>Using embedded SQL statements instead.</p>';
                    echo '</div>';
                    
                    // Embedded SQL as fallback
                    $sql = "-- WhimsicalFrog Database Migration Script
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
('TEST003', 'U001', 67.50, 'Check', '5678', '123 Test St, Testville, TS 12345', 'Processing', NOW(), 'Pending', 'Check #5678 - customer promises to deliver tomorrow');";
                } else {
                    echo '<div class="success">';
                    echo '<h3>✅ Found migration file</h3>';
                    echo '<p>Using SQL file: <code>' . htmlspecialchars($sqlFile) . '</code></p>';
                    echo '</div>';
                    
                    $sql = file_get_contents($sqlFile);
                }
                
                echo '<div class="migration-results">';
                
                // Split into individual statements
                $statements = array_filter(
                    array_map('trim', 
                        explode(';', $sql)
                    )
                );
                
                $successCount = 0;
                $errors = [];
                $warnings = [];
                
                foreach ($statements as $statement) {
                    if (empty($statement) || substr(trim($statement), 0, 2) === '--') {
                        continue; // Skip empty lines and comments
                    }
                    
                    try {
                        echo "<div class='step'>Executing: " . substr(trim($statement), 0, 80) . "...</div>";
                        $pdo->exec($statement);
                        $successCount++;
                    } catch (PDOException $e) {
                        // Check for "already exists" type errors that we can safely ignore
                        $errorMsg = $e->getMessage();
                        $ignorableErrors = [
                            'Duplicate column name',
                            'Duplicate key name',
                            'Duplicate entry',
                            'Multiple primary key defined',
                            'already exists',
                            'Duplicate foreign key constraint name'
                        ];
                        
                        $isIgnorable = false;
                        foreach ($ignorableErrors as $ignorableError) {
                            if (stripos($errorMsg, $ignorableError) !== false) {
                                $isIgnorable = true;
                                $warnings[] = "Warning in statement: " . $e->getMessage();
                                echo "<div class='info'>Warning: " . htmlspecialchars($e->getMessage()) . " (safely ignored)</div>";
                                break;
                            }
                        }
                        
                        if (!$isIgnorable) {
                            $errors[] = "Error in statement: " . $e->getMessage();
                            echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
                        }
                    }
                }
                
                echo '</div>';
                
                if (count($errors) === 0) {
                    echo '<div class="success">';
                    echo '<h3>✅ Migration Completed Successfully!</h3>';
                    echo "<p>Executed $successCount database statements successfully.</p>";
                    
                    if (count($warnings) > 0) {
                        echo '<h4>Warnings (safely ignored):</h4>';
                        echo '<ul>';
                        foreach ($warnings as $warning) {
                            echo '<li>' . htmlspecialchars($warning) . '</li>';
                        }
                        echo '</ul>';
                    }
                    
                    echo '<h4>What was updated:</h4>';
                    echo '<ul>';
                    echo '<li>Orders table structure created/updated</li>';
                    echo '<li>Payment system columns added</li>';
                    echo '<li>Collation issues fixed</li>';
                    echo '<li>Sample data inserted</li>';
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div class="warning">';
                    echo '<h3>⚠️ Migration Completed with Errors</h3>';
                    echo "<p>Executed $successCount statements successfully, but " . count($errors) . " had issues.</p>";
                    echo '<h4>Errors encountered:</h4>';
                    echo '<ul>';
                    foreach ($errors as $error) {
                        echo '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                }
                
                // Show current table structure
                try {
                    echo '<div class="success">';
                    echo '<h3>📋 Current Orders Table Structure</h3>';
                    echo '<table border="1" cellpadding="5" cellspacing="0" style="width: 100%; margin: 10px 0;">';
                    echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th><th>Collation</th></tr>';
                    
                    $result = $pdo->query("DESCRIBE orders");
                    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['Field']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Default'] ?? 'NULL') . '</td>';
                        
                        // Get collation for this column
                        $collResult = $pdo->prepare("SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = ?");
                        $collResult->execute([$row['Field']]);
                        $collation = $collResult->fetchColumn();
                        echo '<td>' . htmlspecialchars($collation ?? 'N/A') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '</div>';
                } catch (Exception $e) {
                    echo '<div class="error">';
                    echo '<h3>❌ Could not display table structure</h3>';
                    echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                }
                
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
