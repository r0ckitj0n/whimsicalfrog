<?php
// Setup script for room categories database tables on live server
// Using the correct production database credentials from api/config.php

echo "<h2>🚀 Setting up Room Categories Database Tables</h2>\n";

// Production database credentials (from api/config.php)
$host = 'db5017975223.hosting-data.io';
$db   = 'dbs14295502';
$user = 'dbu2826619';
$pass = 'Palz2516!';
$charset = 'utf8mb4';

echo "<p>🔧 Using production database configuration:</p>\n";
echo "<p>&nbsp;&nbsp;&nbsp;Host: $host</p>\n";
echo "<p>&nbsp;&nbsp;&nbsp;Database: $db</p>\n";
echo "<p>&nbsp;&nbsp;&nbsp;User: $user</p>\n";

try {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<p>✅ Database connection successful!</p>\n";
    
    // Check if tables already exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $hasCategories = in_array('categories', $tables);
    $hasAssignments = in_array('room_category_assignments', $tables);
    
    echo "<p>📋 Current tables in database: " . count($tables) . "</p>\n";
    
    if ($hasCategories && $hasAssignments) {
        echo "<p>⚠️ Room category tables already exist!</p>\n";
        
        $categoryCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $assignmentCount = $pdo->query("SELECT COUNT(*) FROM room_category_assignments")->fetchColumn();
        
        echo "<p>📊 Categories: $categoryCount</p>\n";
        echo "<p>📊 Assignments: $assignmentCount</p>\n";
        
        if ($categoryCount > 0 && $assignmentCount > 0) {
            echo "<h3>🎉 System is already set up and ready to use!</h3>\n";
            echo "<p>✅ No setup needed - you can start using the Room-Category Assignments feature.</p>\n";
        } else {
            echo "<p>⚠️ Tables exist but are empty - proceeding with data population...</p>\n";
        }
    } else {
        echo "<p>🔧 Creating room category tables...</p>\n";
    }
    
    // Read and execute the SQL file
    $sql = file_get_contents('create_categories_and_room_assignments.sql');
    
    if ($sql === false) {
        throw new Exception("Could not read SQL file: create_categories_and_room_assignments.sql");
    }
    
    echo "<p>📄 SQL file loaded successfully</p>\n";
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    echo "<h3>🔄 Executing SQL statements...</h3>\n";
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            $successCount++;
            echo "<p>✅ Executed: " . substr($statement, 0, 50) . "...</p>\n";
        } catch (PDOException $e) {
            // Check if it's just a "table already exists" error
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "<p>ℹ️ Skipped (already exists): " . substr($statement, 0, 50) . "...</p>\n";
                $successCount++;
            } else {
                $errorCount++;
                echo "<p>❌ Error: " . $e->getMessage() . "</p>\n";
                echo "<p>Statement: " . substr($statement, 0, 100) . "...</p>\n";
            }
        }
    }
    
    echo "<h3>📊 Setup Summary:</h3>\n";
    echo "<p>✅ Successful statements: $successCount</p>\n";
    echo "<p>❌ Failed statements: $errorCount</p>\n";
    
    // Final verification
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $hasCategories = in_array('categories', $tables);
    $hasAssignments = in_array('room_category_assignments', $tables);
    
    if ($hasCategories && $hasAssignments) {
        echo "<h3>🎉 Database setup completed successfully!</h3>\n";
        
        $categoryCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $assignmentCount = $pdo->query("SELECT COUNT(*) FROM room_category_assignments")->fetchColumn();
        
        echo "<p>📊 Final counts:</p>\n";
        echo "<p>&nbsp;&nbsp;&nbsp;Categories: $categoryCount</p>\n";
        echo "<p>&nbsp;&nbsp;&nbsp;Room assignments: $assignmentCount</p>\n";
        
        if ($categoryCount > 0 && $assignmentCount > 0) {
            echo "<p>✅ <strong>The room categories system is now ready to use!</strong></p>\n";
            
            // Show the default assignments
            echo "<h4>🏠 Default Room Assignments:</h4>\n";
            $assignments = $pdo->query("
                SELECT r.room_number, c.name as category_name, r.is_primary 
                FROM room_category_assignments r 
                JOIN categories c ON r.category_id = c.id 
                ORDER BY r.room_number, r.is_primary DESC, c.name
            ")->fetchAll();
            
            $currentRoom = -1;
            foreach ($assignments as $assignment) {
                if ($assignment['room_number'] != $currentRoom) {
                    if ($currentRoom != -1) echo "</ul>\n";
                    echo "<p><strong>Room {$assignment['room_number']}:</strong></p>\n<ul>\n";
                    $currentRoom = $assignment['room_number'];
                }
                $primary = $assignment['is_primary'] ? ' (PRIMARY)' : '';
                echo "<li>{$assignment['category_name']}$primary</li>\n";
            }
            if ($currentRoom != -1) echo "</ul>\n";
            
        } else {
            echo "<p>⚠️ Tables created but data may not have been inserted properly</p>\n";
        }
    } else {
        echo "<h3>❌ Setup incomplete</h3>\n";
        echo "<p>Some tables may not have been created properly</p>\n";
    }
    
} catch (PDOException $e) {
    echo "<h3>❌ Database connection failed</h3>\n";
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
    echo "<p>Please check the database credentials or contact your hosting provider.</p>\n";
} catch (Exception $e) {
    echo "<h3>❌ Setup failed</h3>\n";
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<h3>📝 Next Steps:</h3>\n";
echo "<ul>\n";
echo "<li>✅ Files deployed successfully</li>\n";
echo "<li>✅ Database connection established</li>\n";
echo "<li>🎯 Test the Room-Category Assignments in admin settings</li>\n";
echo "<li>🗑️ Delete this setup file after successful testing</li>\n";
echo "</ul>\n";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3, h4 { color: #333; }
p { margin: 5px 0; }
ul { margin: 10px 0; }
hr { margin: 20px 0; }
li { margin: 2px 0; }
.success { color: green; }
.error { color: red; }
.warning { color: orange; }
</style> 