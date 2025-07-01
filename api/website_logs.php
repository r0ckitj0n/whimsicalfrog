<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';

// Check admin authentication
AuthHelper::requireAdmin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = Database::getInstance();

    switch ($action) {
        case 'list_logs':
            // Auto-cleanup old logs when listing
            cleanupOldLogs($pdo);
            listAvailableLogs($pdo);
            break;
        case 'get_log':
            getLogContent($pdo);
            break;
        case 'search_logs':
            searchLogs($pdo);
            break;
        case 'clear_log':
            clearLog($pdo);
            break;
        case 'download_log':
            downloadLog($pdo);
            break;
        case 'cleanup_old_logs':
            $result = cleanupOldLogs($pdo);
            echo json_encode(['success' => true, 'cleanup_result' => $result]);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function listAvailableLogs($pdo) {
    $logs = [];
    
    // File-based logs
    $fileLogPatterns = [
        './monitor.log' => ['name' => 'System Monitor Log', 'description' => 'System monitoring and health checks', 'category' => 'System'],
        './cron_test.log' => ['name' => 'Cron Test Log', 'description' => 'Scheduled task testing and execution', 'category' => 'System'],
        './php_server.log' => ['name' => 'PHP Server Log', 'description' => 'PHP development server logs', 'category' => 'Development'],
        './autostart.log' => ['name' => 'Autostart Log', 'description' => 'Application startup and initialization', 'category' => 'System'],
        './server.log' => ['name' => 'Server Log', 'description' => 'General server activity and requests', 'category' => 'System'],
        './inventory_errors.log' => ['name' => 'Inventory Errors', 'description' => 'Inventory management error tracking', 'category' => 'Application'],
        '/var/log/apache2/access.log' => ['name' => 'Apache Access Log', 'description' => 'Web server access requests', 'category' => 'Web Server'],
        '/var/log/apache2/error.log' => ['name' => 'Apache Error Log', 'description' => 'Web server errors and warnings', 'category' => 'Web Server'],
        '/var/log/nginx/access.log' => ['name' => 'Nginx Access Log', 'description' => 'Web server access requests', 'category' => 'Web Server'],
        '/var/log/nginx/error.log' => ['name' => 'Nginx Error Log', 'description' => 'Web server errors and warnings', 'category' => 'Web Server'],
        '/var/log/mysql/error.log' => ['name' => 'MySQL Error Log', 'description' => 'Database server errors', 'category' => 'Database'],
        '/var/log/php7.4/fpm.log' => ['name' => 'PHP-FPM Log', 'description' => 'PHP FastCGI Process Manager logs', 'category' => 'PHP'],
        '/var/log/php8.0/fpm.log' => ['name' => 'PHP-FPM Log', 'description' => 'PHP FastCGI Process Manager logs', 'category' => 'PHP'],
        '/var/log/php8.1/fpm.log' => ['name' => 'PHP-FPM Log', 'description' => 'PHP FastCGI Process Manager logs', 'category' => 'PHP'],
        '/var/log/php8.2/fpm.log' => ['name' => 'PHP-FPM Log', 'description' => 'PHP FastCGI Process Manager logs', 'category' => 'PHP']
    ];
    
    // Check which file logs exist
    foreach ($fileLogPatterns as $path => $info) {
        if (file_exists($path) && is_readable($path)) {
            $size = filesize($path);
            $entries = 0;
            
            // Count lines to estimate entries
            if ($size < 10 * 1024 * 1024) { // Only count for files under 10MB
                $entries = $size > 0 ? count(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : 0;
            } else {
                $entries = 'Large file';
            }
            
            $logs[] = [
                'type' => 'file_' . md5($path),
                'name' => $info['name'],
                'description' => $info['description'],
                'category' => $info['category'],
                'entries' => is_numeric($entries) ? $entries : 0,
                'size' => formatFileSize($size),
                'path' => $path,
                'log_source' => 'file'
            ];
        }
    }
    
    // Database-based logs
    $databaseLogs = [
        'analytics_logs' => [
            'name' => 'Analytics Logs',
            'description' => 'User activity and page view tracking',
            'category' => 'Analytics',
            'table' => 'analytics_logs',
            'timestamp_field' => 'timestamp',
            'message_field' => 'event_data'
        ],
        'order_logs' => [
            'name' => 'Order Processing Logs',
            'description' => 'Order creation, updates, and fulfillment',
            'category' => 'E-commerce',
            'table' => 'order_logs',
            'timestamp_field' => 'created_at',
            'message_field' => 'log_message'
        ],
        'inventory_logs' => [
            'name' => 'Inventory Change Logs',
            'description' => 'Stock updates and inventory modifications',
            'category' => 'Inventory',
            'table' => 'inventory_logs',
            'timestamp_field' => 'timestamp',
            'message_field' => 'change_description'
        ],
        'user_activity_logs' => [
            'name' => 'User Activity Logs',
            'description' => 'User authentication and account activity',
            'category' => 'Security',
            'table' => 'user_activity_logs',
            'timestamp_field' => 'timestamp',
            'message_field' => 'activity_description'
        ],
        'error_logs' => [
            'name' => 'Application Error Logs',
            'description' => 'PHP errors, exceptions, and debugging info',
            'category' => 'Application',
            'table' => 'error_logs',
            'timestamp_field' => 'timestamp',
            'message_field' => 'error_message'
        ],
        'admin_activity_logs' => [
            'name' => 'Admin Activity Logs',
            'description' => 'Administrative actions and system changes',
            'category' => 'Administration',
            'table' => 'admin_activity_logs',
            'timestamp_field' => 'timestamp',
            'message_field' => 'action_description'
        ],
        'email_logs' => [
            'name' => 'Email Logs',
            'description' => 'Email sending history and delivery status',
            'category' => 'Communication',
            'table' => 'email_logs',
            'timestamp_field' => 'sent_at',
            'message_field' => 'email_subject'
        ]
    ];
    
    // Check database logs and create tables if needed
    foreach ($databaseLogs as $type => $info) {
        $tableName = $info['table'];
        
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            // Create table based on type
            createLogTable($pdo, $tableName, $type);
        }
        
        // Count entries
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $tableName");
            $count = $stmt->fetch()['count'];
        } catch (Exception $e) {
            $count = 0;
        }
        
        $logs[] = [
            'type' => $type,
            'name' => $info['name'],
            'description' => $info['description'],
            'category' => $info['category'],
            'entries' => $count,
            'table' => $tableName,
            'log_source' => 'database'
        ];
    }
    
    echo json_encode(['success' => true, 'logs' => $logs]);
}

