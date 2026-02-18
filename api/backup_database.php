<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Enable CORS for development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

function wf_db_backup_has_valid_token(): bool
{
    $provided = $_GET['admin_token'] ?? $_POST['admin_token'] ?? '';
    if ($provided === '') {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        if (is_array($jsonInput)) {
            $provided = $jsonInput['admin_token'] ?? '';
        }
    }
    if ($provided === '') {
        return false;
    }

    $expected = getenv('WF_ADMIN_TOKEN') ?: '';
    if ($expected === '' && defined('WF_ADMIN_TOKEN') && WF_ADMIN_TOKEN) {
        $expected = WF_ADMIN_TOKEN;
    }
    if ($expected === '' && defined('AuthHelper::ADMIN_TOKEN')) {
        $expected = AuthHelper::ADMIN_TOKEN;
    }

    return $expected !== '' && hash_equals($expected, $provided);
}

function wf_db_normalize_data_groups($raw): array
{
    $allowed = ['room_maps', 'customers', 'inventory', 'orders'];
    if (!is_array($raw)) {
        return [];
    }
    $groups = [];
    foreach ($raw as $group) {
        $value = strtolower(trim((string)$group));
        if ($value !== '' && in_array($value, $allowed, true) && !in_array($value, $groups, true)) {
            $groups[] = $value;
        }
    }
    return $groups;
}

function wf_db_parse_scope(array $input): array
{
    $scope = $input['scope'] ?? null;
    if (!is_array($scope)) {
        return ['mode' => 'full', 'data_groups' => []];
    }
    $mode = strtolower(trim((string)($scope['mode'] ?? 'full')));
    if ($mode !== 'tables') {
        return ['mode' => 'full', 'data_groups' => []];
    }
    return [
        'mode' => 'tables',
        'data_groups' => wf_db_normalize_data_groups($scope['data_groups'] ?? [])
    ];
}

function wf_db_get_existing_tables(): array
{
    try {
        $rows = Database::queryAll('SHOW TABLES');
        $tables = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row)) {
                continue;
            }
            $name = (string)reset($row);
            if ($name !== '') {
                $tables[strtolower($name)] = $name;
            }
        }
        return $tables;
    } catch (Exception $e) {
        return [];
    }
}

function wf_db_tables_for_groups(array $groups): array
{
    $wanted = [];
    $add = static function (string $name) use (&$wanted): void {
        $value = strtolower(trim($name));
        if ($value !== '' && !in_array($value, $wanted, true)) {
            $wanted[] = $value;
        }
    };

    foreach ($groups as $group) {
        if ($group === 'room_maps') {
            $add('room_maps');
            continue;
        }
        if ($group === 'customers') {
            $add('users');
            $add('users_meta');
            $add('addresses');
            $add('customer_notes');
            continue;
        }
        if ($group === 'inventory') {
            $add('items');
            $add('item_images');
            $add('categories');
            $add('inventory_option_links');
            $add('gender_template_items');
            $add('size_template_items');
            $add('color_template_items');
            continue;
        }
        if ($group === 'orders') {
            $add('orders');
            $add('order_items');
            continue;
        }
    }

    $existing = wf_db_get_existing_tables();
    if (in_array('inventory', $groups, true) && !empty($existing)) {
        foreach (array_keys($existing) as $table) {
            if (str_starts_with($table, 'inventory_') && !in_array($table, $wanted, true)) {
                $wanted[] = $table;
            }
        }
    }

    $resolved = [];
    foreach ($wanted as $table) {
        if (isset($existing[$table])) {
            $resolved[] = $existing[$table];
        }
    }
    return $resolved;
}

if (!wf_db_backup_has_valid_token()) {
    requireAdmin(true);
}

// Change working directory to parent directory (project root)
chdir(dirname(__DIR__));

