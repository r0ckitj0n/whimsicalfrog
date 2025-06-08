<?php
// Set error reporting for maximum debugging information
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'api/config.php';

// Security token to prevent accidental execution
$securityToken = md5('whimsicalfrog_marketing_setup_' . date('Ymd'));

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
    
    // Sample email subscribers
    if (tableExists($pdo, 'email_subscribers')) {
        $checkSubscribers = $pdo->query("SELECT COUNT(*) FROM email_subscribers");
        $subscriberCount = $checkSubscribers->fetchColumn();
        
        if ($subscriberCount < 5) {
            $subscribersSql = "INSERT IGNORE INTO email_subscribers (id, email, name, status, subscribed_date) VALUES 
                ('ES001', 'john@example.com', 'John Doe', 'active', '2025-05-01 10:00:00'),
                ('ES002', 'jane@example.com', 'Jane Smith', 'active', '2025-05-05 14:30:00'),
                ('ES003', 'bob@example.com', 'Bob Johnson', 'active', '2025-05-10 09:15:00'),
                ('ES004', 'sarah@example.com', 'Sarah Williams', 'unsubscribed', '2025-04-15 11:20:00'),
                ('ES005', 'mike@example.com', 'Mike Brown', 'active', '2025-05-20 16:45:00')";
                
            $results[] = executeSqlSafely($pdo, $subscribersSql, "insert sample email subscribers");
        }
    }
    
    // Sample email campaigns
    if (tableExists($pdo, 'email_campaigns')) {
        $checkCampaigns = $pdo->query("SELECT COUNT(*) FROM email_campaigns");
        $campaignCount = $checkCampaigns->fetchColumn();
        
        if ($campaignCount < 3) {
            $campaignsSql = "INSERT IGNORE INTO email_campaigns (id, name, subject, content, status, created_date, sent_date, target_audience) VALUES 
                ('EC001', 'Summer Sale', 'Don\'t Miss Our Summer Sale!', '<h1>Summer Sale</h1><p>Enjoy up to 50% off on selected items!</p>', 'sent', '2025-05-01 09:00:00', '2025-05-02 10:00:00', 'all'),
                ('EC002', 'New Products', 'Check Out Our New Products', '<h1>New Arrivals</h1><p>We\'ve just added new products to our catalog!</p>', 'draft', '2025-05-10 14:00:00', NULL, 'all'),
                ('EC003', 'Customer Feedback', 'We Value Your Feedback', '<h1>Your Opinion Matters</h1><p>Please take a moment to share your experience with us.</p>', 'scheduled', '2025-05-15 11:30:00', '2025-05-20 09:00:00', 'customers')";
                
            $results[] = executeSqlSafely($pdo, $campaignsSql, "insert sample email campaigns");
        }
    }
    
    // Sample email campaign sends
    if (tableExists($pdo, 'email_campaign_sends') && tableExists($pdo, 'email_campaigns') && tableExists($pdo, 'email_subscribers')) {
        $checkSends = $pdo->query("SELECT COUNT(*) FROM email_campaign_sends");
        $sendsCount = $checkSends->fetchColumn();
        
        if ($sendsCount < 5) {
            $sendsSql = "INSERT IGNORE INTO email_campaign_sends (id, campaign_id, subscriber_id, sent_date, opened, clicked) VALUES 
                ('ECS001', 'EC001', 'ES001', '2025-05-02 10:05:00', 1, 1),
                ('ECS002', 'EC001', 'ES002', '2025-05-02 10:05:00', 1, 0),
                ('ECS003', 'EC001', 'ES003', '2025-05-02 10:05:00', 0, 0),
                ('ECS004', 'EC001', 'ES004', '2025-05-02 10:05:00', NULL, NULL),
                ('ECS005', 'EC001', 'ES005', '2025-05-02 10:05:00', 1, 1)";
                
            $results[] = executeSqlSafely($pdo, $sendsSql, "insert sample email campaign sends");
        }
    }
    
    // Sample discount codes
    if (tableExists($pdo, 'discount_codes')) {
        $checkCodes = $pdo->query("SELECT COUNT(*) FROM discount_codes");
        $codesCount = $checkCodes->fetchColumn();
        
        if ($codesCount < 4) {
            $codesSql = "INSERT IGNORE INTO discount_codes (id, code, type, value, min_order_amount, max_uses, current_uses, start_date, end_date, status) VALUES 
                ('DC001', 'SUMMER25', 'percentage', 25.00, 50.00, 100, 45, '2025-06-01 00:00:00', '2025-06-30 23:59:59', 'active'),
                ('DC002', 'WELCOME10', 'percentage', 10.00, 0.00, 0, 124, '2025-01-01 00:00:00', '2025-12-31 23:59:59', 'active'),
                ('DC003', 'FREESHIP', 'fixed', 5.00, 25.00, 200, 87, '2025-05-01 00:00:00', '2025-07-31 23:59:59', 'active'),
                ('DC004', 'HOLIDAY50', 'percentage', 50.00, 100.00, 50, 0, '2025-12-01 00:00:00', '2025-12-25 23:59:59', 'inactive')";
                
            $results[] = executeSqlSafely($pdo, $codesSql, "insert sample discount codes");
        }
    }
    
    // Sample social accounts
    if (tableExists($pdo, 'social_accounts')) {
        $checkAccounts = $pdo->query("SELECT COUNT(*) FROM social_accounts");
        $accountsCount = $checkAccounts->fetchColumn();
        
        if ($accountsCount < 3) {
            $accountsSql = "INSERT IGNORE INTO social_accounts (id, platform, account_name, connected, api_key, api_secret) VALUES 
                ('SA001', 'facebook', 'Whimsical Frog', 1, 'fb_sample_key_123456', 'fb_sample_secret_123456'),
                ('SA002', 'instagram', '@whimsicalfrog', 1, 'ig_sample_key_123456', 'ig_sample_secret_123456'),
                ('SA003', 'twitter', '@WhimsicalFrog', 0, 'tw_sample_key_123456', 'tw_sample_secret_123456')";
                
            $results[] = executeSqlSafely($pdo, $accountsSql, "insert sample social accounts");
        }
    }
    
    // Sample social posts
    if (tableExists($pdo, 'social_posts')) {
        $checkPosts = $pdo->query("SELECT COUNT(*) FROM social_posts");
        $postsCount = $checkPosts->fetchColumn();
        
        if ($postsCount < 4) {
            $postsSql = "INSERT IGNORE INTO social_posts (id, platform, content, image_url, scheduled_date, posted_date, status) VALUES 
                ('SP001', 'facebook', 'Check out our summer collection! 🌞 #WhimsicalFrog #SummerVibes', 'images/social/summer_collection.jpg', '2025-06-01 12:00:00', '2025-06-01 12:00:00', 'posted'),
                ('SP002', 'instagram', 'New arrivals just dropped! 🐸 #WhimsicalFrog #NewArrivals', 'images/social/new_arrivals.jpg', '2025-06-05 15:30:00', '2025-06-05 15:30:00', 'posted'),
                ('SP003', 'twitter', 'Flash sale! 24 hours only - use code FLASH24 for 15% off! #WhimsicalFrog #FlashSale', NULL, '2025-06-10 09:00:00', NULL, 'scheduled'),
                ('SP004', 'facebook', 'Happy Father\\'s Day! Special discounts all weekend. #WhimsicalFrog #FathersDay', 'images/social/fathers_day.jpg', '2025-06-15 10:00:00', NULL, 'draft')";
                
            $results[] = executeSqlSafely($pdo, $postsSql, "insert sample social posts");
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
    $emailCampaignsExists = tableExists($pdo, 'email_campaigns');
    $emailSubscribersExists = tableExists($pdo, 'email_subscribers');
    $emailCampaignSendsExists = tableExists($pdo, 'email_campaign_sends');
    $discountCodesExists = tableExists($pdo, 'discount_codes');
    $socialAccountsExists = tableExists($pdo, 'social_accounts');
    $socialPostsExists = tableExists($pdo, 'social_posts');
    
    $tablesExist = $emailCampaignsExists && $emailSubscribersExists && $emailCampaignSendsExists && 
                   $discountCodesExists && $socialAccountsExists && $socialPostsExists;
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_token']) && $_POST['setup_token'] === $securityToken) {
        
        // Create Email Campaigns Table
        if (!$emailCampaignsExists) {
            $createEmailCampaignsTable = "CREATE TABLE email_campaigns (
                id VARCHAR(16) PRIMARY KEY,
                name VARCHAR(128) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'draft',
                created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                sent_date DATETIME NULL,
                target_audience VARCHAR(64) NOT NULL DEFAULT 'all',
                INDEX (status),
                INDEX (created_date)
            )";
            
            $results[] = executeSqlSafely($pdo, $createEmailCampaignsTable, "create email_campaigns table");
        } else {
            $results[] = [
                'success' => true,
                'message' => "ℹ️ Email campaigns table already exists.",
                'details' => "Skipping creation."
            ];
        }
        
        // Create Email Subscribers Table
        if (!$emailSubscribersExists) {
            $createEmailSubscribersTable = "CREATE TABLE email_subscribers (
                id VARCHAR(16) PRIMARY KEY,
                email VARCHAR(128) NOT NULL,
                name VARCHAR(128) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'active',
                subscribed_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX (email),
                INDEX (status)
            )";
            
            $results[] = executeSqlSafely($pdo, $createEmailSubscribersTable, "create email_subscribers table");
        } else {
            $results[] = [
                'success' => true,
                'message' => "ℹ️ Email subscribers table already exists.",
                'details' => "Skipping creation."
            ];
        }
        
        // Create Email Campaign Sends Table
        if (!$emailCampaignSendsExists) {
            $createEmailCampaignSendsTable = "CREATE TABLE email_campaign_sends (
                id VARCHAR(16) PRIMARY KEY,
                campaign_id VARCHAR(16) NOT NULL,
                subscriber_id VARCHAR(16) NOT NULL,
                sent_date DATETIME NOT NULL,
                opened TINYINT(1) NULL,
                clicked TINYINT(1) NULL,
                INDEX (campaign_id),
                INDEX (subscriber_id),
                INDEX (sent_date)
            )";
            
            $results[] = executeSqlSafely($pdo, $createEmailCampaignSendsTable, "create email_campaign_sends table");
        } else {
            $results[] = [
                'success' => true,
                'message' => "ℹ️ Email campaign sends table already exists.",
                'details' => "Skipping creation."
            ];
        }
        
        // Create Discount Codes Table
        if (!$discountCodesExists) {
            $createDiscountCodesTable = "CREATE TABLE discount_codes (
                id VARCHAR(16) PRIMARY KEY,
                code VARCHAR(32) NOT NULL,
                type VARCHAR(16) NOT NULL DEFAULT 'percentage',
                value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                min_order_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                max_uses INT NOT NULL DEFAULT 0,
                current_uses INT NOT NULL DEFAULT 0,
                start_date DATETIME NOT NULL,
                end_date DATETIME NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'inactive',
                INDEX (code),
                INDEX (status)
            )";
            
            $results[] = executeSqlSafely($pdo, $createDiscountCodesTable, "create discount_codes table");
        } else {
            $results[] = [
                'success' => true,
                'message' => "ℹ️ Discount codes table already exists.",
                'details' => "Skipping creation."
            ];
        }
        
        // Create Social Accounts Table
        if (!$socialAccountsExists) {
            $createSocialAccountsTable = "CREATE TABLE social_accounts (
                id VARCHAR(16) PRIMARY KEY,
                platform VARCHAR(32) NOT NULL,
                account_name VARCHAR(128) NOT NULL,
                connected TINYINT(1) NOT NULL DEFAULT 0,
                api_key VARCHAR(255) NULL,
                api_secret VARCHAR(255) NULL,
                INDEX (platform)
            )";
            
            $results[] = executeSqlSafely($pdo, $createSocialAccountsTable, "create social_accounts table");
        } else {
            $results[] = [
                'success' => true,
                'message' => "ℹ️ Social accounts table already exists.",
                'details' => "Skipping creation."
            ];
        }
        
        // Create Social Posts Table
        if (!$socialPostsExists) {
            $createSocialPostsTable = "CREATE TABLE social_posts (
                id VARCHAR(16) PRIMARY KEY,
                platform VARCHAR(32) NOT NULL,
                content TEXT NOT NULL,
                image_url VARCHAR(255) NULL,
                scheduled_date DATETIME NOT NULL,
                posted_date DATETIME NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'draft',
                INDEX (platform),
                INDEX (status),
                INDEX (scheduled_date)
            )";
            
            $results[] = executeSqlSafely($pdo, $createSocialPostsTable, "create social_posts table");
        } else {
            $results[] = [
                'success' => true,
                'message' => "ℹ️ Social posts table already exists.",
                'details' => "Skipping creation."
            ];
        }
        
        // Insert sample data if tables were created successfully
        if (!$tablesExist || (isset($results[0]['success']) && $results[0]['success'])) {
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
    <title>Whimsical Frog - Marketing Database Setup</title>
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
        .table-list {
            margin-bottom: 10px;
        }
        .table-list li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Marketing Database Setup</h1>
        
        <div class="info-box">
            <h3>Database Information</h3>
            <p><strong>Environment:</strong> <?php echo $isLocalhost ? 'LOCAL' : 'PRODUCTION'; ?></p>
            <p><strong>Database Host:</strong> <?php echo htmlspecialchars($host); ?></p>
            <p><strong>Database Name:</strong> <?php echo htmlspecialchars($db); ?></p>
        </div>
        
        <?php if ($tablesExist): ?>
            <div class="success-box">
                <h3>✓ Tables Already Exist</h3>
                <p>All marketing tables already exist in your database.</p>
                <p>You can still run the setup to add sample data if needed.</p>
            </div>
        <?php else: ?>
            <div class="warning-box">
                <h3>⚠️ Tables Need to be Created</h3>
                <p>The following tables will be created:</p>
                <ul class="table-list">
                    <?php if (!$emailCampaignsExists): ?><li><code>email_campaigns</code> - Stores email campaign information</li><?php endif; ?>
                    <?php if (!$emailSubscribersExists): ?><li><code>email_subscribers</code> - Stores email subscriber information</li><?php endif; ?>
                    <?php if (!$emailCampaignSendsExists): ?><li><code>email_campaign_sends</code> - Tracks email sends and engagement</li><?php endif; ?>
                    <?php if (!$discountCodesExists): ?><li><code>discount_codes</code> - Stores promotional discount codes</li><?php endif; ?>
                    <?php if (!$socialAccountsExists): ?><li><code>social_accounts</code> - Stores social media account connections</li><?php endif; ?>
                    <?php if (!$socialPostsExists): ?><li><code>social_posts</code> - Stores social media posts and schedules</li><?php endif; ?>
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
                        <p>You can safely delete <code>setup_marketing_tables.php</code> from your server.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($results) || $failureCount > 0): ?>
            <form method="post" action="">
                <input type="hidden" name="setup_token" value="<?php echo $securityToken; ?>">
                <button type="submit" class="button">Create Marketing Tables</button>
            </form>
        <?php endif; ?>
        
        <p style="margin-top: 20px; text-align: center;">
            <a href="/" class="button">Return to Homepage</a>
        </p>
    </div>
</body>
</html>