function createLogTable($pdo, $tableName, $type) {
    $sql = '';
    
    switch ($type) {
        case 'analytics_logs':
            $sql = "CREATE TABLE $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                session_id VARCHAR(255),
                page_url VARCHAR(500),
                event_type VARCHAR(100),
                event_data JSON,
                user_agent TEXT,
                ip_address VARCHAR(45),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_timestamp (timestamp),
                INDEX idx_user_id (user_id),
                INDEX idx_event_type (event_type)
            )";
            break;
            
        case 'order_logs':
            $sql = "CREATE TABLE $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                log_message TEXT,
                previous_status VARCHAR(50),
                new_status VARCHAR(50),
                admin_user_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_order_id (order_id),
                INDEX idx_created_at (created_at),
                INDEX idx_action (action)
            )";
            break;
            
        case 'inventory_logs':
            $sql = "CREATE TABLE $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_sku VARCHAR(100) NOT NULL,
                change_type VARCHAR(50) NOT NULL,
                previous_quantity INT,
                new_quantity INT,
                change_amount INT,
                change_description TEXT,
                admin_user_id INT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_item_sku (item_sku),
                INDEX idx_timestamp (timestamp),
                INDEX idx_change_type (change_type)
            )";
            break;
            
        case 'user_activity_logs':
            $sql = "CREATE TABLE $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                activity_type VARCHAR(100) NOT NULL,
                activity_description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                session_id VARCHAR(255),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_timestamp (timestamp),
                INDEX idx_activity_type (activity_type)
            )";
            break;
            
        case 'error_logs':
            $sql = "CREATE TABLE $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                error_level VARCHAR(20) NOT NULL DEFAULT 'ERROR',
                error_message TEXT NOT NULL,
                file_path VARCHAR(500),
                line_number INT,
                stack_trace TEXT,
                user_id INT NULL,
                session_id VARCHAR(255),
                request_uri VARCHAR(500),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_timestamp (timestamp),
                INDEX idx_error_level (error_level),
                INDEX idx_file_path (file_path)
            )";
            break;
            
        case 'admin_activity_logs':
            $sql = "CREATE TABLE $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_user_id INT NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                action_description TEXT,
                target_type VARCHAR(100),
                target_id INT,
                ip_address VARCHAR(45),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_admin_user_id (admin_user_id),
                INDEX idx_timestamp (timestamp),
                INDEX idx_action_type (action_type)
            )";
            break;
            
        case 'email_logs':
            $sql = "CREATE TABLE $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                from_email VARCHAR(255),
                email_subject VARCHAR(500),
                email_type VARCHAR(100),
                status VARCHAR(50) DEFAULT 'sent',
                error_message TEXT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sent_at (sent_at),
                INDEX idx_to_email (to_email),
                INDEX idx_status (status)
            )";
            break;
    }
    
    if ($sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            // Table creation failed, but continue
        }
    }
}

