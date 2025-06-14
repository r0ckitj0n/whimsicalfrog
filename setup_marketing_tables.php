<?php
/**
 * Marketing Tables Setup Script for Whimsical Frog
 * 
 * This script creates the missing marketing tables:
 * - email_campaigns
 * - email_subscribers
 * - discount_codes
 * - social_accounts
 * - social_posts
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once __DIR__ . '/api/config.php';

// HTML header for better readability
echo "<!DOCTYPE html>
<html>
<head>
    <title>Marketing Tables Setup</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1, h2, h3 { color: #556B2F; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow: auto; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #87ac3a; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .button { display: inline-block; background-color: #87ac3a; color: white; padding: 10px 15px; 
                 text-decoration: none; border-radius: 5px; margin-top: 20px; }
        .button:hover { background-color: #6B8E23; }
    </style>
</head>
<body>
    <h1>Whimsical Frog Marketing Tables Setup</h1>";

// Connect to database
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "<div class='section'>";
    echo "<p class='success'>✅ Database connection successful!</p>";
    echo "<p>Connected to: " . htmlspecialchars($host) . "/" . htmlspecialchars($db) . "</p>";
    echo "</div>";
} catch (PDOException $e) {
    echo "<div class='section'>";
    echo "<p class='error'>❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database configuration in api/config.php</p>";
    echo "</div>";
    echo "</body></html>";
    exit;
}

// Define the tables to create
$tables = [
    'email_campaigns' => [
        'sql' => "CREATE TABLE IF NOT EXISTS email_campaigns (
            id VARCHAR(16) NOT NULL PRIMARY KEY,
            name VARCHAR(128) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            target_audience VARCHAR(50) DEFAULT 'all',
            status VARCHAR(20) DEFAULT 'draft',
            created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_date TIMESTAMP NULL
        )",
        'sample_data' => [
            [
                'id' => 'EC001',
                'name' => 'Summer Sale Announcement',
                'subject' => '🌞 Summer Sale - 20% Off All Products!',
                'content' => '<h1>Summer Sale!</h1><p>Enjoy 20% off all products this summer. Use code SUMMER20 at checkout.</p>',
                'target_audience' => 'all',
                'status' => 'draft',
                'created_date' => date('Y-m-d H:i:s'),
                'sent_date' => null
            ],
            [
                'id' => 'EC002',
                'name' => 'New Product Launch',
                'subject' => 'Introducing Our New Custom Tumblers!',
                'content' => '<h1>New Products Alert!</h1><p>Check out our new line of custom tumblers with unique designs.</p>',
                'target_audience' => 'customers',
                'status' => 'scheduled',
                'created_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'sent_date' => date('Y-m-d H:i:s', strtotime('+3 days'))
            ],
            [
                'id' => 'EC003',
                'name' => 'Customer Feedback Request',
                'subject' => 'We Value Your Feedback!',
                'content' => '<h1>How Did We Do?</h1><p>Please take a moment to share your experience with our products and service.</p>',
                'target_audience' => 'customers',
                'status' => 'sent',
                'created_date' => date('Y-m-d H:i:s', strtotime('-10 days')),
                'sent_date' => date('Y-m-d H:i:s', strtotime('-7 days'))
            ]
        ]
    ],
    'email_subscribers' => [
        'sql' => "CREATE TABLE IF NOT EXISTS email_subscribers (
            id VARCHAR(16) NOT NULL PRIMARY KEY,
            email VARCHAR(128) NOT NULL,
            first_name VARCHAR(64),
            last_name VARCHAR(64),
            status VARCHAR(20) DEFAULT 'active',
            source VARCHAR(50),
            subscribe_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_email_date TIMESTAMP NULL
        )",
        'sample_data' => [
            [
                'id' => 'ES001',
                'email' => 'customer@example.com',
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'status' => 'active',
                'source' => 'website',
                'subscribe_date' => date('Y-m-d H:i:s', strtotime('-30 days')),
                'last_email_date' => date('Y-m-d H:i:s', strtotime('-7 days'))
            ],
            [
                'id' => 'ES002',
                'email' => 'jane.doe@example.com',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'status' => 'active',
                'source' => 'checkout',
                'subscribe_date' => date('Y-m-d H:i:s', strtotime('-15 days')),
                'last_email_date' => date('Y-m-d H:i:s', strtotime('-7 days'))
            ],
            [
                'id' => 'ES003',
                'email' => 'john.smith@example.com',
                'first_name' => 'John',
                'last_name' => 'Smith',
                'status' => 'active',
                'source' => 'website',
                'subscribe_date' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'last_email_date' => null
            ]
        ]
    ],
    'discount_codes' => [
        'sql' => "CREATE TABLE IF NOT EXISTS discount_codes (
            id VARCHAR(16) NOT NULL PRIMARY KEY,
            code VARCHAR(32) NOT NULL,
            type VARCHAR(20) DEFAULT 'percentage',
            value DECIMAL(10,2) NOT NULL,
            min_order_amount DECIMAL(10,2) DEFAULT 0,
            max_uses INT DEFAULT 0,
            current_uses INT DEFAULT 0,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status VARCHAR(20) DEFAULT 'active'
        )",
        'sample_data' => [
            [
                'id' => 'DC001',
                'code' => 'SUMMER20',
                'type' => 'percentage',
                'value' => 20.00,
                'min_order_amount' => 50.00,
                'max_uses' => 100,
                'current_uses' => 12,
                'start_date' => date('Y-m-d', strtotime('-10 days')),
                'end_date' => date('Y-m-d', strtotime('+20 days')),
                'status' => 'active'
            ],
            [
                'id' => 'DC002',
                'code' => 'WELCOME10',
                'type' => 'percentage',
                'value' => 10.00,
                'min_order_amount' => 0.00,
                'max_uses' => 0,
                'current_uses' => 45,
                'start_date' => date('Y-m-d', strtotime('-60 days')),
                'end_date' => date('Y-m-d', strtotime('+305 days')),
                'status' => 'active'
            ],
            [
                'id' => 'DC003',
                'code' => 'FREESHIP',
                'type' => 'fixed',
                'value' => 5.99,
                'min_order_amount' => 25.00,
                'max_uses' => 200,
                'current_uses' => 87,
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d', strtotime('+15 days')),
                'status' => 'active'
            ]
        ]
    ],
    'social_accounts' => [
        'sql' => "CREATE TABLE IF NOT EXISTS social_accounts (
            id VARCHAR(16) NOT NULL PRIMARY KEY,
            platform VARCHAR(32) NOT NULL,
            account_name VARCHAR(64) NOT NULL,
            connected BOOLEAN DEFAULT FALSE,
            auth_token TEXT,
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        'sample_data' => [
            [
                'id' => 'SA001',
                'platform' => 'facebook',
                'account_name' => 'Whimsical Frog Crafts',
                'connected' => true,
                'auth_token' => null,
                'last_updated' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 'SA002',
                'platform' => 'instagram',
                'account_name' => '@whimsicalfrog',
                'connected' => true,
                'auth_token' => null,
                'last_updated' => date('Y-m-d H:i:s')
            ],
            [
                'id' => 'SA003',
                'platform' => 'twitter',
                'account_name' => '@whimsicalfrog',
                'connected' => false,
                'auth_token' => null,
                'last_updated' => date('Y-m-d H:i:s')
            ]
        ]
    ],
    'social_posts' => [
        'sql' => "CREATE TABLE IF NOT EXISTS social_posts (
            id VARCHAR(16) NOT NULL PRIMARY KEY,
            platform VARCHAR(32) NOT NULL,
            content TEXT NOT NULL,
            image_url VARCHAR(255),
            status VARCHAR(20) DEFAULT 'draft',
            scheduled_date TIMESTAMP NULL,
            posted_date TIMESTAMP NULL,
            account_id VARCHAR(16),
            FOREIGN KEY (account_id) REFERENCES social_accounts(id)
        )",
        'sample_data' => [
            [
                'id' => 'SP001',
                'platform' => 'facebook',
                'content' => 'Check out our new summer collection! Perfect for those hot days. #WhimsicalFrog #SummerVibes',
                'image_url' => 'images/products/product_custom-tumbler-20oz.png',
                'status' => 'scheduled',
                'scheduled_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
                'posted_date' => null,
                'account_id' => 'SA001'
            ],
            [
                'id' => 'SP002',
                'platform' => 'instagram',
                'content' => 'Our new tumblers keep your drinks cold for 24 hours! Perfect for summer adventures. #WhimsicalFrog #StayHydrated',
                'image_url' => 'images/products/product_custom-tumbler-30oz.png',
                'status' => 'posted',
                'scheduled_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
                'posted_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
                'account_id' => 'SA002'
            ],
            [
                'id' => 'SP003',
                'platform' => 'facebook',
                'content' => 'Use code SUMMER20 for 20% off all products this week only! #WhimsicalFrog #SummerSale',
                'image_url' => null,
                'status' => 'draft',
                'scheduled_date' => null,
                'posted_date' => null,
                'account_id' => 'SA001'
            ]
        ]
    ]
];

// Create tables and insert sample data
echo "<div class='section'>";
echo "<h2>Creating Marketing Tables</h2>";
echo "<table>";
echo "<tr><th>Table</th><th>Status</th><th>Sample Data</th></tr>";

foreach ($tables as $tableName => $tableData) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($tableName) . "</td>";
    
    try {
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '" . $tableName . "'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            echo "<td class='warning'>⚠️ Already exists</td>";
        } else {
            // Create table
            $pdo->exec($tableData['sql']);
            echo "<td class='success'>✅ Created successfully</td>";
        }
        
        // Insert sample data if table was just created or is empty
        $countStmt = $pdo->query("SELECT COUNT(*) FROM " . $tableName);
        $rowCount = $countStmt->fetchColumn();
        
        if ($rowCount == 0 && isset($tableData['sample_data'])) {
            $insertCount = 0;
            
            foreach ($tableData['sample_data'] as $row) {
                $columns = implode(", ", array_keys($row));
                $placeholders = implode(", ", array_fill(0, count($row), "?"));
                
                $sql = "INSERT INTO " . $tableName . " (" . $columns . ") VALUES (" . $placeholders . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($row));
                $insertCount++;
            }
            
            echo "<td class='success'>✅ Added " . $insertCount . " sample records</td>";
        } else {
            echo "<td class='warning'>⚠️ Data already exists (" . $rowCount . " records)</td>";
        }
    } catch (PDOException $e) {
        echo "<td class='error' colspan='2'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</td>";
    }
    
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// Show success message and link to marketing page
echo "<div class='section'>";
echo "<h2>Setup Complete</h2>";
echo "<p>Marketing tables have been created and populated with sample data.</p>";
echo "<p>You can now use the marketing features in the admin dashboard.</p>";
echo "<a href='/?page=admin&section=marketing' class='button'>Go to Marketing Dashboard</a>";
echo "</div>";

// Close the database connection
$pdo = null;

echo "</body></html>";
?>
