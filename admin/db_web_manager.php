<?php
/**
 * WhimsicalFrog Web Database Manager
 * Browser-based database management tool
 */



// Simple authentication
$password = 'Palz2516Admin';
$authenticated = isset($_SESSION['db_auth']) && $_SESSION['db_auth'] === true;

if (isset($_POST['password'])) {
    if ($_POST['password'] === $password) {
        $_SESSION['db_auth'] = true;
        $authenticated = true;
    } else {
        $error = 'Invalid password';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['db_auth']);
    $authenticated = false;
}

if (!$authenticated) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Manager - Authentication</title>
        
    </head>
    <body>
        <div class="login-form">
            <h2>🐸 Database Manager</h2>
            <p>Enter password to access database management tools:</p>
            <?php if (isset($error)) {
                echo "<div class='error'>$error</div>";
            } ?>
            <form method="post">
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Include database configuration
require_once __DIR__ . '/includes/database.php';

// Database configurations
$configs = [
    'local' => [
        'host' => 'localhost',
        'db' => 'whimsicalfrog',
        'user' => 'root',
        'pass' => 'Palz2516',
        'name' => 'Local Database'
    ],
    'live' => [
        'host' => 'db5017975223.hosting-data.io',
        'db' => 'dbs14295502',
        'user' => 'dbu2826619',
        'pass' => 'Palz2516!',
        'name' => 'Live Database (IONOS)'
    ]
];

$currentEnv = $_GET['env'] ?? 'local';
$config = $configs[$currentEnv];

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['db']};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10
        ];

        $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);

        switch ($_POST['action']) {
            case 'status':
                $stmt = $pdo->query("SELECT VERSION() as version");
                $version = $stmt->fetch()['version'];

                $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '{$config['db']}'");
                $tableCount = $stmt->fetch()['count'];

                $stmt = $pdo->query("
                    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = '{$config['db']}'
                ");
                $size = $stmt->fetch()['size_mb'];

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'version' => $version,
                        'tables' => $tableCount,
                        'size' => $size . ' MB',
                        'host' => $config['host'],
                        'database' => $config['db']
                    ]
                ]);
                break;

            case 'tables':
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $tableData = [];
                foreach ($tables as $table) {
                    $stmt = $pdo->query("SELECT COUNT(*) as count FROM `{$table}`");
                    $count = $stmt->fetch()['count'];
                    $tableData[] = ['name' => $table, 'rows' => $count];
                }

                echo json_encode(['success' => true, 'data' => $tableData]);
                break;

            case 'query':
                $sql = $_POST['sql'] ?? '';
                if (empty($sql)) {
                    echo json_encode(['success' => false, 'error' => 'SQL query required']);
                    break;
                }

                $stmt = $pdo->query($sql);

                if ($stmt->columnCount() > 0) {
                    $results = $stmt->fetchAll();
                    echo json_encode([
                        'success' => true,
                        'data' => $results,
                        'type' => 'select',
                        'rows' => count($results)
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'data' => [],
                        'type' => 'update',
                        'affected' => $stmt->rowCount()
                    ]);
                }
                break;

            case 'describe':
                $table = $_POST['table'] ?? '';
                if (empty($table)) {
                    echo json_encode(['success' => false, 'error' => 'Table name required']);
                    break;
                }

                $stmt = $pdo->query("DESCRIBE `{$table}`");
                $structure = $stmt->fetchAll();

                echo json_encode(['success' => true, 'data' => $structure]);
                break;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    exit;
}
?>

<?php include __DIR__ . '/../partials/header.php'; ?>
    <div class="header">
        <h1>🐸 WhimsicalFrog Database Manager</h1>
        <a href="?logout=1" class="logout">Logout</a>
    </div>
    
    <div class="env-switcher">
        <a href="?env=local" class="<?= $currentEnv === 'local' ? 'active' : '' ?>">Local Database</a>
        <a href="?env=live" class="<?= $currentEnv === 'live' ? 'active' : '' ?>">Live Database</a>
    </div>
    
    <div class="panel">
        <h2><?= $config['name'] ?> Status</h2>
        <div id="status" class="loading">Loading database status...</div>
    </div>
    
    <div class="tabs">
        <button class="tab active" data-action="showTab" data-params='{"tabName":"query"}'>SQL Query</button>
        <button class="tab" data-action="showTab" data-params='{"tabName":"tables"}'>Tables</button>
        <button class="tab" data-action="showTab" data-params='{"tabName":"structure"}'>Table Structure</button>
    </div>
    
    <div id="query" class="tab-content active panel">
        <h3>Execute SQL Query</h3>
        <textarea id="sqlQuery" placeholder="SELECT * FROM global_css_rules WHERE category = 'main_room' LIMIT 10;"></textarea>
        <br>
        <button data-action="executeQuery">Execute Query</button>
        <button class="secondary" data-action="clearQuery">Clear</button>
        <div id="queryResults" class="results"></div>
    </div>
    
    <div id="tables" class="tab-content panel">
        <h3>Database Tables</h3>
        <button data-action="loadTables">Refresh Tables</button>
        <div id="tablesResults" class="results"></div>
    </div>
    
    <div id="structure" class="tab-content panel">
        <h3>Table Structure</h3>
        <input type="text" id="tableName" placeholder="Enter table name" class="input-table-name">
        <button data-action="describeTable">Describe Table</button>
        <div id="structureResults" class="results"></div>
    </div>

<?php include __DIR__ . '/../partials/footer.php'; ?>