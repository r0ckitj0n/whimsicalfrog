<?php
/**
 * WhimsicalFrog System Cleanup API
 * Identifies and removes stale code, comments, and unused database elements
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Check authentication
session_start();
if (!requireAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    switch ($action) {
        case 'analyze':
            analyzeSystem($pdo);
            break;
            
        case 'remove_empty_tables':
            removeEmptyTables($pdo);
            break;
            
        case 'cleanup_stale_files':
            cleanupStaleFiles();
            break;
            
        case 'remove_unused_code':
            removeUnusedCode();
            break;
            
        case 'optimize_database':
            optimizeDatabase($pdo);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function analyzeSystem($pdo) {
    $analysis = [
        'empty_tables' => getEmptyTables($pdo),
        'unused_files' => getUnusedFiles(),
        'stale_comments' => getStaleComments(),
        'redundant_code' => getRedundantCode(),
        'optimization_opportunities' => getOptimizationOpportunities($pdo)
    ];
    
    echo json_encode([
        'success' => true,
        'analysis' => $analysis,
        'recommendations' => generateRecommendations($analysis)
    ]);
}

function getEmptyTables($pdo) {
    $tables = [];
    $allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    // Tables that are safe to remove if empty (not core functionality)
    $removableTables = [
        'area_mappings',
        'item_marketing_preferences', 
        'optimization_suggestions',
        'sale_items',
        'square_settings',
        'square_sync_log'
    ];
    
    foreach ($allTables as $table) {
        if (in_array($table, $removableTables)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                // Check if table is referenced in code
                $references = checkTableReferences($table);
                $tables[] = [
                    'name' => $table,
                    'row_count' => $count,
                    'references' => $references,
                    'safe_to_remove' => count($references) == 0
                ];
            }
        }
    }
    
    return $tables;
}

function checkTableReferences($tableName) {
    $references = [];
    $phpFiles = glob('../**/*.php', GLOB_BRACE);
    
    foreach ($phpFiles as $file) {
        $content = file_get_contents($file);
        if (strpos($content, $tableName) !== false) {
            // Further check if it's actually used in SQL
            if (preg_match('/FROM\s+' . preg_quote($tableName) . '|INSERT\s+INTO\s+' . preg_quote($tableName) . '|UPDATE\s+' . preg_quote($tableName) . '/i', $content)) {
                $references[] = str_replace('../', '', $file);
            }
        }
    }
    
    return $references;
}

function getUnusedFiles() {
    $unusedFiles = [];
    
    // Known stale files that can be removed
    $staleFiles = [
        '../api/email_config_backup_2025-06-16_15-14-32.php',
        '../api/square_settings_refactored_demo.php',
        '../api/square_settings_refactored.php',
        '../cookies.txt',
        '../current_cron.txt',
        '../new_cron.txt',
        '../test_cart_options.html'
    ];
    
    foreach ($staleFiles as $file) {
        if (file_exists($file)) {
            $unusedFiles[] = [
                'path' => $file,
                'size' => filesize($file),
                'modified' => filemtime($file),
                'reason' => 'Identified as stale/backup file'
            ];
        }
    }
    
    return $unusedFiles;
}

function getStaleComments() {
    $staleComments = [];
    $patterns = [
        '/\/\*\*?\s*TODO[^*]*\*\//',
        '/\/\/\s*TODO.*/',
        '/\/\*\*?\s*FIXME[^*]*\*\//',
        '/\/\/\s*FIXME.*/',
        '/\/\*\*?\s*DEPRECATED[^*]*\*\//',
        '/\/\/\s*DEBUG.*/',
        '/\/\*\*?\s*OLD[^*]*\*\//',
        '/\/\/\s*TEMP.*/'
    ];
    
    $phpFiles = glob('../{api,sections,includes}/*.php', GLOB_BRACE);
    
    foreach ($phpFiles as $file) {
        $content = file_get_contents($file);
        $lines = explode("\n", $content);
        
        foreach ($lines as $lineNum => $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    $staleComments[] = [
                        'file' => str_replace('../', '', $file),
                        'line' => $lineNum + 1,
                        'content' => trim($line),
                        'type' => 'stale_comment'
                    ];
                }
            }
        }
    }
    
    return $staleComments;
}

