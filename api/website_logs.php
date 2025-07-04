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
    
    // Database-based logs only
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
            'timestamp_field' => 'created_at',
            'message_field' => 'message'
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
            
            // Get last entry timestamp
            $lastEntryTime = null;
            $timestampField = $info['timestamp_field'];
            $lastStmt = $pdo->query("SELECT MAX($timestampField) as last_entry FROM $tableName");
            $lastResult = $lastStmt->fetch();
            if ($lastResult && $lastResult['last_entry']) {
                $lastEntryTime = $lastResult['last_entry'];
            }
        } catch (Exception $e) {
            $count = 0;
            $lastEntryTime = null;
        }
        
        $logs[] = [
            'type' => $type,
            'name' => $info['name'],
            'description' => $info['description'],
            'category' => $info['category'],
            'entries' => $count,
            'table' => $tableName,
            'timestamp_field' => $info['timestamp_field'],
            'message_field' => $info['message_field'],
            'last_entry' => $lastEntryTime,
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
            
        case 'error_logs':
            $sql = "CREATE TABLE $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                error_type VARCHAR(100) NOT NULL DEFAULT 'ERROR',
                message TEXT NOT NULL,
                context_data JSON,
                user_id INT NULL,
                file_path VARCHAR(500),
                line_number INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_created_at (created_at),
                INDEX idx_error_type (error_type),
                INDEX idx_file_path (file_path)
            )";
            break;
            
        case 'user_activity_logs':
            $sql = "CREATE TABLE $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                session_id VARCHAR(255),
                activity_type VARCHAR(100) NOT NULL,
                activity_description TEXT,
                target_type VARCHAR(100),
                target_id INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_timestamp (timestamp),
                INDEX idx_activity_type (activity_type)
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
            
        case 'order_logs':
            $sql = "CREATE TABLE $tableName (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id VARCHAR(50) NOT NULL,
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
                item_sku VARCHAR(50) NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                change_description TEXT,
                old_quantity INT,
                new_quantity INT,
                old_price DECIMAL(10,2),
                new_price DECIMAL(10,2),
                user_id INT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_item_sku (item_sku),
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
            error_log("Failed to create log table $tableName: " . $e->getMessage());
        }
    }
}

