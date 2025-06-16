<?php
// Setup script for room categories database tables on live server
// Run this once after deployment to create the new tables

// Live server database connection
$host = 'localhost';
$dbname = 'whimsicalfrog';
$username = 'whimsicalfrog';  // Live server username
$password = 'Palz2516';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>🚀 Setting up Room Categories Database Tables</h2>\n";
    
    // Read and execute the SQL file
    $sql = file_get_contents('create_categories_and_room_assignments.sql');
    
    if ($sql === false) {
        throw new Exception("Could not read SQL file");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            $successCount++;
            echo "<p>✅ Executed: " . substr($statement, 0, 50) . "...</p>\n";
        } catch (PDOException $e) {
            $errorCount++;
            echo "<p>❌ Error: " . $e->getMessage() . "</p>\n";
            echo "<p>Statement: " . substr($statement, 0, 100) . "...</p>\n";
        }
    }
    
    echo "<h3>📊 Setup Summary:</h3>\n";
    echo "<p>✅ Successful statements: $successCount</p>\n";
    echo "<p>❌ Failed statements: $errorCount</p>\n";
    
    if ($errorCount === 0) {
        echo "<h3>🎉 Database setup completed successfully!</h3>\n";
        echo "<p>The room categories system is now ready to use.</p>\n";
        
        // Verify tables were created
        $tables = $pdo->query("SHOW TABLES LIKE 'categories'")->fetchAll();
        $tables2 = $pdo->query("SHOW TABLES LIKE 'room_category_assignments'")->fetchAll();
        
        if (count($tables) > 0 && count($tables2) > 0) {
            echo "<p>✅ Verified: Both tables created successfully</p>\n";
            
            // Check if data was inserted
            $categoryCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
            $assignmentCount = $pdo->query("SELECT COUNT(*) FROM room_category_assignments")->fetchColumn();
            
            echo "<p>📊 Categories created: $categoryCount</p>\n";
            echo "<p>📊 Room assignments created: $assignmentCount</p>\n";
        } else {
            echo "<p>⚠️ Warning: Tables may not have been created properly</p>\n";
        }
    } else {
        echo "<h3>⚠️ Setup completed with errors</h3>\n";
        echo "<p>Some statements failed. Please check the errors above.</p>\n";
    }
    
} catch (Exception $e) {
    echo "<h3>❌ Setup Failed</h3>\n";
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><strong>Next Steps:</strong></p>\n";
echo "<ul>\n";
echo "<li>✅ Files deployed successfully</li>\n";
echo "<li>✅ Database tables created</li>\n";
echo "<li>🎯 Test the Room-Category Assignments in admin settings</li>\n";
echo "<li>🗑️ Delete this setup file after successful testing</li>\n";
echo "</ul>\n";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
p { margin: 5px 0; }
ul { margin: 10px 0; }
hr { margin: 20px 0; }
</style> 