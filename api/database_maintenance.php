<?php
/**
 * Database Maintenance API
 * Manages database connection settings and credentials
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Security check
if (!isset($_SESSION)) {
    session_start();
}

// Admin authentication check
$isAdmin = false;
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $isAdmin = (strtolower($_SESSION['role']) === 'admin');
}

// Allow admin token for API access
if (!$isAdmin && isset($_GET['admin_token']) && $_GET['admin_token'] === 'whimsical_admin_2024') {
    $isAdmin = true;
}

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_config':
        getConfig();
        break;
    case 'test_connection':
        testConnection();
        break;
    case 'update_config':
        updateConfig();
        break;
    case 'get_connection_stats':
        getConnectionStats();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getConfig() {
    global $host, $db, $user, $pass;
    
    // Return current config (mask password)
    echo json_encode([
        'success' => true,
        'config' => [
            'host' => $host,
            'database' => $db,
            'username' => $user,
            'password_masked' => str_repeat('*', strlen($pass)),
            'environment' => $GLOBALS['isLocalhost'] ? 'local' : 'production',
            'dsn' => $GLOBALS['dsn']
        ]
    ]);
}

function testConnection() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $testHost = $input['host'] ?? '';
    $testDb = $input['database'] ?? '';
    $testUser = $input['username'] ?? '';
    $testPass = $input['password'] ?? '';
    $testSsl = $input['ssl_enabled'] ?? false;
    $testSslCert = $input['ssl_cert'] ?? '';
    
    if (empty($testHost) || empty($testDb) || empty($testUser)) {
        echo json_encode(['success' => false, 'message' => 'Host, database, and username are required']);
        return;
    }
    
    try {
        $testDsn = "mysql:host=$testHost;dbname=$testDb;charset=utf8mb4";
        $testOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10 // 10 second timeout
        ];
        
        // Add SSL options if enabled
        if ($testSsl && !empty($testSslCert)) {
            $testOptions[PDO::MYSQL_ATTR_SSL_CA] = $testSslCert;
            $testOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
        
        $testPdo = new PDO($testDsn, $testUser, $testPass, $testOptions);
        
        // Test basic query
        $stmt = $testPdo->query("SELECT VERSION() as version, DATABASE() as current_db");
        $info = $stmt->fetch();
        
        // Get table count
        $stmt = $testPdo->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = '$testDb'");
        $tableInfo = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'Connection successful',
            'info' => [
                'mysql_version' => $info['version'],
                'current_database' => $info['current_db'],
                'table_count' => $tableInfo['table_count'],
                'ssl_enabled' => $testSsl,
                'connection_time' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Connection failed: ' . $e->getMessage(),
            'error_code' => $e->getCode()
        ]);
    }
}

function updateConfig() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $newHost = $input['host'] ?? '';
    $newDb = $input['database'] ?? '';
    $newUser = $input['username'] ?? '';
    $newPass = $input['password'] ?? '';
    $environment = $input['environment'] ?? 'auto';
    $sslEnabled = $input['ssl_enabled'] ?? false;
    $sslCert = $input['ssl_cert'] ?? '';
    
    if (empty($newHost) || empty($newDb) || empty($newUser)) {
        echo json_encode(['success' => false, 'message' => 'Host, database, and username are required']);
        return;
    }
    
    // Read current config file
    $configPath = __DIR__ . '/config.php';
    if (!file_exists($configPath)) {
        echo json_encode(['success' => false, 'message' => 'Config file not found']);
        return;
    }
    
    $configContent = file_get_contents($configPath);
    
    // Update the config based on environment
    if ($environment === 'local' || $environment === 'both') {
        // Update local credentials
        $configContent = preg_replace(
            "/(\\\$host = ')([^']*)(';.*?\/\/ Local)/",
            "\$1$newHost\$3",
            $configContent
        );
        $configContent = preg_replace(
            "/(\\\$db   = ')([^']*)(';)/",
            "\$1$newDb\$3",
            $configContent
        );
        $configContent = preg_replace(
            "/(\\\$user = ')([^']*)(';)/",
            "\$1$newUser\$3",
            $configContent
        );
        if (!empty($newPass)) {
            $configContent = preg_replace(
                "/(\\\$pass = ')([^']*)(';)/",
                "\$1$newPass\$3",
                $configContent
            );
        }
    }
    
    if ($environment === 'production' || $environment === 'both') {
        // Update production credentials
        $configContent = preg_replace(
            "/(\\\$host = ')([^']*)(';.*?\/\/ Real IONOS)/",
            "\$1$newHost\$3",
            $configContent
        );
        $configContent = preg_replace(
            "/(\\\$db   = ')([^']*)(';.*?\/\/ Real IONOS)/",
            "\$1$newDb\$3",
            $configContent
        );
        $configContent = preg_replace(
            "/(\\\$user = ')([^']*)(';.*?\/\/ Real IONOS)/",
            "\$1$newUser\$3",
            $configContent
        );
        if (!empty($newPass)) {
            $configContent = preg_replace(
                "/(\\\$pass = ')([^']*)(';.*?\/\/ IONOS)/",
                "\$1$newPass\$3",
                $configContent
            );
        }
    }
    
    // Add SSL support if needed
    if ($sslEnabled && !empty($sslCert)) {
        // Add SSL options to the config
        $sslConfig = "\n// SSL Configuration\n";
        $sslConfig .= "if (\$sslEnabled) {\n";
        $sslConfig .= "    \$options[PDO::MYSQL_ATTR_SSL_CA] = '$sslCert';\n";
        $sslConfig .= "    \$options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;\n";
        $sslConfig .= "}\n";
        
        // Add before the dsn line
        $configContent = str_replace(
            '$dsn = "mysql:host=$host;dbname=$db;charset=$charset";',
            '$sslEnabled = ' . ($sslEnabled ? 'true' : 'false') . ";\n$sslConfig\n" . '$dsn = "mysql:host=$host;dbname=$db;charset=$charset";',
            $configContent
        );
    }
    
    // Create backup of current config
    $backupPath = __DIR__ . '/config_backup_' . date('Y-m-d_H-i-s') . '.php';
    copy($configPath, $backupPath);
    
    // Write updated config
    if (file_put_contents($configPath, $configContent) !== false) {
        echo json_encode([
            'success' => true,
            'message' => 'Database configuration updated successfully',
            'backup_created' => basename($backupPath)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to write config file']);
    }
}

function getConnectionStats() {
    try {
        $pdo = Database::getInstance();
        
        // Get current connections
        $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
        $connections = $stmt->fetch();
        
        // Get max connections
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'max_connections'");
        $maxConnections = $stmt->fetch();
        
        // Get database size
        global $db;
        $stmt = $pdo->query("
            SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS database_size_mb
            FROM information_schema.tables 
            WHERE table_schema = '$db'
        ");
        $sizeInfo = $stmt->fetch();
        
        // Get table count
        $stmt = $pdo->query("
            SELECT COUNT(*) as table_count 
            FROM information_schema.tables 
            WHERE table_schema = '$db'
        ");
        $tableCount = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'current_connections' => $connections['Value'],
                'max_connections' => $maxConnections['Value'],
                'database_size_mb' => $sizeInfo['database_size_mb'],
                'table_count' => $tableCount['table_count'],
                'last_updated' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get connection stats: ' . $e->getMessage()
        ]);
    }
}
?> 