function getLogContent($pdo) {
    $type = $_GET['type'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(10, min(500, intval($_GET['limit'] ?? 100)));
    $offset = ($page - 1) * $limit;
    
    if (strpos($type, 'file_') === 0) {
        // File-based log
        getFileLogContent($type, $page, $limit, $offset);
    } else {
        // Database-based log
        getDatabaseLogContent($pdo, $type, $page, $limit, $offset);
    }
}

function getFileLogContent($type, $page, $limit, $offset) {
    // Find the file path from our patterns
    $fileLogPatterns = [
        'file_' . md5('./monitor.log') => './monitor.log',
        'file_' . md5('./cron_test.log') => './cron_test.log',
        'file_' . md5('./php_server.log') => './php_server.log',
        'file_' . md5('./autostart.log') => './autostart.log',
        'file_' . md5('./server.log') => './server.log',
        'file_' . md5('./inventory_errors.log') => './inventory_errors.log',
        'file_' . md5('/var/log/apache2/access.log') => '/var/log/apache2/access.log',
        'file_' . md5('/var/log/apache2/error.log') => '/var/log/apache2/error.log',
        'file_' . md5('/var/log/nginx/access.log') => '/var/log/nginx/access.log',
        'file_' . md5('/var/log/nginx/error.log') => '/var/log/nginx/error.log',
        'file_' . md5('/var/log/mysql/error.log') => '/var/log/mysql/error.log',
        'file_' . md5('/var/log/php7.4/fpm.log') => '/var/log/php7.4/fpm.log',
        'file_' . md5('/var/log/php8.0/fpm.log') => '/var/log/php8.0/fpm.log',
        'file_' . md5('/var/log/php8.1/fpm.log') => '/var/log/php8.1/fpm.log',
        'file_' . md5('/var/log/php8.2/fpm.log') => '/var/log/php8.2/fpm.log'
    ];
    
    $filePath = $fileLogPatterns[$type] ?? null;
    if (!$filePath || !file_exists($filePath) || !is_readable($filePath)) {
        echo json_encode(['success' => false, 'error' => 'Log file not found or not readable']);
        return;
    }
    
    // Read file lines
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $totalLines = count($lines);
    
    // Reverse to get newest first
    $lines = array_reverse($lines);
    
    // Paginate
    $paginatedLines = array_slice($lines, $offset, $limit);
    
    // Format entries
    $entries = [];
    foreach ($paginatedLines as $i => $line) {
        $entries[] = [
            'id' => $offset + $i + 1,
            'message' => $line,
            'timestamp' => null, // File logs don't always have parseable timestamps
            'level' => extractLogLevel($line)
        ];
    }
    
    $pagination = [
        'current_page' => $page,
        'total_pages' => ceil($totalLines / $limit),
        'total_entries' => $totalLines,
        'has_previous' => $page > 1,
        'has_next' => $page < ceil($totalLines / $limit),
        'start' => $offset + 1,
        'end' => min($offset + $limit, $totalLines)
    ];
    
    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'pagination' => $pagination,
        'log_info' => [
            'type' => $type,
            'source' => 'file',
            'path' => $filePath,
            'can_clear' => is_writable($filePath)
        ]
    ]);
}

