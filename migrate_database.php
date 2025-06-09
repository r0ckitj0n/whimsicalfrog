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

// Check if password is provided
$password = $_POST['password'] ?? $_GET['password'] ?? '';
$runMigration = $_POST['run_migration'] ?? false;
$confirmed = $_POST['confirmed'] ?? false;

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
                // Use the database connection variables from api/config.php
                $pdo = new PDO($dsn, $user, $pass, $options);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                echo '<div class="success"><h3>✅ Migration Started</h3></div>';
                echo '<div class="migration-results">';
                
                // Read and execute the SQL migration file
                $sqlFile = 'migrate_database_structure.sql';
                if (!file_exists($sqlFile)) {
                    throw new Exception("Migration SQL file not found: $sqlFile");
                }
                
                $sql = file_get_contents($sqlFile);
                
                // Split into individual statements
                $statements = array_filter(
                    array_map('trim', 
                        preg_split('/;\\s*$/m', $sql)
                    )
                );
                
                $successCount = 0;
                $errors = [];
                
                foreach ($statements as $statement) {
                    if (empty($statement) || substr(trim($statement), 0, 2) === '--') {
                        continue; // Skip empty lines and comments
                    }
                    
                    try {
                        echo "<div class='step'>Executing: " . substr(trim($statement), 0, 80) . "...</div>";
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
                    echo '<li>Orders table structure created/updated</li>';
                    echo '<li>Payment system columns added</li>';
                    echo '<li>Collation issues fixed</li>';
                    echo '<li>Sample data inserted</li>';
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
                
                // Show current table structure
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