function getRedundantCode() {
    $redundantCode = [];
    
    // Check for duplicate function definitions
    $functions = [];
    $phpFiles = glob('../{api,sections,includes}/*.php', GLOB_BRACE);
    
    foreach ($phpFiles as $file) {
        $content = file_get_contents($file);
        
        // Find function definitions
        preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches, PREG_OFFSET_CAPTURE);
        
        foreach ($matches[1] as $match) {
            $functionName = $match[0];
            $offset = $match[1];
            
            if (!isset($functions[$functionName])) {
                $functions[$functionName] = [];
            }
            
            $functions[$functionName][] = [
                'file' => str_replace('../', '', $file),
                'line' => substr_count(substr($content, 0, $offset), "\n") + 1
            ];
        }
    }
    
    // Find duplicates
    foreach ($functions as $functionName => $locations) {
        if (count($locations) > 1) {
            $redundantCode[] = [
                'type' => 'duplicate_function',
                'name' => $functionName,
                'locations' => $locations
            ];
        }
    }
    
    return $redundantCode;
}

function getOptimizationOpportunities($pdo) {
    $opportunities = [];
    
    // Check for tables without indexes
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $indexes = $pdo->query("SHOW INDEX FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($indexes) <= 1) { // Only PRIMARY key
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $rowCount = $stmt->fetchColumn();
            
            if ($rowCount > 100) {
                $opportunities[] = [
                    'type' => 'missing_indexes',
                    'table' => $table,
                    'row_count' => $rowCount,
                    'suggestion' => 'Consider adding indexes for frequently queried columns'
                ];
            }
        }
    }
    
    // Check for large tables that might benefit from optimization
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $rowCount = $stmt->fetchColumn();
        
        if ($rowCount > 10000) {
            $opportunities[] = [
                'type' => 'large_table',
                'table' => $table,
                'row_count' => $rowCount,
                'suggestion' => 'Consider archiving old data or implementing pagination'
            ];
        }
    }
    
    return $opportunities;
}

function generateRecommendations($analysis) {
    $recommendations = [];
    
    // Empty tables recommendations
    foreach ($analysis['empty_tables'] as $table) {
        if ($table['safe_to_remove']) {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'remove_table',
                'target' => $table['name'],
                'description' => "Remove empty table '{$table['name']}' - no data and no code references",
                'impact' => 'low',
                'effort' => 'low'
            ];
        }
    }
    
    // Unused files recommendations
    foreach ($analysis['unused_files'] as $file) {
        $recommendations[] = [
            'priority' => 'low',
            'action' => 'remove_file',
            'target' => $file['path'],
            'description' => "Remove stale file: " . basename($file['path']),
            'impact' => 'very_low',
            'effort' => 'very_low'
        ];
    }
    
    // Stale comments recommendations
    if (count($analysis['stale_comments']) > 10) {
        $recommendations[] = [
            'priority' => 'low',
            'action' => 'cleanup_comments',
            'target' => 'multiple_files',
            'description' => "Clean up " . count($analysis['stale_comments']) . " stale comments (TODO, FIXME, DEBUG)",
            'impact' => 'very_low',
            'effort' => 'medium'
        ];
    }
    
    // Optimization recommendations
    foreach ($analysis['optimization_opportunities'] as $opportunity) {
        $priority = $opportunity['type'] === 'large_table' ? 'high' : 'medium';
        $recommendations[] = [
            'priority' => $priority,
            'action' => 'optimize',
            'target' => $opportunity['table'],
            'description' => $opportunity['suggestion'],
            'impact' => 'medium',
            'effort' => 'medium'
        ];
    }
    
    return $recommendations;
}

function removeEmptyTables($pdo) {
    $emptyTables = getEmptyTables($pdo);
    $removed = [];
    $errors = [];
    
    foreach ($emptyTables as $table) {
        if ($table['safe_to_remove']) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `{$table['name']}`");
                $removed[] = $table['name'];
            } catch (Exception $e) {
                $errors[] = [
                    'table' => $table['name'],
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'removed_tables' => $removed,
        'errors' => $errors,
        'message' => count($removed) . ' empty tables removed successfully'
    ]);
}

function cleanupStaleFiles() {
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

function removeUnusedCode() {
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

function optimizeDatabase($pdo) {
    $optimized = [];
    $errors = [];
    
    try {
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            try {
                // Optimize table
                $pdo->exec("OPTIMIZE TABLE `$table`");
                $optimized[] = $table;
            } catch (Exception $e) {
                $errors[] = [
                    'table' => $table,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'optimized_tables' => $optimized,
            'errors' => $errors,
            'message' => count($optimized) . ' tables optimized successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Database optimization failed: ' . $e->getMessage());
    }
}

?> 