function getLogContent($pdo) {
    $type = $_GET['type'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;
    
    if (empty($type)) {
        echo json_encode(['success' => false, 'error' => 'Log type required']);
        return;
    }
    
    getDatabaseLogContent($pdo, $type, $page, $limit, $offset);
}

function getDatabaseLogContent($pdo, $type, $page, $limit, $offset) {
    // Map log types to table configurations
    $logConfigs = [
        'analytics_logs' => [
            'table' => 'analytics_logs',
            'timestamp_field' => 'timestamp',
            'fields' => 'id, user_id, session_id, page_url, event_type, event_data, ip_address, timestamp'
        ],
        'error_logs' => [
            'table' => 'error_logs',
            'timestamp_field' => 'created_at',
            'fields' => 'id, error_type, message, context_data, user_id, file_path, line_number, ip_address, created_at'
        ],
        'user_activity_logs' => [
            'table' => 'user_activity_logs',
            'timestamp_field' => 'timestamp',
            'fields' => 'id, user_id, session_id, activity_type, activity_description, target_type, target_id, ip_address, timestamp'
        ],
        'admin_activity_logs' => [
            'table' => 'admin_activity_logs',
            'timestamp_field' => 'timestamp',
            'fields' => 'id, admin_user_id, action_type, action_description, target_type, target_id, ip_address, timestamp'
        ],
        'order_logs' => [
            'table' => 'order_logs',
            'timestamp_field' => 'created_at',
            'fields' => 'id, order_id, user_id, action, log_message, previous_status, new_status, admin_user_id, created_at'
        ],
        'inventory_logs' => [
            'table' => 'inventory_logs',
            'timestamp_field' => 'timestamp',
            'fields' => 'id, item_sku, action_type, change_description, old_quantity, new_quantity, old_price, new_price, user_id, timestamp'
        ],
        'email_logs' => [
            'table' => 'email_logs',
            'timestamp_field' => 'sent_at',
            'fields' => 'id, to_email, from_email, email_subject, email_type, status, error_message, sent_at'
        ]
    ];
    
    if (!isset($logConfigs[$type])) {
        echo json_encode(['success' => false, 'error' => 'Unknown log type']);
        return;
    }
    
    $config = $logConfigs[$type];
    $table = $config['table'];
    $timestampField = $config['timestamp_field'];
    $fields = $config['fields'];
    
    try {
        // Get total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM $table");
        $countStmt->execute();
        $totalCount = $countStmt->fetch()['total'];
        
        // Get log entries
        $stmt = $pdo->prepare("
            SELECT $fields 
            FROM $table 
            ORDER BY $timestampField DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'type' => $type,
            'entries' => $entries,
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalCount / $limit)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function searchLogs($pdo) {
    $query = $_GET['query'] ?? '';
    $type = $_GET['type'] ?? '';
    
    if (empty($query)) {
        echo json_encode(['success' => false, 'error' => 'Search query required']);
        return;
    }
    
    searchDatabaseLogs($pdo, $query, $type);
}

function searchDatabaseLogs($pdo, $query, $type = '') {
    $results = [];
    
    // Define searchable tables and their key fields
    $searchTables = [
        'analytics_logs' => ['page_url', 'event_type', 'event_data'],
        'error_logs' => ['error_type', 'message', 'file_path'],
        'user_activity_logs' => ['activity_type', 'activity_description'],
        'admin_activity_logs' => ['action_type', 'action_description'],
        'order_logs' => ['order_id', 'action', 'log_message'],
        'inventory_logs' => ['item_sku', 'change_description'],
        'email_logs' => ['to_email', 'email_subject', 'email_type']
    ];
    
    // If specific type requested, only search that table
    if (!empty($type) && isset($searchTables[$type])) {
        $searchTables = [$type => $searchTables[$type]];
    }
    
    foreach ($searchTables as $table => $fields) {
        try {
            $whereConditions = [];
            $params = [];
            
            foreach ($fields as $field) {
                $whereConditions[] = "$field LIKE ?";
                $params[] = "%$query%";
            }
            
            // Get timestamp field for ordering
            $timestampField = ($table === 'error_logs') ? 'created_at' : 
                             (($table === 'order_logs' || $table === 'email_logs') ? 
                              ($table === 'email_logs' ? 'sent_at' : 'created_at') : 'timestamp');
            
            $sql = "SELECT *, '$table' as source_table FROM $table WHERE " . implode(' OR ', $whereConditions) . " ORDER BY $timestampField DESC LIMIT 20";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tableResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = array_merge($results, $tableResults);
            
        } catch (Exception $e) {
            // Table might not exist, continue with others
            continue;
        }
    }
    
    echo json_encode(['success' => true, 'results' => $results, 'query' => $query]);
}

function clearLog($pdo) {
    $type = $_POST['type'] ?? '';
    
    if (empty($type)) {
        echo json_encode(['success' => false, 'error' => 'Log type required']);
        return;
    }
    
    clearDatabaseLog($pdo, $type);
}

function clearDatabaseLog($pdo, $type) {
    $validTables = [
        'analytics_logs', 'error_logs', 'user_activity_logs', 
        'admin_activity_logs', 'order_logs', 'inventory_logs', 'email_logs'
    ];
    
    if (!in_array($type, $validTables)) {
        echo json_encode(['success' => false, 'error' => 'Invalid log type']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM $type");
        $result = $stmt->execute();
        
        if ($result) {
            // Reset auto-increment
            $pdo->exec("ALTER TABLE $type AUTO_INCREMENT = 1");
            echo json_encode(['success' => true, 'message' => ucfirst(str_replace('_', ' ', $type)) . ' cleared successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to clear log']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function downloadLog($pdo) {
    $type = $_GET['type'] ?? '';
    
    if (empty($type)) {
        echo json_encode(['success' => false, 'error' => 'Log type required']);
        return;
    }
    
    downloadDatabaseLog($pdo, $type);
}

function downloadDatabaseLog($pdo, $type) {
    $validTables = [
        'analytics_logs', 'error_logs', 'user_activity_logs', 
        'admin_activity_logs', 'order_logs', 'inventory_logs', 'email_logs'
    ];
    
    if (!in_array($type, $validTables)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid log type']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM $type ORDER BY id DESC");
        $stmt->execute();
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Set headers for file download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output CSV
        $output = fopen('php://output', 'w');
        
        if (!empty($entries)) {
            // Write header row
            fputcsv($output, array_keys($entries[0]));
            
            // Write data rows
            foreach ($entries as $entry) {
                fputcsv($output, $entry);
            }
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function cleanupOldLogs($pdo) {
    $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
    $results = [];
    
    $logTables = [
        'analytics_logs' => 'timestamp',
        'error_logs' => 'created_at',
        'user_activity_logs' => 'timestamp',
        'admin_activity_logs' => 'timestamp',
        'order_logs' => 'created_at',
        'inventory_logs' => 'timestamp',
        'email_logs' => 'sent_at'
    ];
    
    foreach ($logTables as $table => $timestampField) {
        try {
            // Count old entries first
            $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE $timestampField < ?");
            $countStmt->execute([$cutoffDate]);
            $oldCount = $countStmt->fetch()['count'];
            
            if ($oldCount > 0) {
                // Delete old entries
                $deleteStmt = $pdo->prepare("DELETE FROM $table WHERE $timestampField < ?");
                $deleted = $deleteStmt->execute([$cutoffDate]);
                
                if ($deleted) {
                    $results[$table] = [
                        'deleted' => $oldCount,
                        'status' => 'success'
                    ];
                } else {
                    $results[$table] = [
                        'deleted' => 0,
                        'status' => 'failed',
                        'error' => 'Delete operation failed'
                    ];
                }
            } else {
                $results[$table] = [
                    'deleted' => 0,
                    'status' => 'no_old_entries'
                ];
            }
        } catch (Exception $e) {
            $results[$table] = [
                'deleted' => 0,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $results;
}
?> 