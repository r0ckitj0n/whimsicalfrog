<?php
// Email Logs Database Initialization Script
// This script creates the email_logs table and sets up the database structure for email history functionality

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once 'config.php';

try {
    // Create PDO connection
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "<h2>Email Logs Database Initialization</h2>";
    echo "<p>Starting database setup...</p>";
    
    // Check if email_logs table exists
    $tableExists = false;
    try {
        $stmt = $pdo->query("DESCRIBE email_logs");
        $tableExists = true;
        echo "<p>✅ email_logs table already exists.</p>";
    } catch (PDOException $e) {
        echo "<p>📋 email_logs table does not exist. Creating...</p>";
    }
    
    if (!$tableExists) {
        // Create email_logs table
        $createTableSQL = "
        CREATE TABLE email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            to_email VARCHAR(255) NOT NULL,
            from_email VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            content TEXT NOT NULL,
            email_type ENUM('order_confirmation', 'admin_notification', 'test_email', 'manual_resend') NOT NULL,
            status ENUM('sent', 'failed') NOT NULL,
            error_message TEXT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            order_id VARCHAR(50) NULL,
            created_by VARCHAR(100) NULL,
            INDEX idx_email_type (email_type),
            INDEX idx_status (status),
            INDEX idx_sent_at (sent_at),
            INDEX idx_order_id (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($createTableSQL);
        echo "<p>✅ email_logs table created successfully with proper indexes.</p>";
        
        // Insert sample data to verify table structure
        $insertSampleSQL = "
        INSERT INTO email_logs (to_email, from_email, subject, content, email_type, status, sent_at, created_by) 
        VALUES 
        ('admin@whimsicalfrog.us', 'orders@whimsicalfrog.us', 'Email System Initialized', 
         '<h2>Email Logging System</h2><p>The email history system has been successfully initialized.</p>', 
         'test_email', 'sent', NOW(), 'system')";
        
        $pdo->exec($insertSampleSQL);
        echo "<p>✅ Sample email log entry created.</p>";
    }
    
    // Verify table structure
    $stmt = $pdo->query("DESCRIBE email_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>📋 Table Structure Verification:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check indexes
    $stmt = $pdo->query("SHOW INDEX FROM email_logs");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>🔍 Index Verification:</h3>";
    echo "<ul>";
    $indexNames = [];
    foreach ($indexes as $index) {
        if (!in_array($index['Key_name'], $indexNames)) {
            $indexNames[] = $index['Key_name'];
            echo "<li>" . htmlspecialchars($index['Key_name']) . " on " . htmlspecialchars($index['Column_name']) . "</li>";
        }
    }
    echo "</ul>";
    
    // Test database operations
    echo "<h3>🧪 Testing Database Operations:</h3>";
    
    // Test insert
    $testInsert = "INSERT INTO email_logs (to_email, from_email, subject, content, email_type, status, created_by) 
                   VALUES ('test@example.com', 'orders@whimsicalfrog.us', 'Test Insert', 'Test content', 'test_email', 'sent', 'init_script')";
    $pdo->exec($testInsert);
    $insertId = $pdo->lastInsertId();
    echo "<p>✅ Test insert successful (ID: $insertId)</p>";
    
    // Test select
    $stmt = $pdo->prepare("SELECT * FROM email_logs WHERE id = ?");
    $stmt->execute([$insertId]);
    $testRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testRecord) {
        echo "<p>✅ Test select successful</p>";
    } else {
        echo "<p>❌ Test select failed</p>";
    }
    
    // Test update
    $updateSQL = "UPDATE email_logs SET subject = 'Updated Test Subject' WHERE id = ?";
    $stmt = $pdo->prepare($updateSQL);
    $stmt->execute([$insertId]);
    echo "<p>✅ Test update successful</p>";
    
    // Clean up test record
    $deleteSQL = "DELETE FROM email_logs WHERE id = ?";
    $stmt = $pdo->prepare($deleteSQL);
    $stmt->execute([$insertId]);
    echo "<p>✅ Test delete successful</p>";
    
    // Count existing records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_logs");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>📊 Current email log count: $count records</p>";
    
    echo "<h3>🎉 Email Logs Database Initialization Complete!</h3>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Database table created with proper structure</li>";
    echo "<li>✅ Indexes created for optimal performance</li>";
    echo "<li>✅ Database operations tested successfully</li>";
    echo "<li>📧 Email history system is ready to use</li>";
    echo "<li>🔧 Test the email configuration in Admin Settings</li>";
    echo "</ul>";
    
    // Show current database configuration
    echo "<h3>🔧 Current Database Configuration:</h3>";
    echo "<p><strong>Host:</strong> " . (isset($host) ? $host : 'Not set') . "</p>";
    echo "<p><strong>Database:</strong> " . (isset($dbname) ? $dbname : 'Not set') . "</p>";
    echo "<p><strong>User:</strong> " . (isset($user) ? $user : 'Not set') . "</p>";
    
    // Environment detection
    $isLocal = ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);
    echo "<p><strong>Environment:</strong> " . ($isLocal ? 'Local Development' : 'Production Server') . "</p>";
    
    if (!$isLocal) {
        echo "<p>🚀 <strong>Production server detected</strong> - Email logging system is live!</p>";
    }
    
} catch (PDOException $e) {
    echo "<h2>❌ Database Error</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration in config.php</p>";
    
    // Show database connection details for debugging
    echo "<h3>🔧 Debug Information:</h3>";
    echo "<p><strong>DSN:</strong> " . htmlspecialchars($dsn ?? 'Not set') . "</p>";
    echo "<p><strong>User:</strong> " . htmlspecialchars($user ?? 'Not set') . "</p>";
    echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ General Error</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?> 