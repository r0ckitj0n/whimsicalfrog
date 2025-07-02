<?php
/**
 * WhimsicalFrog Web Database Manager
 * Browser-based database management tool
 */

session_start();

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
        <style>
            body { font-family: Arial, sans-serif; margin: 50px; background: #f5f5f5; }
            .login-form { background: white; padding: 30px; border-radius: 8px; max-width: 400px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            input[type="password"] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
            button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #005a87; }
            .error { color: red; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="login-form">
            <h2>🐸 Database Manager</h2>
            <p>Enter password to access database management tools:</p>
            <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
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

<!DOCTYPE html>
<html>
<head>
    <title>WhimsicalFrog Database Manager</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #007cba; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .env-switcher { margin-bottom: 20px; }
        .env-switcher a { padding: 10px 20px; margin-right: 10px; background: white; text-decoration: none; border-radius: 4px; border: 2px solid #007cba; }
        .env-switcher a.active { background: #007cba; color: white; }
        .panel { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .status-item { padding: 15px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #007cba; }
        .status-item strong { display: block; font-size: 1.2em; color: #007cba; }
        textarea { width: 100%; height: 100px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #005a87; }
        button.secondary { background: #6c757d; }
        button.secondary:hover { background: #545b62; }
        .results { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
        th { background: #f8f9fa; font-weight: bold; }
        tr:nth-child(even) { background: #f8f9fa; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .loading { text-align: center; padding: 20px; color: #666; }
        .tabs { display: flex; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: #e9ecef; border: none; cursor: pointer; margin-right: 5px; }
        .tab.active { background: #007cba; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .logout { float: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🐸 WhimsicalFrog Database Manager</h1>
        <a href="?logout=1" class="logout" style="color: white; text-decoration: none;">Logout</a>
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
        <button class="tab active" onclick="showTab('query')">SQL Query</button>
        <button class="tab" onclick="showTab('tables')">Tables</button>
        <button class="tab" onclick="showTab('structure')">Table Structure</button>
    </div>
    
    <div id="query" class="tab-content active panel">
        <h3>Execute SQL Query</h3>
        <textarea id="sqlQuery" placeholder="SELECT * FROM global_css_rules WHERE category = 'main_room' LIMIT 10;"></textarea>
        <br>
        <button onclick="executeQuery()">Execute Query</button>
        <button class="secondary" onclick="clearQuery()">Clear</button>
        <div id="queryResults" class="results"></div>
    </div>
    
    <div id="tables" class="tab-content panel">
        <h3>Database Tables</h3>
        <button onclick="loadTables()">Refresh Tables</button>
        <div id="tablesResults" class="results"></div>
    </div>
    
    <div id="structure" class="tab-content panel">
        <h3>Table Structure</h3>
        <input type="text" id="tableName" placeholder="Enter table name" style="padding: 10px; width: 300px; margin-right: 10px;">
        <button onclick="describeTable()">Describe Table</button>
        <div id="structureResults" class="results"></div>
    </div>

    <script>
        const currentEnv = '<?= $currentEnv ?>';
        
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function loadStatus() {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=status'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('status').innerHTML = `
                        <div class="status-grid">
                            <div class="status-item"><strong>${data.data.version}</strong>MySQL Version</div>
                            <div class="status-item"><strong>${data.data.tables}</strong>Tables</div>
                            <div class="status-item"><strong>${data.data.size}</strong>Database Size</div>
                            <div class="status-item"><strong>${data.data.host}</strong>Host</div>
                            <div class="status-item"><strong>${data.data.database}</strong>Database</div>
                        </div>
                    `;
                } else {
                    document.getElementById('status').innerHTML = `<div class="error">Error: ${data.error}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('status').innerHTML = `<div class="error">Connection failed: ${error.message}</div>`;
            });
        }
        
        function executeQuery() {
            const sql = document.getElementById('sqlQuery').value.trim();
            if (!sql) {
                alert('Please enter a SQL query');
                return;
            }
            
            document.getElementById('queryResults').innerHTML = '<div class="loading">Executing query...</div>';
            
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=query&sql=${encodeURIComponent(sql)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.type === 'select' && data.data.length > 0) {
                        let html = `<div class="success">Query executed successfully. ${data.rows} rows returned.</div>`;
                        html += '<table><thead><tr>';
                        
                        // Headers
                        Object.keys(data.data[0]).forEach(key => {
                            html += `<th>${key}</th>`;
                        });
                        html += '</tr></thead><tbody>';
                        
                        // Data
                        data.data.forEach(row => {
                            html += '<tr>';
                            Object.values(row).forEach(value => {
                                html += `<td>${value !== null ? value : '<em>NULL</em>'}</td>`;
                            });
                            html += '</tr>';
                        });
                        html += '</tbody></table>';
                        
                        document.getElementById('queryResults').innerHTML = html;
                    } else if (data.type === 'update') {
                        document.getElementById('queryResults').innerHTML = `<div class="success">Query executed successfully. ${data.affected} rows affected.</div>`;
                    } else {
                        document.getElementById('queryResults').innerHTML = '<div class="success">Query executed successfully. No results returned.</div>';
                    }
                } else {
                    document.getElementById('queryResults').innerHTML = `<div class="error">Error: ${data.error}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('queryResults').innerHTML = `<div class="error">Request failed: ${error.message}</div>`;
            });
        }
        
        function clearQuery() {
            document.getElementById('sqlQuery').value = '';
            document.getElementById('queryResults').innerHTML = '';
        }
        
        function loadTables() {
            document.getElementById('tablesResults').innerHTML = '<div class="loading">Loading tables...</div>';
            
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=tables'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '<table><thead><tr><th>Table Name</th><th>Row Count</th><th>Actions</th></tr></thead><tbody>';
                    data.data.forEach(table => {
                        html += `<tr>
                            <td><strong>${table.name}</strong></td>
                            <td>${table.rows}</td>
                            <td>
                                <button onclick="quickQuery('SELECT * FROM \`${table.name}\` LIMIT 10')">Preview</button>
                                <button onclick="quickQuery('DESCRIBE \`${table.name}\`')">Structure</button>
                            </td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    document.getElementById('tablesResults').innerHTML = html;
                } else {
                    document.getElementById('tablesResults').innerHTML = `<div class="error">Error: ${data.error}</div>`;
                }
            });
        }
        
        function describeTable() {
            const tableName = document.getElementById('tableName').value.trim();
            if (!tableName) {
                alert('Please enter a table name');
                return;
            }
            
            document.getElementById('structureResults').innerHTML = '<div class="loading">Loading table structure...</div>';
            
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=describe&table=${encodeURIComponent(tableName)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '<table><thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead><tbody>';
                    data.data.forEach(field => {
                        html += `<tr>
                            <td><strong>${field.Field}</strong></td>
                            <td>${field.Type}</td>
                            <td>${field.Null}</td>
                            <td>${field.Key}</td>
                            <td>${field.Default || '<em>NULL</em>'}</td>
                            <td>${field.Extra}</td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                    document.getElementById('structureResults').innerHTML = html;
                } else {
                    document.getElementById('structureResults').innerHTML = `<div class="error">Error: ${data.error}</div>`;
                }
            });
        }
        
        function quickQuery(sql) {
            document.getElementById('sqlQuery').value = sql;
            showTab('query');
            document.querySelector('.tab').click();
            executeQuery();
        }
        
        // Load status on page load
        loadStatus();
    </script>
</body>
</html> 