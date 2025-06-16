<?php
// Fixed setup script for room categories database tables on live server
// Tries multiple connection methods to handle server-specific configurations

echo "<h2>🚀 Setting up Room Categories Database Tables</h2>\n";

// Try different connection configurations
$connectionConfigs = [
    // Config 1: Standard localhost
    [
        'host' => 'localhost',
        'dbname' => 'whimsicalfrog',
        'username' => 'whimsicalfrog',
        'password' => 'Palz2516',
        'description' => 'Standard localhost connection'
    ],
    // Config 2: 127.0.0.1 instead of localhost
    [
        'host' => '127.0.0.1',
        'dbname' => 'whimsicalfrog',
        'username' => 'whimsicalfrog',
        'password' => 'Palz2516',
        'description' => '127.0.0.1 connection'
    ],
    // Config 3: With port specification
    [
        'host' => 'localhost:3306',
        'dbname' => 'whimsicalfrog',
        'username' => 'whimsicalfrog',
        'password' => 'Palz2516',
        'description' => 'Localhost with port 3306'
    ],
    // Config 4: Try with socket specification
    [
        'host' => 'localhost',
        'dbname' => 'whimsicalfrog',
        'username' => 'whimsicalfrog',
        'password' => 'Palz2516',
        'socket' => '/var/lib/mysql/mysql.sock',
        'description' => 'With MySQL socket'
    ]
];

$pdo = null;
$workingConfig = null;

// Try each configuration
foreach ($connectionConfigs as $config) {
    echo "<p>🔄 Trying: {$config['description']}</p>\n";
    
    try {
        if (isset($config['socket'])) {
            $dsn = "mysql:unix_socket={$config['socket']};dbname={$config['dbname']};charset=utf8mb4";
        } else {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        }
        
        $pdo = new PDO($dsn, $config['username'], $config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Test the connection
        $pdo->query("SELECT 1");
        
        echo "<p>✅ Success with: {$config['description']}</p>\n";
        $workingConfig = $config;
        break;
        
    } catch (PDOException $e) {
        echo "<p>❌ Failed: " . $e->getMessage() . "</p>\n";
        $pdo = null;
    }
}

if (!$pdo) {
    echo "<h3>❌ All connection attempts failed</h3>\n";
    echo "<p>Please check your database configuration or contact your hosting provider.</p>\n";
    echo "<h4>Debug Information:</h4>\n";
    echo "<ul>\n";
    echo "<li>PHP Version: " . phpversion() . "</li>\n";
    echo "<li>PDO Available: " . (extension_loaded('pdo') ? 'Yes' : 'No') . "</li>\n";
    echo "<li>PDO MySQL Available: " . (extension_loaded('pdo_mysql') ? 'Yes' : 'No') . "</li>\n";
    echo "</ul>\n";
    exit;
}

echo "<hr>\n";
echo "<h3>🎯 Proceeding with database setup...</h3>\n";

try {
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
        try {
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
        } catch (PDOException $e) {
            echo "<p>⚠️ Could not verify tables: " . $e->getMessage() . "</p>\n";
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
echo "<p><strong>Connection Used:</strong> {$workingConfig['description']}</p>\n";
echo "<p><strong>Next Steps:</strong></p>\n";
echo "<ul>\n";
echo "<li>✅ Files deployed successfully</li>\n";
echo "<li>✅ Database connection established</li>\n";
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
.success { color: green; }
.error { color: red; }
.warning { color: orange; }
</style> 