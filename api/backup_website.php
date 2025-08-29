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

function createBackup($downloadToComputer = true, $keepOnServer = true) {
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
        
        // Files and directories to exclude from backup
        $excludes = [
            '-exclude=backups',
            '-exclude=node_modules',
            '-exclude=.git',
            '-exclude=.DS_Store',
            '-exclude=logs', // Exclude the entire logs directory
            '-exclude=*.log'   // Exclude any stray .log files in root
        ];
        
        $excludeString = implode(' ', $excludes);
        
        // Create tar.gz archive
        $command = "tar -czf $backupPath $excludeString .";
        
        exec($command, $output, $returnCode);
        
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

function formatFileSize($bytes) {
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

function cleanupOldBackups() {
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
        if ($file === '.' || $file === '..') continue;
        
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
    usort($backupFiles, function($a, $b) {
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
    
    if ($method === 'POST') {
        // Get request parameters
        $input = json_decode(file_get_contents('php://input'), true);
        $downloadToComputer = isset($input['download_to_computer']) ? $input['download_to_computer'] : true;
        $keepOnServer = isset($input['keep_on_server']) ? $input['keep_on_server'] : true;
        
        // Validate that at least one destination is selected
        if (!$downloadToComputer && !$keepOnServer) {
            $result = ['success' => false, 'error' => 'At least one backup destination must be selected'];
        } else {
            $result = createBackup($downloadToComputer, $keepOnServer);
            
            // If successful and we need to delete after download
            if ($result['success'] && isset($result['delete_after_download']) && $result['delete_after_download']) {
                // Register shutdown function to delete file after response is sent
                register_shutdown_function(function() use ($result) {
                    if (file_exists($result['path'])) {
                        unlink($result['path']);
                        error_log("Backup file deleted after download: " . $result['filename']);
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