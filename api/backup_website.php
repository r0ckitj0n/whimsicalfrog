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

function wf_backup_has_valid_token(): bool
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

if (!wf_backup_has_valid_token()) {
    requireAdmin(true);
}

// Change working directory to parent directory (project root)
chdir(dirname(__DIR__));

function wf_normalize_image_groups($raw): array
{
    $allowed = ['items', 'backgrounds', 'signs'];
    if (!is_array($raw)) {
        return [];
    }

    $groups = [];
    foreach ($raw as $group) {
        $value = strtolower(trim((string) $group));
        if ($value !== '' && in_array($value, $allowed, true) && !in_array($value, $groups, true)) {
            $groups[] = $value;
        }
    }
    return $groups;
}

function wf_parse_website_scope(array $input): array
{
    $scope = $input['scope'] ?? null;
    if (!is_array($scope)) {
        return ['mode' => 'full', 'image_groups' => []];
    }

    $mode = strtolower(trim((string) ($scope['mode'] ?? 'full')));
    if ($mode !== 'images') {
        return ['mode' => 'full', 'image_groups' => []];
    }

    return [
        'mode' => 'images',
        'image_groups' => wf_normalize_image_groups($scope['image_groups'] ?? [])
    ];
}

function createBackup($downloadToComputer = true, $keepOnServer = true, array $scope = ['mode' => 'full', 'image_groups' => []])
{
    try {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = "whimsicalfrog_backup_$timestamp";
        $backupFile = "$backupName.tar.gz";

        // Create backups directory if it doesn't exist
        if (!is_dir('backups')) {
            mkdir('backups', 0755, true);
        }

        $backupPath = "backups/$backupFile";

        // Clean up old backups before creating new one
        $cleanupInfo = cleanupOldBackups();

        $isImagesScope = ($scope['mode'] ?? 'full') === 'images';
        $envBackupPath = null;

        if ($isImagesScope) {
            $selectedGroups = $scope['image_groups'] ?? [];
            if (empty($selectedGroups)) {
                return [
                    'success' => false,
                    'error' => 'Select at least one image group (items, backgrounds, signs) to back up.'
                ];
            }

            $includePaths = [];
            foreach ($selectedGroups as $group) {
                $candidate = 'images/' . $group;
                if (is_dir($candidate)) {
                    $includePaths[] = $candidate;
                }
            }

            if (empty($includePaths)) {
                return [
                    'success' => false,
                    'error' => 'None of the selected image directories exist on disk.'
                ];
            }

            $includeString = implode(' ', array_map('escapeshellarg', $includePaths));
            $command = 'tar -czf ' . escapeshellarg($backupPath) . ' ' . $includeString;
        } else {
            // Files and directories to exclude from backup (GNU tar syntax)
            // Note: Exclude flags must appear BEFORE the file list (".")
            $excludes = [
                '--exclude=backups',
                '--exclude=node_modules',
                '--exclude=.git',
                '--exclude=.DS_Store',
                '--exclude=logs',
                '--exclude=*.log',
                '--exclude=.env',
            ];
            $excludeString = implode(' ', $excludes);

            // Save .env separately as .env.backup (for manual restoration if needed)
            $envPath = '.env';
            $envBackupPath = "backups/{$backupName}.env.backup";
            if (file_exists($envPath)) {
                copy($envPath, $envBackupPath);
            }

            $command = 'tar -czf ' . escapeshellarg($backupPath) . ' ' . $excludeString . ' .';
        }

        exec("$command 2>&1", $output, $returnCode);

        if ($returnCode === 0 && file_exists($backupPath)) {
            $fileSize = filesize($backupPath);
            $fileSizeFormatted = formatFileSize($fileSize);

            $response = [
                'success' => true,
                'filename' => $backupFile,
                'path' => $backupPath,
                'size' => $fileSize,
                'size_formatted' => $fileSizeFormatted,
                'created' => date('Y-m-d H:i:s'),
                'download_to_computer' => $downloadToComputer,
                'keep_on_server' => $keepOnServer,
                'env_backup' => $envBackupPath && file_exists($envBackupPath) ? $envBackupPath : null,
                'scope' => [
                    'type' => $isImagesScope ? 'images' : 'full',
                    'image_groups' => $isImagesScope ? ($scope['image_groups'] ?? []) : []
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
            return [
                'success' => false,
                'error' => 'Failed to create backup archive'
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Backup creation failed: ' . $e->getMessage()
        ];
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

function cleanupOldBackups()
{
    $maxBackups = 10;
    $backupDir = 'backups';
    $deletedCount = 0;
    $deletedFiles = [];

    if (!is_dir($backupDir)) {
        return ['deleted' => 0, 'files' => []];
    }

    // Get all backup files
    $backupFiles = [];
    $files = scandir($backupDir);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $filePath = $backupDir . '/' . $file;

        // Only process WhimsicalFrog backup files
        if (is_file($filePath) && preg_match('/^whimsicalfrog_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.tar\.gz$/', $file)) {
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
                error_log("Backup cleanup: Deleted old backup file: " . $fileInfo['name']);
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
        $scope = wf_parse_website_scope($input);
        $result = createBackup($downloadToComputer, $keepOnServer, $scope);

        // If successful and we need to delete after download
        if ($result['success'] && isset($result['delete_after_download']) && $result['delete_after_download']) {
            // Register shutdown function to delete file after response is sent
            register_shutdown_function(function () use ($result) {
                if (file_exists($result['path'])) {
                    unlink($result['path']);
                    error_log("Backup file deleted after download: " . $result['filename']);
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