function createDatabaseBackup($downloadToComputer = true, $keepOnServer = true, array $scope = ['mode' => 'full', 'data_groups' => []])
{
    try {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "whimsicalfrog_database_backup_$timestamp";
        $backupFile = "$backupName.sql";

        // Create backups directory if it doesn't exist
        if (!is_dir('backups')) {
            mkdir('backups', 0755, true);
        }

        $backupPath = "backups/$backupFile";

        // Clean up old database backups before creating new one
        $cleanupInfo = cleanupOldDatabaseBackups();

        // Get database credentials from config
        $dbHost = $GLOBALS['host'];
        $database = $GLOBALS['db'];
        $username = $GLOBALS['user'];
        $password = $GLOBALS['pass'];

        $isTableScope = ($scope['mode'] ?? 'full') === 'tables';
        $selectedTables = [];
        if ($isTableScope) {
            $groups = $scope['data_groups'] ?? [];
            if (empty($groups)) {
                return [
                    'success' => false,
                    'error' => 'Select at least one data group (room maps, customers, inventory, orders) to back up.'
                ];
            }
            $selectedTables = wf_db_tables_for_groups($groups);
            if (empty($selectedTables)) {
                return [
                    'success' => false,
                    'error' => 'None of the selected data-group tables were found in this database.'
                ];
            }
        }

        // Create mysqldump command with correct syntax and escaping
        $tableClause = '';
        if ($isTableScope) {
            $tableClause = ' ' . implode(' ', array_map('escapeshellarg', $selectedTables));
        }
        $command = "mysqldump --host=" . escapeshellarg($dbHost) .
            " --user=" . escapeshellarg($username) .
            " --password=" . escapeshellarg($password) .
            " --single-transaction --routines --triggers --result-file=" . escapeshellarg($backupPath) .
            " " . escapeshellarg($database) . $tableClause;

        exec("$command 2>&1", $output, $returnCode);

        if ($returnCode === 0 && file_exists($backupPath)) {
            $fileSize = filesize($backupPath);
            $fileSizeFormatted = formatFileSize($fileSize);

            // Get table count from the backup file
            $tableCount = getTableCountFromBackup($backupPath);

            $response = [
                'success' => true,
                'filename' => $backupFile,
                'path' => $backupPath,
                'size' => $fileSize,
                'size_formatted' => $fileSizeFormatted,
                'created' => date('Y-m-d H:i:s'),
                'table_count' => $tableCount,
                'download_to_computer' => $downloadToComputer,
                'keep_on_server' => $keepOnServer,
                'scope' => [
                    'type' => $isTableScope ? 'database_tables' : 'full',
                    'data_groups' => $isTableScope ? ($scope['data_groups'] ?? []) : [],
                    'tables' => $isTableScope ? $selectedTables : []
                ]
            ];

            // Only include download URL if downloading to computer
            if ($downloadToComputer) {
                $response['download_url'] = $backupPath;
            }

            // Add cleanup info if any files were deleted
            if ($cleanupInfo['deleted'] > 0) {
                $response['cleanup'] = $cleanupInfo;
            }

            // If not keeping on server, delete the backup file after providing download
            if (!$keepOnServer && $downloadToComputer) {
                // Note: File will be deleted after response is sent
                $response['delete_after_download'] = true;
            }

            return $response;
        } else {
            $errorMsg = !empty($output) ? implode("\n", $output) : 'Unknown system error';
            return [
                'success' => false,
                'error' => "Failed to create database backup: $errorMsg"
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Database backup creation failed: ' . $e->getMessage()
        ];
    }
}

function getTableCountFromBackup($backupPath)
{
    // Memory efficient way: Query the DB instead of reading a potentially huge file
    try {
        global $db_conn; // Assuming connection exists in config.php
        if (!$db_conn)
            return 0;
        $stmt = $db_conn->query("SELECT count(*) FROM information_schema.tables WHERE table_schema = '" . $GLOBALS['db'] . "'");
        return $stmt ? (int) $stmt->fetchColumn() : 0;
    } catch (Exception $e) {
        return 0;
    }
}

function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function cleanupOldDatabaseBackups()
{
    $maxBackups = 10;
    $backupDir = 'backups';
    $deletedCount = 0;
    $deletedFiles = [];

    if (!is_dir($backupDir)) {
        return ['deleted' => 0, 'files' => []];
    }

    // Get all database backup files
    $backupFiles = [];
    $files = scandir($backupDir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $filePath = $backupDir . '/' . $file;

        // Only process WhimsicalFrog database backup files
        if (is_file($filePath) && preg_match('/^whimsicalfrog_database_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql$/', $file)) {
            $backupFiles[] = [
                'name' => $file,
                'path' => $filePath,
                'modified' => filemtime($filePath)
            ];
        }
    }

    // Sort by modification time (newest first)
    usort($backupFiles, function ($a, $b) {
        return $b['modified'] - $a['modified'];
    });

    // Delete old backups if we have more than the limit
    if (count($backupFiles) >= $maxBackups) {
        $filesToDelete = array_slice($backupFiles, $maxBackups - 1); // Keep 9, delete the rest (since we're about to create 1 more)

        foreach ($filesToDelete as $fileInfo) {
            if (file_exists($fileInfo['path'])) {
                unlink($fileInfo['path']);
                $deletedCount++;
                $deletedFiles[] = $fileInfo['name'];
                error_log("Database backup cleanup: Deleted old backup file: " . $fileInfo['name']);
            }
        }
    }

    return [
        'deleted' => $deletedCount,
        'files' => $deletedFiles
    ];
}

// Handle the request
try {
    $method = $_SERVER['REQUEST_METHOD'];

    // Get request parameters
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }
    $downloadToComputer = !empty($input['download_to_computer']);
    $keepOnServer = !empty($input['keep_on_server']);
    if (!array_key_exists('download_to_computer', $input)) {
        $downloadToComputer = true;
    }
    if (!array_key_exists('keep_on_server', $input)) {
        $keepOnServer = true;
    }

    // Validate that at least one destination is selected
    if (!$downloadToComputer && !$keepOnServer) {
        $result = ['success' => false, 'error' => 'At least one backup destination must be selected'];
    } else {
        $scope = wf_db_parse_scope($input);
        $result = createDatabaseBackup($downloadToComputer, $keepOnServer, $scope);

        // If successful and we need to delete after download
        if ($result['success'] && isset($result['delete_after_download']) && $result['delete_after_download']) {
            // Register shutdown function to delete file after response is sent
            register_shutdown_function(function () use ($result) {
                if (file_exists($result['path'])) {
                    unlink($result['path']);
                    error_log("Database backup file deleted after download: " . $result['filename']);
                }
            });
        }
    }

    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
