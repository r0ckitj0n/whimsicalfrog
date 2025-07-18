<?php
require_once 'config.php';

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

// Change working directory to parent directory (project root)
chdir(dirname(__DIR__));

function createDatabaseBackup($downloadToComputer = true, $keepOnServer = true)
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

        // Create mysqldump command
        $command = "mysqldump -host=$dbHost -user=$username -password='$password' -single-transaction -routines -triggers $database > $backupPath";

        exec($command, $output, $returnCode);

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
                'keep_on_server' => $keepOnServer
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
            return [
                'success' => false,
                'error' => 'Failed to create database backup. Please check database connection and permissions.'
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
    try {
        $content = file_get_contents($backupPath);
        // Count CREATE TABLE statements
        $tableCount = preg_match_all('/CREATE TABLE/i', $content);
        return $tableCount;
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

    if ($method === 'POST') {
        // Get request parameters
        $input = json_decode(file_get_contents('php://input'), true);
        $downloadToComputer = isset($input['download_to_computer']) ? $input['download_to_computer'] : true;
        $keepOnServer = isset($input['keep_on_server']) ? $input['keep_on_server'] : true;

        // Validate that at least one destination is selected
        if (!$downloadToComputer && !$keepOnServer) {
            $result = ['success' => false, 'error' => 'At least one backup destination must be selected'];
        } else {
            $result = createDatabaseBackup($downloadToComputer, $keepOnServer);

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
    } else {
        $result = ['success' => false, 'error' => 'Method not allowed'];
    }

    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 