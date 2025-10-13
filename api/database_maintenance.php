<?php
/**
 * Database Maintenance API
 * Manages database connection settings and credentials
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Optional admin token bypass for automated deploys
// If a valid WF_ADMIN_TOKEN is provided, allow access without session auth
function wf_is_token_valid(): bool {
    $provided = $_GET['admin_token'] ?? $_POST['admin_token'] ?? '';
    if ($provided === '') {
        return false;
    }
    $expected = getenv('WF_ADMIN_TOKEN') ?: '';
    // Also support constant override if defined in config
    if (defined('WF_ADMIN_TOKEN') && WF_ADMIN_TOKEN) {
        $expected = WF_ADMIN_TOKEN;
    }
    return $expected !== '' && hash_equals($expected, $provided);
}

// Require admin unless a valid admin token is supplied
if (!wf_is_token_valid()) {
    AuthHelper::requireAdmin();
}

header('Content-Type: application/json');

// Get action from JSON body or request parameters
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? '';

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

        // New maintenance tool actions
    case 'optimize_tables':
        optimizeTables();
        break;
    case 'analyze_indexes':
        analyzeIndexes();
        break;
    case 'cleanup_database':
        cleanupDatabase();
        break;
    case 'repair_tables':
        repairTables();
        break;
    case 'analyze_size':
        analyzeDatabaseSize();
        break;
    case 'performance_monitor':
        performanceMonitor();
        break;
    case 'check_foreign_keys':
        checkForeignKeys();
        break;
    case 'get_schema':
        getDatabaseSchema();
        break;
    case 'export_tables':
        exportTables();
        break;

        // Database restore actions
    case 'list_backups':
        listBackups();
        break;
    case 'create_backup':
        createBackup();
        break;
    case 'drop_all_tables':
        dropAllTables();
        break;
    case 'restore_database':
        restoreDatabase();
        break;
    case 'initialize_database':
        initializeDatabase();
        break;

        // Import actions
    case 'import_sql':
        importSQL();
        break;
    case 'import_csv':
        importCSV();
        break;
    case 'import_json':
        importJSON();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getConfig()
{
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

function testConnection()
{
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
        // Use centralized helper with consistent defaults; override timeout and add SSL if requested
        $testOptions = [
            PDO::ATTR_TIMEOUT => 10
        ];

        // Add SSL options if enabled
        if ($testSsl && !empty($testSslCert)) {
            $testOptions[PDO::MYSQL_ATTR_SSL_CA] = $testSslCert;
            $testOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        $testPdo = Database::createConnection(
            $testHost,
            $testDb,
            $testUser,
            $testPass,
            3306,
            null,
            $testOptions
        );

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

function updateConfig()
{
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

function getConnectionStats()
{
    try {
        // Ensure connection initialized
        Database::getInstance();

        // Get current connections and max connections
        $connections = Database::queryOne("SHOW STATUS LIKE 'Threads_connected'") ?? [];
        $maxConnections = Database::queryOne("SHOW VARIABLES LIKE 'max_connections'") ?? [];

        // Get database size and table count
        global $db;
        $sizeInfo = Database::queryOne(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS database_size_mb
             FROM information_schema.tables 
             WHERE table_schema = '$db'"
        ) ?? [];

        $tableCount = Database::queryOne(
            "SELECT COUNT(*) as table_count 
             FROM information_schema.tables 
             WHERE table_schema = '$db'"
        ) ?? [];

        echo json_encode([
            'success' => true,
            'stats' => [
                'current_connections' => $connections['Value'] ?? null,
                'max_connections' => $maxConnections['Value'] ?? null,
                'database_size_mb' => $sizeInfo['database_size_mb'] ?? null,
                'table_count' => $tableCount['table_count'] ?? null,
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

// ======================================
// NEW MAINTENANCE TOOL FUNCTIONS
// ======================================

function optimizeTables()
{
    try {
        Database::getInstance();
        global $db;

        // Get all tables in the database
        $tables = array_column(Database::queryAll("SHOW TABLES FROM `$db`"), 0);

        $optimizedCount = 0;
        $details = [];

        foreach ($tables as $table) {
            $result = Database::queryOne("OPTIMIZE TABLE `$table`") ?? [];
            $msg = $result['Msg_text'] ?? ($result['Message_text'] ?? 'OK');
            $details[] = "$table: " . $msg;
            $optimizedCount++;
        }

        echo json_encode([
            'success' => true,
            'tables_optimized' => $optimizedCount,
            'details' => implode('; ', $details)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Table optimization failed: ' . $e->getMessage()
        ]);
    }
}

function analyzeIndexes()
{
    try {
        $indexes = Database::queryAll("
            SELECT 
                table_name,
                index_name,
                cardinality,
                index_type
            FROM information_schema.statistics 
            WHERE table_schema = '$db'
            ORDER BY table_name, index_name
        ");

        $indexCount = count($indexes);
        $recommendations = [];

        // Simple analysis - check for tables without indexes
        $rowsNoIdx = Database::queryAll("
            SELECT table_name 
            FROM information_schema.tables 
            WHERE table_schema = '$db' 
            AND table_name NOT IN (
                SELECT DISTINCT table_name 
                FROM information_schema.statistics 
                WHERE table_schema = '$db'
            )
        ");
        $noIndexTables = array_map(function ($r) { return $r['table_name'] ?? null; }, $rowsNoIdx);
        $noIndexTables = array_values(array_filter($noIndexTables, function ($v) { return $v !== null; }));

        if (!empty($noIndexTables)) {
            $recommendations[] = "Tables without indexes: " . implode(', ', $noIndexTables);
        }

        echo json_encode([
            'success' => true,
            'indexes_analyzed' => $indexCount,
            'recommendations' => implode('; ', $recommendations) ?: 'All tables have appropriate indexes'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Index analysis failed: ' . $e->getMessage()
        ]);
    }
}

function cleanupDatabase()
{
    try {
        $orphanedRecords = 0;
        $tempFiles = 0;

        // Example cleanup - remove orphaned order items
        $affected = Database::execute("
            DELETE oi FROM order_items oi 
            LEFT JOIN orders o ON oi.order_id = o.id 
            WHERE o.id IS NULL
        ");
        $orphanedRecords += ($affected > 0 ? $affected : 0);

        // Example cleanup - remove orphaned item images
        $affected2 = Database::execute("
            DELETE ii FROM item_images ii 
            LEFT JOIN items i ON ii.item_sku = i.sku 
            WHERE i.sku IS NULL
        ");
        $orphanedRecords += ($affected2 > 0 ? $affected2 : 0);

        echo json_encode([
            'success' => true,
            'orphaned_records' => $orphanedRecords,
            'temp_files' => $tempFiles
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database cleanup failed: ' . $e->getMessage()
        ]);
    }
}

function analyzeDatabaseSize()
{
    try {
        $sizeInfo = Database::queryOne("
            SELECT 
                ROUND(SUM(data_length) / 1024 / 1024, 2) AS data_size_mb,
                ROUND(SUM(index_length) / 1024 / 1024, 2) AS index_size_mb,
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS total_size_mb
            FROM information_schema.tables 
            WHERE table_schema = '$db'
        ");

        // Get largest table
        $largestTable = Database::queryOne("
            SELECT 
                table_name,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = '$db'
            ORDER BY (data_length + index_length) DESC
            LIMIT 1
        ");

        echo json_encode([
            'success' => true,
            'total_size' => $sizeInfo['total_size_mb'] . ' MB',
            'data_size' => $sizeInfo['data_size_mb'] . ' MB',
            'index_size' => $sizeInfo['index_size_mb'] . ' MB',
            'largest_table' => $largestTable['table_name'] . ' (' . $largestTable['size_mb'] . ' MB)'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database size analysis failed: ' . $e->getMessage()
        ]);
    }
}

function performanceMonitor()
{
    try {
        $pdo = Database::getInstance();

        // Get connection count
        $connections = Database::queryOne("SHOW STATUS LIKE 'Threads_connected'") ?? [];

        // Get slow query log status
        $slowLogStatus = Database::queryOne("SHOW VARIABLES LIKE 'slow_query_log'") ?? [];

        // Get uptime
        $uptime = Database::queryOne("SHOW STATUS LIKE 'Uptime'") ?? [];

        // Get query cache hit rate
        $hits = Database::queryOne("SHOW STATUS LIKE 'Qcache_hits'") ?? [];
        $selects = Database::queryOne("SHOW STATUS LIKE 'Com_select'") ?? [];

        $hitRate = 'N/A';
        if ($hits && $selects && ($hits['Value'] + $selects['Value']) > 0) {
            $hitRate = round(($hits['Value'] / ($hits['Value'] + $selects['Value'])) * 100, 2) . '%';
        }

        echo json_encode([
            'success' => true,
            'connections' => $connections['Value'] ?? null,
            'slow_queries' => (($slowLogStatus['Value'] ?? 'OFF') === 'ON') ? 'Enabled' : 'Disabled',
            'cache_hit_rate' => $hitRate,
            'avg_query_time' => 'N/A' // Would need more complex calculation
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Performance monitoring failed: ' . $e->getMessage()
        ]);
    }
}

function checkForeignKeys()
{
    try {
        $pdo = Database::getInstance();
        global $db;

        // Get all foreign key constraints
        $stmt = $pdo->query("
            SELECT 
                table_name,
                constraint_name,
                column_name,
                referenced_table_name,
                referenced_column_name
            FROM information_schema.key_column_usage 
            WHERE table_schema = '$db' 
            AND referenced_table_name IS NOT NULL
        ");
        $foreignKeys = $stmt->fetchAll();

        $keysChecked = count($foreignKeys);
        $issuesFound = 0;

        // Check each foreign key for orphaned records
        foreach ($foreignKeys as $fk) {
            // Check if all required keys exist to avoid PHP warnings
            if (!isset($fk['table_name']) || !isset($fk['referenced_table_name']) ||
                !isset($fk['column_name']) || !isset($fk['referenced_column_name'])) {
                continue; // Skip this foreign key if required data is missing
            }

            try {
                $sql = "
                    SELECT COUNT(*) as orphaned_count
                    FROM `{$fk['table_name']}` t1
                    LEFT JOIN `{$fk['referenced_table_name']}` t2 
                    ON t1.`{$fk['column_name']}` = t2.`{$fk['referenced_column_name']}`
                    WHERE t1.`{$fk['column_name']}` IS NOT NULL 
                    AND t2.`{$fk['referenced_column_name']}` IS NULL
                ";

                $stmt = $pdo->query($sql);
                $result = $stmt->fetch();

                if ($result && isset($result['orphaned_count']) && $result['orphaned_count'] > 0) {
                    $issuesFound += $result['orphaned_count'];
                }
            } catch (Exception $e) {
                // Skip this foreign key check if there's an error
                continue;
            }
        }

        echo json_encode([
            'success' => true,
            'keys_checked' => $keysChecked,
            'issues_found' => $issuesFound
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Foreign key check failed: ' . $e->getMessage()
        ]);
    }
}

function getDatabaseSchema()
{
    try {
        $pdo = Database::getInstance();
        global $db;

        // Get table information with explicit column aliases to ensure case consistency
        $stmt = $pdo->query("
            SELECT 
                table_name AS table_name,
                table_rows AS table_rows,
                ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = '$db'
            ORDER BY table_name
        ");
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $schemaInfo = [];
        foreach ($tables as $table) {
            // Handle case sensitivity and null values
            $tableName = $table['table_name'] ?? $table['TABLE_NAME'] ?? 'Unknown';
            $tableRows = $table['table_rows'] ?? $table['TABLE_ROWS'] ?? 0;
            $tableSize = $table['size_mb'] ?? 0;

            // Skip if table name is invalid
            if ($tableName === 'Unknown' || empty($tableName)) {
                continue;
            }

            // Get column information for each table
            $stmt = $pdo->query("
                SELECT column_name 
                FROM information_schema.columns 
                WHERE table_schema = '$db' 
                AND table_name = '$tableName'
                ORDER BY ordinal_position
            ");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $schemaInfo[] = [
                'name' => $tableName,
                'rows' => $tableRows !== null ? $tableRows : 0,
                'size' => $tableSize . ' MB',
                'columns' => $columns ?: []
            ];
        }

        echo json_encode([
            'success' => true,
            'tables' => $schemaInfo
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Schema retrieval failed: ' . $e->getMessage()
        ]);
    }
}

function exportTables()
{
    try {
        $tables = $_GET['tables'] ?? '';
        if (empty($tables)) {
            throw new Exception('No tables specified for export');
        }

        $tableList = explode(',', $tables);
        $pdo = Database::getInstance();
        global $db;

        // Set headers for file download
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="database_export_' . date('Y-m-d_H-i-s') . '.sql"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "- Database Export\n";
        echo "- Generated: " . date('Y-m-d H:i:s') . "\n";
        echo "- Database: $db\n";
        echo "- Tables: " . implode(', ', $tableList) . "\n\n";

        foreach ($tableList as $table) {
            $table = trim($table);

            // Export table structure
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $createTable = $stmt->fetch();

            echo "- Table structure for `$table`\n";
            echo "DROP TABLE IF EXISTS `$table`;\n";
            echo $createTable['Create Table'] . ";\n\n";

            // Export table data
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll();

            if (!empty($rows)) {
                echo "- Data for table `$table`\n";

                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';

                echo "INSERT INTO `$table` ($columnList) VALUES\n";

                $values = [];
                foreach ($rows as $row) {
                    $escapedRow = array_map(function ($value) use ($pdo) {
                        return $value === null ? 'NULL' : $pdo->quote($value);
                    }, $row);
                    $values[] = '(' . implode(', ', $escapedRow) . ')';
                }

                echo implode(",\n", $values) . ";\n\n";
            }
        }

        exit; // End output for download

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Export failed: ' . $e->getMessage()
        ]);
    }
}

// List available backup files on the server
function listBackups()
{
    try {
        $backupDir = __DIR__ . '/../backups/';
        $backups = [];

        // Create backup directory if it doesn't exist
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $files = glob($backupDir . '*.sql');

        foreach ($files as $file) {
            $filename = basename($file);
            $size = filesize($file);
            $created = date('Y-m-d H:i:s', filemtime($file));
            $age = time() - filemtime($file);

            // Format age
            if ($age < 3600) {
                $ageFormatted = round($age / 60) . ' minutes ago';
            } elseif ($age < 86400) {
                $ageFormatted = round($age / 3600) . ' hours ago';
            } else {
                $ageFormatted = round($age / 86400) . ' days ago';
            }

            $backups[] = [
                'filename' => $filename,
                'path' => $file,
                'size' => formatBytes($size),
                'created' => $created,
                'age' => $ageFormatted,
                'timestamp' => filemtime($file)
            ];
        }

        // Sort by newest first
        usort($backups, function ($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        echo json_encode([
            'success' => true,
            'backups' => $backups
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to list backups: ' . $e->getMessage()
        ]);
    }
}

// Create a backup of the current database
function createBackup()
{
    try {
        $pdo = Database::getInstance();
        global $db;

        $backupDir = __DIR__ . '/../backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = "backup_" . date('Y-m-d_H-i-s') . ".sql";
        $filePath = $backupDir . $filename;

        // Get all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $backup = "- Database Backup\n";
        $backup .= "- Generated: " . date('Y-m-d H:i:s') . "\n";
        $backup .= "- Database: $db\n\n";

        foreach ($tables as $table) {
            // Get table structure
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $createTable = $stmt->fetch();

            $backup .= "- Table structure for `$table`\n";
            $backup .= "DROP TABLE IF EXISTS `$table`;\n";
            $backup .= $createTable['Create Table'] . ";\n\n";

            // Get table data
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll();

            if (!empty($rows)) {
                $backup .= "- Data for table `$table`\n";

                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';

                $backup .= "INSERT INTO `$table` ($columnList) VALUES\n";

                $values = [];
                foreach ($rows as $row) {
                    $escapedRow = array_map(function ($value) use ($pdo) {
                        return $value === null ? 'NULL' : $pdo->quote($value);
                    }, $row);
                    $values[] = '(' . implode(', ', $escapedRow) . ')';
                }

                $backup .= implode(",\n", $values) . ";\n\n";
            }
        }

        if (file_put_contents($filePath, $backup) === false) {
            throw new Exception('Failed to write backup file');
        }

        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'filepath' => $filePath,
            'size' => formatBytes(filesize($filePath)),
            'tables' => count($tables)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Backup creation failed: ' . $e->getMessage()
        ]);
    }
}

// Drop all tables in the database
function dropAllTables()
{
    try {
        $pdo = Database::getInstance();

        // Disable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Get all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $droppedTables = [];

        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            $droppedTables[] = $table;
        }

        // Re-enable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        echo json_encode([
            'success' => true,
            'tables_dropped' => count($droppedTables),
            'tables' => $droppedTables
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to drop tables: ' . $e->getMessage()
        ]);
    }
}

// Restore database from backup file
function restoreDatabase()
{
    try {
        $pdo = Database::getInstance();
        $ignoreErrors = isset($_POST['ignore_errors']) && $_POST['ignore_errors'] === '1';
        $sqlContent = '';
        $filePath = '';
        $isGzip = false;

        // Handle uploaded file
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['backup_file']['tmp_name'];

            // Validate file type
            $filename = $_FILES['backup_file']['name'];
            if (!preg_match('/\.(sql|txt|sql\.gz)$/i', $filename)) {
                throw new Exception('Invalid file type. Only .sql, .txt, or .sql.gz files are allowed.');
            }

            // Prefer streaming from file path to avoid loading large files into memory
            $filePath = $uploadedFile;
            $isGzip = (bool)preg_match('/\.gz$/i', $filename);

        } elseif (isset($_POST['server_backup_path'])) {
            // Handle server backup file (allow relative paths under backups/ or api/uploads/)
            $relPath = trim($_POST['server_backup_path']);
            // Resolve to absolute: prefer paths relative to api/ directory
            $candidatePaths = [];
            if ($relPath !== '') {
                // If absolute given, use as-is; else try under api/ and repo root
                if ($relPath[0] === '/') {
                    $candidatePaths[] = $relPath;
                } else {
                    $candidatePaths[] = __DIR__ . '/' . $relPath; // e.g., api/uploads/foo.sql.gz
                    $candidatePaths[] = dirname(__DIR__) . '/' . $relPath; // e.g., backups/foo.sql.gz
                }
            }

            $resolved = '';
            foreach ($candidatePaths as $cand) {
                $rp = realpath($cand);
                if ($rp && is_file($rp)) {
                    $resolved = $rp;
                    break;
                }
            }
            if ($resolved === '') {
                throw new Exception('Backup file not found');
            }

            // Security: restrict to backups/ or api/uploads/
            $backupDir = realpath(__DIR__ . '/../backups/');
            $uploadsDir = realpath(__DIR__ . '/uploads/');
            $apiDir = realpath(__DIR__);
            $requestedPath = realpath($resolved);
            $allowed = false;
            if ($backupDir && strpos($requestedPath, $backupDir) === 0) {
                $allowed = true;
            } elseif ($uploadsDir && strpos($requestedPath, $uploadsDir) === 0) {
                $allowed = true;
            } elseif ($apiDir && strpos($requestedPath, $apiDir) === 0 && strpos($requestedPath, DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR) !== false) {
                // uploads directory might not resolve separately; allow any path under api/ containing /uploads/
                $allowed = true;
            }
            if (!$allowed) {
                throw new Exception('Invalid backup file path');
            }

            $filePath = $resolved;
            $isGzip = (bool)preg_match('/\.gz$/i', $filePath);

        } else {
            throw new Exception('No backup file provided');
        }

        // If we are not streaming from a file, ensure we have inline SQL content
        if ($filePath === '' && empty($sqlContent)) {
            throw new Exception('Backup file is empty or could not be read');
        }

        $startTime = microtime(true);

        // Disable foreign key checks during restore
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("SET SQL_MODE = ''");

        // Execute SQL either from in-memory content or by streaming from file
        $executedStatements = 0;
        $errors = [];
        $tablesRestored = 0;
        $recordsRestored = 0;

        if ($filePath !== '') {
            // Stream read (supports .gz)
            $handle = $isGzip ? @gzopen($filePath, 'rb') : @fopen($filePath, 'rb');
            if (!$handle) {
                throw new Exception('Unable to open backup file for reading');
            }
            $buffer = '';
            while (!($isGzip ? gzeof($handle) : feof($handle))) {
                $line = $isGzip ? gzgets($handle) : fgets($handle);
                if ($line === false) { break; }
                $trim = ltrim($line);
                if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '/*')) {
                    continue;
                }
                $buffer .= $line;
                // naive statement boundary: semicolon at end of line
                if (preg_match('/;\s*$/', rtrim($buffer))) {
                    $stmt = trim($buffer);
                    if ($stmt !== '' && !str_starts_with($stmt, '--') && !str_starts_with($stmt, '/*')) {
                        // drop trailing semicolon for exec
                        if (substr($stmt, -1) === ';') { $stmt = substr($stmt, 0, -1); }
                        try {
                            $result = $pdo->exec($stmt);
                            $executedStatements++;
                            if (stripos($stmt, 'CREATE TABLE') !== false) {
                                $tablesRestored++;
                            } elseif (stripos($stmt, 'INSERT INTO') !== false) {
                                $recordsRestored += $result ?: 0;
                            }
                        } catch (PDOException $e) {
                            $err = 'Error in statement: ' . substr($stmt, 0, 100) . '... - ' . $e->getMessage();
                            $errors[] = $err;
                            if (!$ignoreErrors) { throw new Exception('SQL execution failed: ' . $err); }
                        }
                    }
                    $buffer = '';
                }
            }
            if ($isGzip) { @gzclose($handle); } else { @fclose($handle); }
        } else {
            // Fallback: execute from provided inline content
            $statements = preg_split('/;\s*\n/', $sqlContent);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement) || substr($statement, 0, 2) === '-') { continue; }
                try {
                    $result = $pdo->exec($statement);
                    $executedStatements++;
                    if (stripos($statement, 'CREATE TABLE') !== false) {
                        $tablesRestored++;
                    } elseif (stripos($statement, 'INSERT INTO') !== false) {
                        $recordsRestored += $result ?: 0;
                    }
                } catch (PDOException $e) {
                    $error = 'Error in statement: ' . substr($statement, 0, 100) . '... - ' . $e->getMessage();
                    $errors[] = $error;
                    if (!$ignoreErrors) { throw new Exception('SQL execution failed: ' . $error); }
                }
            }
        }

        // Re-enable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        $executionTime = round((microtime(true) - $startTime), 2) . ' seconds';

        $response = [
            'success' => true,
            'tables_restored' => $tablesRestored,
            'records_restored' => $recordsRestored,
            'statements_executed' => $executedStatements,
            'execution_time' => $executionTime
        ];

        if (!empty($errors)) {
            $response['warnings'] = count($errors) . ' warnings/errors encountered';
            $response['error_details'] = $errors;
        }

        echo json_encode($response);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database restore failed: ' . $e->getMessage()
        ]);
    }
}

// Helper function to format file sizes
function formatBytes($size)
{
    if ($size == 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($size, 1024);
    $unit = floor($base);

    return round(pow(1024, $base - $unit), 2) . ' ' . $units[$unit];
}

// Initialize database with all necessary tables and default data
function initializeDatabase()
{
    try {
        $startTime = microtime(true);
        $pdo = Database::getInstance();

        $tablesCreated = 0;
        $tablesSkipped = 0;
        $defaultRecords = 0;
        $details = [];
        $warnings = [];

        // Define all necessary tables with their SQL
        $tables = [
            'items' => "
                CREATE TABLE IF NOT EXISTS `items` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `sku` varchar(50) NOT NULL,
                    `name` varchar(255) NOT NULL,
                    `description` text,
                    `price` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `stockLevel` int(11) NOT NULL DEFAULT '0',
                    `category` varchar(100) DEFAULT NULL,
                    `subcategory` varchar(100) DEFAULT NULL,
                    `status` enum('active','inactive','discontinued') DEFAULT 'active',
                    `image_url` varchar(500) DEFAULT NULL,
                    `weight` decimal(8,2) DEFAULT NULL,
                    `dimensions` varchar(100) DEFAULT NULL,
                    `tags` text,
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `sku` (`sku`),
                    KEY `category` (`category`),
                    KEY `status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",

            'customers' => "
                CREATE TABLE IF NOT EXISTS `customers` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `email` varchar(255) NOT NULL,
                    `firstName` varchar(100) NOT NULL,
                    `lastName` varchar(100) NOT NULL,
                    `phone` varchar(20) DEFAULT NULL,
                    `address` text,
                    `city` varchar(100) DEFAULT NULL,
                    `state` varchar(50) DEFAULT NULL,
                    `zipCode` varchar(20) DEFAULT NULL,
                    `country` varchar(50) DEFAULT 'US',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",

            'orders' => "
                CREATE TABLE IF NOT EXISTS `orders` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `orderNumber` varchar(50) NOT NULL,
                    `customerId` int(11) NOT NULL,
                    `customerEmail` varchar(255) NOT NULL,
                    `customerName` varchar(255) NOT NULL,
                    `customerPhone` varchar(20) DEFAULT NULL,
                    `shippingAddress` text NOT NULL,
                    `billingAddress` text,
                    `totalAmount` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `taxAmount` decimal(10,2) DEFAULT '0.00',
                    `shippingAmount` decimal(10,2) DEFAULT '0.00',
                    `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
                    `paymentStatus` enum('pending','paid','failed','refunded') DEFAULT 'pending',
                    `paymentMethod` varchar(50) DEFAULT NULL,
                    `shippingMethod` varchar(100) DEFAULT NULL,
                    `notes` text,
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `orderNumber` (`orderNumber`),
                    KEY `customerId` (`customerId`),
                    KEY `status` (`status`),
                    KEY `paymentStatus` (`paymentStatus`),
                    FOREIGN KEY (`customerId`) REFERENCES `customers` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",

            'order_items' => "
                CREATE TABLE IF NOT EXISTS `order_items` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `orderId` int(11) NOT NULL,
                    `sku` varchar(50) NOT NULL,
                    `itemName` varchar(255) NOT NULL,
                    `quantity` int(11) NOT NULL DEFAULT '1',
                    `unitPrice` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `totalPrice` decimal(10,2) NOT NULL DEFAULT '0.00',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `orderId` (`orderId`),
                    KEY `sku` (`sku`),
                    FOREIGN KEY (`orderId`) REFERENCES `orders` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",

            'admin_users' => "
                CREATE TABLE IF NOT EXISTS `admin_users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `username` varchar(100) NOT NULL,
                    `email` varchar(255) NOT NULL,
                    `password_hash` varchar(255) NOT NULL,
                    `role` enum('admin','manager','staff') DEFAULT 'admin',
                    `firstName` varchar(100) DEFAULT NULL,
                    `lastName` varchar(100) DEFAULT NULL,
                    `lastLogin` timestamp NULL DEFAULT NULL,
                    `isActive` tinyint(1) DEFAULT '1',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `username` (`username`),
                    UNIQUE KEY `email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",

            'categories' => "
                CREATE TABLE IF NOT EXISTS `categories` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `slug` varchar(100) NOT NULL,
                    `description` text,
                    `parent_id` int(11) DEFAULT NULL,
                    `sort_order` int(11) DEFAULT '0',
                    `is_active` tinyint(1) DEFAULT '1',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `slug` (`slug`),
                    KEY `parent_id` (`parent_id`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",

            'website_settings' => "
                CREATE TABLE IF NOT EXISTS `website_settings` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `setting_key` varchar(100) NOT NULL,
                    `setting_value` text,
                    `setting_type` varchar(50) DEFAULT 'text',
                    `description` text,
                    `category` varchar(50) DEFAULT 'general',
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `setting_key` (`setting_key`),
                    KEY `category` (`category`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",

            'email_logs' => "
                CREATE TABLE IF NOT EXISTS `email_logs` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `to_email` varchar(255) NOT NULL,
                    `from_email` varchar(255) DEFAULT NULL,
                    `subject` varchar(500) NOT NULL,
                    `content` text,
                    `email_type` varchar(100) DEFAULT NULL,
                    `order_id` varchar(50) DEFAULT NULL,
                    `status` enum('sent','failed','pending') DEFAULT 'pending',
                    `error_message` text,
                    `sent_at` timestamp NULL DEFAULT NULL,
                    `created_by` varchar(100) DEFAULT 'system',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `to_email` (`to_email`),
                    KEY `email_type` (`email_type`),
                    KEY `status` (`status`),
                    KEY `sent_at` (`sent_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",

            'shipping_zones' => "
                CREATE TABLE IF NOT EXISTS `shipping_zones` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `countries` text,
                    `states` text,
                    `zip_codes` text,
                    `base_rate` decimal(10,2) DEFAULT '0.00',
                    `per_item_rate` decimal(10,2) DEFAULT '0.00',
                    `free_shipping_threshold` decimal(10,2) DEFAULT NULL,
                    `is_active` tinyint(1) DEFAULT '1',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",

            'tax_rates' => "
                CREATE TABLE IF NOT EXISTS `tax_rates` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `name` varchar(100) NOT NULL,
                    `rate` decimal(5,4) NOT NULL DEFAULT '0.0000',
                    `country` varchar(50) DEFAULT NULL,
                    `state` varchar(50) DEFAULT NULL,
                    `zip_code` varchar(20) DEFAULT NULL,
                    `is_active` tinyint(1) DEFAULT '1',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `country` (`country`),
                    KEY `state` (`state`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",

            'admin_activity_logs' => "
                CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `admin_id` int(11) DEFAULT NULL,
                    `admin_username` varchar(100) DEFAULT NULL,
                    `action` varchar(100) NOT NULL,
                    `description` text,
                    `ip_address` varchar(45) DEFAULT NULL,
                    `user_agent` text,
                    `data` text,
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `admin_id` (`admin_id`),
                    KEY `action` (`action`),
                    KEY `created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",

            'room_mappings' => "
                CREATE TABLE IF NOT EXISTS `room_mappings` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `room_type` varchar(100) NOT NULL,
                    `coordinates` text NOT NULL,
                    `linked_items` text,
                    `title` varchar(255) DEFAULT NULL,
                    `description` text,
                    `is_active` tinyint(1) DEFAULT '1',
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `room_type` (`room_type`),
                    KEY `is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            "
        ];

        // Create tables
        foreach ($tables as $tableName => $sql) {
            try {
                // Check if table exists
                $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
                if ($stmt->rowCount() > 0) {
                    $tablesSkipped++;
                    $details[] = [
                        'type' => 'skip',
                        'message' => "Table '$tableName' already exists, skipped"
                    ];
                } else {
                    $pdo->exec($sql);
                    $tablesCreated++;
                    $details[] = [
                        'type' => 'success',
                        'message' => "Created table '$tableName'"
                    ];
                }
            } catch (Exception $e) {
                $warnings[] = "Failed to create table '$tableName': " . $e->getMessage();
                $details[] = [
                    'type' => 'error',
                    'message' => "Failed to create table '$tableName': " . $e->getMessage()
                ];
            }
        }

        // Insert default data
        $defaultData = [
            'admin_users' => [
                'check' => "SELECT COUNT(*) FROM admin_users WHERE username = 'admin'",
                'insert' => "INSERT INTO admin_users (username, email, password_hash, role, firstName, lastName) 
                           VALUES ('admin', 'admin@whimsicalfrog.com', ?, 'admin', 'System', 'Administrator')",
                'data' => [password_hash('admin123', PASSWORD_DEFAULT)],
                'description' => 'Default admin user (username: admin, password: admin123)'
            ],

            'website_settings' => [
                'multiple' => [
                    [
                        'check' => "SELECT COUNT(*) FROM website_settings WHERE setting_key = 'site_name'",
                        'insert' => "INSERT INTO website_settings (setting_key, setting_value, setting_type, description, category) 
                                   VALUES ('site_name', 'WhimsicalFrog', 'text', 'Website name', 'general')",
                        'description' => 'Site name setting'
                    ],
                    [
                        'check' => "SELECT COUNT(*) FROM website_settings WHERE setting_key = 'site_email'",
                        'insert' => "INSERT INTO website_settings (setting_key, setting_value, setting_type, description, category) 
                                   VALUES ('site_email', 'info@whimsicalfrog.com', 'email', 'Default site email', 'general')",
                        'description' => 'Site email setting'
                    ],
                    [
                        'check' => "SELECT COUNT(*) FROM website_settings WHERE setting_key = 'currency'",
                        'insert' => "INSERT INTO website_settings (setting_key, setting_value, setting_type, description, category) 
                                   VALUES ('currency', 'USD', 'text', 'Site currency', 'general')",
                        'description' => 'Currency setting'
                    ],
                    [
                        'check' => "SELECT COUNT(*) FROM website_settings WHERE setting_key = 'tax_rate'",
                        'insert' => "INSERT INTO website_settings (setting_key, setting_value, setting_type, description, category) 
                                   VALUES ('tax_rate', '0.0875', 'decimal', 'Default tax rate', 'commerce')",
                        'description' => 'Default tax rate (8.75%)'
                    ],
                    [
                        'check' => "SELECT COUNT(*) FROM website_settings WHERE setting_key = 'shipping_rate'",
                        'insert' => "INSERT INTO website_settings (setting_key, setting_value, setting_type, description, category) 
                                   VALUES ('shipping_rate', '9.99', 'decimal', 'Default shipping rate', 'commerce')",
                        'description' => 'Default shipping rate'
                    ]
                ]
            ],

            'categories' => [
                'multiple' => [
                    [
                        'check' => "SELECT COUNT(*) FROM categories WHERE slug = 'general'",
                        'insert' => "INSERT INTO categories (name, slug, description, sort_order) 
                                   VALUES ('General', 'general', 'General category for uncategorized items', 1)",
                        'description' => 'Default general category'
                    ],
                    [
                        'check' => "SELECT COUNT(*) FROM categories WHERE slug = 'featured'",
                        'insert' => "INSERT INTO categories (name, slug, description, sort_order) 
                                   VALUES ('Featured', 'featured', 'Featured products', 0)",
                        'description' => 'Featured products category'
                    ]
                ]
            ],

            'shipping_zones' => [
                'check' => "SELECT COUNT(*) FROM shipping_zones WHERE name = 'United States'",
                'insert' => "INSERT INTO shipping_zones (name, countries, base_rate, per_item_rate, free_shipping_threshold) 
                           VALUES ('United States', 'US', 9.99, 0.00, 75.00)",
                'description' => 'Default US shipping zone'
            ],

            'tax_rates' => [
                'check' => "SELECT COUNT(*) FROM tax_rates WHERE name = 'Default Tax'",
                'insert' => "INSERT INTO tax_rates (name, rate, country) 
                           VALUES ('Default Tax', 0.0875, 'US')",
                'description' => 'Default US tax rate (8.75%)'
            ]
        ];

        // Insert default data
        foreach ($defaultData as $table => $config) {
            try {
                if (isset($config['multiple'])) {
                    // Multiple records for this table
                    foreach ($config['multiple'] as $record) {
                        $stmt = $pdo->query($record['check']);
                        if ($stmt->fetchColumn() == 0) {
                            $pdo->exec($record['insert']);
                            $defaultRecords++;
                            $details[] = [
                                'type' => 'success',
                                'message' => "Added: " . $record['description']
                            ];
                        } else {
                            $details[] = [
                                'type' => 'skip',
                                'message' => "Skipped: " . $record['description'] . " (already exists)"
                            ];
                        }
                    }
                } else {
                    // Single record for this table
                    $stmt = $pdo->query($config['check']);
                    if ($stmt->fetchColumn() == 0) {
                        if (isset($config['data'])) {
                            $stmt = $pdo->prepare($config['insert']);
                            $stmt->execute($config['data']);
                        } else {
                            $pdo->exec($config['insert']);
                        }
                        $defaultRecords++;
                        $details[] = [
                            'type' => 'success',
                            'message' => "Added: " . $config['description']
                        ];
                    } else {
                        $details[] = [
                            'type' => 'skip',
                            'message' => "Skipped: " . $config['description'] . " (already exists)"
                        ];
                    }
                }
            } catch (Exception $e) {
                $warnings[] = "Failed to insert default data for '$table': " . $e->getMessage();
                $details[] = [
                    'type' => 'error',
                    'message' => "Failed to add default data for '$table': " . $e->getMessage()
                ];
            }
        }

        $executionTime = round((microtime(true) - $startTime), 2) . ' seconds';

        echo json_encode([
            'success' => true,
            'tables_created' => $tablesCreated,
            'tables_skipped' => $tablesSkipped,
            'default_records' => $defaultRecords,
            'execution_time' => $executionTime,
            'details' => $details,
            'warnings' => $warnings
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database initialization failed: ' . $e->getMessage()
        ]);
    }
}

// ======================================
// IMPORT FUNCTIONS
// ======================================

function importSQL()
{
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $sqlContent = $input['sql_content'] ?? '';

        if (empty($sqlContent)) {
            echo json_encode(['success' => false, 'message' => 'No SQL content provided']);
            return;
        }

        $pdo = Database::getInstance();

        // Split SQL content into individual statements
        $statements = explode(';', $sqlContent);
        $statementsExecuted = 0;
        $totalRowsAffected = 0;
        $warnings = [];

        foreach ($statements as $statement) {
            $statement = trim($statement);

            // Skip empty statements and comments
            if (empty($statement) || strpos($statement, '-') === 0 || strpos($statement, '/*') === 0) {
                continue;
            }

            try {
                $stmt = $pdo->prepare($statement);
                $stmt->execute();
                $statementsExecuted++;
                $totalRowsAffected += $stmt->rowCount();
            } catch (Exception $e) {
                $warnings[] = "Statement failed: " . $e->getMessage();
            }
        }

        echo json_encode([
            'success' => true,
            'statements_executed' => $statementsExecuted,
            'rows_affected' => $totalRowsAffected,
            'warnings' => !empty($warnings) ? implode('; ', $warnings) : null
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'SQL import failed: ' . $e->getMessage()
        ]);
    }
}

function importCSV()
{
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $tableName = $input['table_name'] ?? '';
        $csvContent = $input['csv_content'] ?? '';
        $hasHeaders = $input['has_headers'] ?? true;
        $replaceData = $input['replace_data'] ?? false;

        if (empty($tableName) || empty($csvContent)) {
            echo json_encode(['success' => false, 'message' => 'Table name and CSV content are required']);
            return;
        }

        $pdo = Database::getInstance();

        // Validate table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => "Table '$tableName' does not exist"]);
            return;
        }

        // Get table columns
        $stmt = $pdo->query("DESCRIBE $tableName");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Parse CSV content
        $lines = str_getcsv($csvContent, "\n");
        $rowsImported = 0;
        $skippedRows = 0;

        // Get headers from first row if specified
        $headers = null;
        if ($hasHeaders && count($lines) > 0) {
            $headers = str_getcsv(array_shift($lines));
        } else {
            $headers = $columns; // Use table columns as headers
        }

        // Map CSV headers to table columns
        $columnMapping = [];
        foreach ($headers as $i => $header) {
            if (in_array($header, $columns)) {
                $columnMapping[$i] = $header;
            }
        }

        if (empty($columnMapping)) {
            echo json_encode(['success' => false, 'message' => 'No matching columns found between CSV and table']);
            return;
        }

        // Clear table if replace mode
        if ($replaceData) {
            $pdo->exec("DELETE FROM $tableName");
        }

        // Prepare insert statement
        $mappedColumns = array_values($columnMapping);
        $placeholders = str_repeat('?,', count($mappedColumns) - 1) . '?';
        $insertSQL = "INSERT INTO $tableName (" . implode(',', $mappedColumns) . ") VALUES ($placeholders)";
        $insertStmt = $pdo->prepare($insertSQL);

        // Process data rows
        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            $row = str_getcsv($line);
            $values = [];

            // Map values according to column mapping
            foreach ($columnMapping as $csvIndex => $dbColumn) {
                $values[] = $row[$csvIndex] ?? null;
            }

            try {
                $insertStmt->execute($values);
                $rowsImported++;
            } catch (Exception $e) {
                $skippedRows++;
            }
        }

        echo json_encode([
            'success' => true,
            'rows_imported' => $rowsImported,
            'columns_mapped' => count($columnMapping),
            'skipped_rows' => $skippedRows > 0 ? $skippedRows : null
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'CSV import failed: ' . $e->getMessage()
        ]);
    }
}

function importJSON()
{
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $tableName = $input['table_name'] ?? '';
        $jsonContent = $input['json_content'] ?? '';

        if (empty($tableName) || empty($jsonContent)) {
            echo json_encode(['success' => false, 'message' => 'Table name and JSON content are required']);
            return;
        }

        $pdo = Database::getInstance();

        // Validate table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        if ($stmt->rowCount() == 0) {
            echo json_encode(['success' => false, 'message' => "Table '$tableName' does not exist"]);
            return;
        }

        // Get table columns
        $stmt = $pdo->query("DESCRIBE $tableName");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Parse JSON content
        $jsonData = json_decode($jsonContent, true);
        if ($jsonData === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid JSON format']);
            return;
        }

        // Ensure it's an array of objects
        if (!is_array($jsonData)) {
            echo json_encode(['success' => false, 'message' => 'JSON must contain an array of objects']);
            return;
        }

        $recordsImported = 0;
        $validationErrors = 0;
        $fieldsMapping = [];

        foreach ($jsonData as $record) {
            if (!is_array($record)) {
                $validationErrors++;
                continue;
            }

            // Map JSON fields to table columns
            $mappedFields = [];
            $values = [];

            foreach ($record as $field => $value) {
                if (in_array($field, $columns)) {
                    $mappedFields[] = $field;
                    $values[] = $value;
                    $fieldsMapping[$field] = true;
                }
            }

            if (empty($mappedFields)) {
                $validationErrors++;
                continue;
            }

            // Prepare and execute insert
            $placeholders = str_repeat('?,', count($mappedFields) - 1) . '?';
            $insertSQL = "INSERT INTO $tableName (" . implode(',', $mappedFields) . ") VALUES ($placeholders)";

            try {
                $stmt = $pdo->prepare($insertSQL);
                $stmt->execute($values);
                $recordsImported++;
            } catch (Exception $e) {
                $validationErrors++;
            }
        }

        echo json_encode([
            'success' => true,
            'records_imported' => $recordsImported,
            'fields_mapped' => count($fieldsMapping),
            'validation_errors' => $validationErrors > 0 ? $validationErrors : null
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'JSON import failed: ' . $e->getMessage()
        ]);
    }
}
?> 