function getDatabaseLogContent($pdo, $type, $page, $limit, $offset) {
    $databaseLogs = [
        'analytics_logs' => ['table' => 'analytics_logs', 'timestamp_field' => 'timestamp', 'message_field' => 'event_data'],
        'order_logs' => ['table' => 'order_logs', 'timestamp_field' => 'created_at', 'message_field' => 'log_message'],
        'inventory_logs' => ['table' => 'inventory_logs', 'timestamp_field' => 'timestamp', 'message_field' => 'change_description'],
        'user_activity_logs' => ['table' => 'user_activity_logs', 'timestamp_field' => 'timestamp', 'message_field' => 'activity_description'],
        'error_logs' => ['table' => 'error_logs', 'timestamp_field' => 'timestamp', 'message_field' => 'error_message'],
        'admin_activity_logs' => ['table' => 'admin_activity_logs', 'timestamp_field' => 'timestamp', 'message_field' => 'action_description'],
        'email_logs' => ['table' => 'email_logs', 'timestamp_field' => 'sent_at', 'message_field' => 'email_subject']
    ];
    
    $logInfo = $databaseLogs[$type] ?? null;
    if (!$logInfo) {
        echo json_encode(['success' => false, 'error' => 'Unknown log type']);
        return;
    }
    
    $table = $logInfo['table'];
    $timestampField = $logInfo['timestamp_field'];
    $messageField = $logInfo['message_field'];
    
    try {
        // Get total count
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
        $total = $countStmt->fetch()['total'];
        
        // Get entries
        $sql = "SELECT *, $timestampField as timestamp, $messageField as message FROM $table 
                ORDER BY $timestampField DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->query($sql);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format entries
        $formattedEntries = [];
        foreach ($entries as $entry) {
            $formattedEntries[] = [
                'id' => $entry['id'],
                'message' => $entry['message'] ?? 'No message',
                'timestamp' => $entry['timestamp'],
                'level' => $entry['error_level'] ?? 'INFO',
                'context' => array_diff_key($entry, ['id' => '', 'message' => '', 'timestamp' => ''])
            ];
        }
        
        $pagination = [
            'current_page' => $page,
            'total_pages' => ceil($total / $limit),
            'total_entries' => $total,
            'has_previous' => $page > 1,
            'has_next' => $page < ceil($total / $limit),
            'start' => $offset + 1,
            'end' => min($offset + $limit, $total)
        ];
        
        echo json_encode([
            'success' => true,
            'entries' => $formattedEntries,
            'pagination' => $pagination,
            'log_info' => [
                'type' => $type,
                'source' => 'database',
                'table' => $table,
                'can_clear' => true
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function searchLogs($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $query = $input['query'] ?? '';
    $logType = $input['log_type'] ?? 'all';
    
    if (!$query) {
        echo json_encode(['success' => false, 'error' => 'Search query required']);
        return;
    }
    
    $results = [];
    
    // Search database logs
    if ($logType === 'all' || $logType === 'database') {
        $results = array_merge($results, searchDatabaseLogs($pdo, $query));
    }
    
    // Search file logs
    if ($logType === 'all' || $logType === 'files') {
        $results = array_merge($results, searchFileLogs($query));
    }
    
    // Sort by timestamp (newest first)
    usort($results, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    echo json_encode(['success' => true, 'results' => $results]);
}

function searchDatabaseLogs($pdo, $query) {
    $results = [];
    $tables = [
        'error_logs' => 'error_message',
        'order_logs' => 'log_message',
        'admin_activity_logs' => 'action_description',
        'user_activity_logs' => 'activity_description',
        'inventory_logs' => 'change_description',
        'email_logs' => 'email_subject'
    ];
    
    foreach ($tables as $table => $messageField) {
        try {
            $sql = "SELECT id, $messageField as message, 
                           COALESCE(timestamp, created_at, sent_at) as timestamp
                    FROM $table 
                    WHERE $messageField LIKE :query 
                    ORDER BY COALESCE(timestamp, created_at, sent_at) DESC 
                    LIMIT 20";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['query' => "%$query%"]);
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'log_type' => $table,
                    'id' => $row['id'],
                    'message' => $row['message'],
                    'timestamp' => $row['timestamp']
                ];
            }
        } catch (Exception $e) {
            // Table might not exist, continue
        }
    }
    
    return $results;
}

function searchFileLogs($query) {
    $results = [];
    $filePaths = [
        './monitor.log',
        './cron_test.log',
        './php_server.log',
        './autostart.log',
        './server.log',
        './inventory_errors.log'
    ];
    
    foreach ($filePaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $lineNum => $line) {
                if (stripos($line, $query) !== false) {
                    $results[] = [
                        'log_type' => 'file_' . basename($path),
                        'id' => $lineNum + 1,
                        'message' => $line,
                        'timestamp' => date('Y-m-d H:i:s') // File logs don't have reliable timestamps
                    ];
                    
                    if (count($results) >= 50) break 2; // Limit results
                }
            }
        }
    }
    
    return $results;
}

function clearLog($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    
    if (strpos($type, 'file_') === 0) {
        // Clear file log
        clearFileLog($type);
    } else {
        // Clear database log
        clearDatabaseLog($pdo, $type);
    }
}

function clearFileLog($type) {
    $fileLogPatterns = [
        'file_' . md5('./monitor.log') => './monitor.log',
        'file_' . md5('./cron_test.log') => './cron_test.log',
        'file_' . md5('./php_server.log') => './php_server.log',
        'file_' . md5('./autostart.log') => './autostart.log',
        'file_' . md5('./server.log') => './server.log',
        'file_' . md5('./inventory_errors.log') => './inventory_errors.log'
    ];
    
    $filePath = $fileLogPatterns[$type] ?? null;
    if (!$filePath || !file_exists($filePath) || !is_writable($filePath)) {
        echo json_encode(['success' => false, 'error' => 'Cannot clear this log file']);
        return;
    }
    
    if (file_put_contents($filePath, '') !== false) {
        echo json_encode(['success' => true, 'message' => 'Log file cleared successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to clear log file']);
    }
}

function clearDatabaseLog($pdo, $type) {
    $databaseLogs = [
        'analytics_logs' => 'analytics_logs',
        'order_logs' => 'order_logs',
        'inventory_logs' => 'inventory_logs',
        'user_activity_logs' => 'user_activity_logs',
        'error_logs' => 'error_logs',
        'admin_activity_logs' => 'admin_activity_logs',
        'email_logs' => 'email_logs'
    ];
    
    $table = $databaseLogs[$type] ?? null;
    if (!$table) {
        echo json_encode(['success' => false, 'error' => 'Unknown log type']);
        return;
    }
    
    try {
        $pdo->exec("DELETE FROM $table");
        echo json_encode(['success' => true, 'message' => 'Database log cleared successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to clear database log: ' . $e->getMessage()]);
    }
}

function downloadLog($pdo) {
    $type = $_GET['type'] ?? '';
    
    if (strpos($type, 'file_') === 0) {
        downloadFileLog($type);
    } else {
        downloadDatabaseLog($pdo, $type);
    }
}

function downloadFileLog($type) {
    $fileLogPatterns = [
        'file_' . md5('./monitor.log') => './monitor.log',
        'file_' . md5('./cron_test.log') => './cron_test.log',
        'file_' . md5('./php_server.log') => './php_server.log',
        'file_' . md5('./autostart.log') => './autostart.log',
        'file_' . md5('./server.log') => './server.log',
        'file_' . md5('./inventory_errors.log') => './inventory_errors.log'
    ];
    
    $filePath = $fileLogPatterns[$type] ?? null;
    if (!$filePath || !file_exists($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        return;
    }
    
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
}

function downloadDatabaseLog($pdo, $type) {
    $databaseLogs = [
        'analytics_logs' => 'analytics_logs',
        'order_logs' => 'order_logs',
        'inventory_logs' => 'inventory_logs',
        'user_activity_logs' => 'user_activity_logs',
        'error_logs' => 'error_logs',
        'admin_activity_logs' => 'admin_activity_logs',
        'email_logs' => 'email_logs'
    ];
    
    $table = $databaseLogs[$type] ?? null;
    if (!$table) {
        http_response_code(404);
        return;
    }
    
    try {
        $stmt = $pdo->query("SELECT * FROM $table ORDER BY id DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d') . '.txt"');
        
        foreach ($rows as $row) {
            echo implode("\t", $row) . "\n";
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
}

function cleanupOldLogs($pdo) {
    $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
    $cleanup_results = [
        'database_logs' => [],
        'file_logs' => [],
        'cutoff_date' => $cutoffDate
    ];
    
    // Clean up database logs
    $databaseLogs = [
        'analytics_logs' => 'timestamp',
        'order_logs' => 'created_at', 
        'inventory_logs' => 'timestamp',
        'user_activity_logs' => 'timestamp',
        'error_logs' => 'timestamp',
        'admin_activity_logs' => 'timestamp',
        'email_logs' => 'sent_at'
    ];
    
    foreach ($databaseLogs as $table => $timestampField) {
        try {
            // Check if table exists
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                // Count old records before cleanup
                $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE $timestampField < ?");
                $countStmt->execute([$cutoffDate]);
                $oldCount = $countStmt->fetch()['count'];
                
                if ($oldCount > 0) {
                    // Delete old records
                    $deleteStmt = $pdo->prepare("DELETE FROM $table WHERE $timestampField < ?");
                    $deleteStmt->execute([$cutoffDate]);
                    
                    $cleanup_results['database_logs'][$table] = [
                        'deleted_records' => $oldCount,
                        'timestamp_field' => $timestampField
                    ];
                }
            }
        } catch (Exception $e) {
            $cleanup_results['database_logs'][$table] = [
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Clean up file logs (rotate/truncate old content)
    $fileLogPaths = [
        './monitor.log',
        './cron_test.log', 
        './php_server.log',
        './autostart.log',
        './server.log',
        './inventory_errors.log'
    ];
    
    foreach ($fileLogPaths as $logPath) {
        if (file_exists($logPath) && is_writable($logPath)) {
            try {
                $originalSize = filesize($logPath);
                $cleanedLines = cleanupFileLogContent($logPath, $cutoffDate);
                $newSize = file_exists($logPath) ? filesize($logPath) : 0;
                
                $cleanup_results['file_logs'][$logPath] = [
                    'original_size' => formatFileSize($originalSize),
                    'new_size' => formatFileSize($newSize),
                    'lines_kept' => $cleanedLines,
                    'space_freed' => formatFileSize($originalSize - $newSize)
                ];
            } catch (Exception $e) {
                $cleanup_results['file_logs'][$logPath] = [
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    return $cleanup_results;
}

function cleanupFileLogContent($logPath, $cutoffDate) {
    if (!file_exists($logPath) || !is_readable($logPath) || !is_writable($logPath)) {
        return 0;
    }
    
    $cutoffTimestamp = strtotime($cutoffDate);
    $tempFile = $logPath . '.tmp';
    $keptLines = 0;
    
    $inputHandle = fopen($logPath, 'r');
    $outputHandle = fopen($tempFile, 'w');
    
    if (!$inputHandle || !$outputHandle) {
        if ($inputHandle) fclose($inputHandle);
        if ($outputHandle) fclose($outputHandle);
        return 0;
    }
    
    while (($line = fgets($inputHandle)) !== false) {
        $lineTimestamp = extractTimestampFromLogLine($line);
        
        // Keep the line if we can't parse timestamp (safer) or if it's newer than cutoff
        if ($lineTimestamp === false || $lineTimestamp >= $cutoffTimestamp) {
            fwrite($outputHandle, $line);
            $keptLines++;
        }
    }
    
    fclose($inputHandle);
    fclose($outputHandle);
    
    // Replace original file with cleaned version
    if ($keptLines > 0) {
        rename($tempFile, $logPath);
    } else {
        // If no lines to keep, create empty file
        unlink($tempFile);
        file_put_contents($logPath, '');
    }
    
    return $keptLines;
}

function extractTimestampFromLogLine($line) {
    // Try different timestamp formats common in log files
    $patterns = [
        // [Mon Jun 30 21:08:36 2025] format
        '/\[([A-Z][a-z]{2} [A-Z][a-z]{2} \d{1,2} \d{2}:\d{2}:\d{2} \d{4})\]/',
        // 2024-12-30 14:35:22 format  
        '/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/',
        // 2024/12/30 14:35:22 format
        '/(\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2})/',
        // Dec 30 14:35:22 format
        '/([A-Z][a-z]{2} \d{1,2} \d{2}:\d{2}:\d{2})/',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $line, $matches)) {
            $timestamp = strtotime($matches[1]);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }
    }
    
    return false;
}

// formatFileSize function is available from includes/functions.php

function extractLogLevel($line) {
    if (preg_match('/\b(FATAL|CRITICAL|ERROR)\b/i', $line)) {
        return 'ERROR';
    } elseif (preg_match('/\b(WARN|WARNING)\b/i', $line)) {
        return 'WARNING';
    } elseif (preg_match('/\b(INFO|SUCCESS)\b/i', $line)) {
        return 'INFO';
    } elseif (preg_match('/\b(DEBUG)\b/i', $line)) {
        return 'DEBUG';
    }
    return 'INFO';
}
?> 