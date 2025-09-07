<?php

/**
 * WhimsicalFrog System Cleanup and Maintenance
 * Centralized system functions to eliminate duplication
 * Generated: 2025-07-01 23:30:28
 */

// Include system and file dependencies
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/file_helper.php';


function cleanupStaleFiles()
{
    $unusedFiles = getUnusedFiles();
    $removed = [];
    $errors = [];

    foreach ($unusedFiles as $file) {
        try {
            if (unlink($file['path'])) {
                $removed[] = basename($file['path']);
            }
        } catch (Exception $e) {
            $errors[] = [
                'file' => $file['path'],
                'error' => $e->getMessage()
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'removed_files' => $removed,
        'errors' => $errors,
        'message' => count($removed) . ' stale files removed successfully'
    ]);
}


function removeUnusedCode()
{
    $processed = [];
    $errors = [];

    // Remove stale comments from files
    $staleComments = getStaleComments();
    $filesProcessed = [];

    foreach ($staleComments as $comment) {
        $filePath = '../' . $comment['file'];

        if (!in_array($filePath, $filesProcessed)) {
            try {
                $content = file_get_contents($filePath);

                // Remove various types of stale comments
                $patterns = [
                    '/\/\*\*?\s*TODO[^*]*\*\/\s*/',
                    '/\/\/\s*TODO.*\n/',
                    '/\/\*\*?\s*FIXME[^*]*\*\/\s*/',
                    '/\/\/\s*FIXME.*\n/',
                    '/\/\*\*?\s*DEPRECATED[^*]*\*\/\s*/',
                    '/\/\/\s*DEBUG.*\n/',
                    '/\/\*\*?\s*OLD[^*]*\*\/\s*/',
                    '/\/\/\s*TEMP.*\n/'
                ];

                $originalContent = $content;
                foreach ($patterns as $pattern) {
                    $content = preg_replace($pattern, '', $content);
                }

                if ($content !== $originalContent) {
                    file_put_contents($filePath, $content);
                    $filesProcessed[] = $filePath;
                    $processed[] = str_replace('../', '', $filePath);
                }

            } catch (Exception $e) {
                $errors[] = [
                    'file' => $comment['file'],
                    'error' => $e->getMessage()
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'processed_files' => $processed,
        'errors' => $errors,
        'message' => count($processed) . ' files cleaned of stale comments'
    ]);
}


function optimizeDatabase()
{
    $startTime = microtime(true);
    $optimized = [];
    $errors = [];
    $details = [];

    try {
        // Get all tables with their sizes
        $rows = Database::queryAll("SHOW TABLES");
        $tables = array_map(function($r){ return array_values($r)[0]; }, $rows);
        $totalTables = count($tables);

        foreach ($tables as $index => $table) {
            $tableStartTime = microtime(true);

            try {
                // Get table info before optimization
                $sizeQuery = "SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                    table_rows
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE() AND table_name = ?";
                $beforeInfo = Database::queryOne($sizeQuery, [$table]);

                // Optimize table
                $optimizeRows = Database::queryAll("OPTIMIZE TABLE `$table`");
                $optimizeResult = $optimizeRows ? $optimizeRows[0] : [];

                // Get table info after optimization
                $afterInfo = Database::queryOne($sizeQuery, [$table]);

                $tableEndTime = microtime(true);
                $tableTime = round(($tableEndTime - $tableStartTime), 3);

                $tableDetails = [
                    'table' => $table,
                    'status' => $optimizeResult['Msg_text'] ?? 'OK',
                    'rows' => (int)($afterInfo['table_rows'] ?? 0),
                    'size_mb' => (float)($afterInfo['size_mb'] ?? 0),
                    'time_seconds' => $tableTime,
                    'progress' => round((($index + 1) / $totalTables) * 100, 1)
                ];

                if ($beforeInfo && $afterInfo) {
                    $sizeDiff = (float)$beforeInfo['size_mb'] - (float)$afterInfo['size_mb'];
                    $tableDetails['space_reclaimed_mb'] = round($sizeDiff, 3);
                }

                $optimized[] = $table;
                $details[] = $tableDetails;

            } catch (Exception $e) {
                $errors[] = [
                    'table' => $table,
                    'error' => $e->getMessage(),
                    'progress' => round((($index + 1) / $totalTables) * 100, 1)
                ];
            }
        }

        $totalTime = round((microtime(true) - $startTime), 3);
        $totalSpaceReclaimed = array_sum(array_column($details, 'space_reclaimed_mb'));

        echo json_encode([
            'success' => true,
            'optimized_tables' => $optimized,
            'errors' => $errors,
            'details' => $details,
            'summary' => [
                'total_tables' => $totalTables,
                'optimized_count' => count($optimized),
                'error_count' => count($errors),
                'total_time_seconds' => $totalTime,
                'total_space_reclaimed_mb' => round($totalSpaceReclaimed, 3)
            ],
            'message' => count($optimized) . " of $totalTables tables optimized successfully in {$totalTime}s" .
                        ($totalSpaceReclaimed > 0 ? ", reclaimed {$totalSpaceReclaimed}MB" : "")
        ]);

    } catch (Exception $e) {
        throw new Exception('Database optimization failed: ' . $e->getMessage());
    }
}
