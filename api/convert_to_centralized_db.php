<?php
/**
 * Centralized Database Migration Script
 *
 * This script automatically converts all files from direct PDO connections
 * to use the centralized Database class for improved security and maintainability.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/auth_helper.php';

// Security check: require admin for web requests; allow CLI
if (PHP_SAPI !== 'cli') {
    AuthHelper::requireAdmin();
}

// Get project root directory
$projectRoot = dirname(__DIR__);

// Define patterns to search for and their replacements
$patterns = [
    // Direct PDO instantiation with variables
    '/\$pdo = new PDO\(\$dsn, \$user, \$pass, \$options\);/' => 'try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }',

    // PDO with different variable names
    '/\$tempPdo = new PDO\(\$dsn, \$user, \$pass, \$options\);/' => 'try { $tempPdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }',

    // PDO with direct connection string (common pattern)
    '/\$pdo = new PDO\("mysql:host=\$host;dbname=\$dbname;charset=utf8mb4", \$username, \$password\);/' => 'try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }',

    // Other PDO instantiation patterns with arrays
    '/\$pdo = new PDO\(\$dsn, \$user, \$pass, \[[\s\S]*?\]\);/' => 'try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }',

    // Generic PDO pattern (more flexible)
    '/\$\w+ = new PDO\([^;]+\);/' => 'try { $pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }',

    // Self PDO assignment in classes
    '/self::\$pdo = new PDO\(\$dsn, \$user, \$pass, \$options\);/' => 'try { self::$pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }',

    // This PDO assignment in classes
    '/\$this->pdo = new PDO\(\$dsn, \$user, \$pass, \$options\);/' => 'try { $this->pdo = Database::getInstance(); } catch (Exception $e) { error_log("Database connection failed: " . $e->getMessage()); throw $e; }',
];

// Files to exclude from conversion
$excludeFiles = [
    'api/config.php',
    'includes/database.php',
    'api/convert_to_centralized_db.php',
    'sync_database_smart.php',
    'api/database_maintenance.php'
];

/**
 * Scan directory for PHP files
 */
function scanForPHPFiles($directory)
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/**
 * Check if file contains direct PDO connections
 */
function containsDirectPDO($filePath)
{
    $content = file_get_contents($filePath);
    return preg_match('/new PDO\(\$dsn/', $content) ||
           preg_match('/new PDO\("mysql:/', $content);
}

/**
 * Add Database include if not present
 */
function ensureDatabaseInclude($content, $filePath)
{
    // Check if Database class is already included
    if (strpos($content, 'Database::') !== false &&
        strpos($content, 'require_once') === false &&
        strpos($content, 'include') === false) {

        // Add include at the top after <?php
        $content = preg_replace(
            '/(<\?php\s*(?:\/\*[\s\S]*?\*\/)?\s*)/',
            "$1\nrequire_once __DIR__ . '/../includes/functions.php';\n",
            $content,
            1
        );
    }

    return $content;
}

/**
 * Convert file to use centralized database
 */
function convertFile($filePath, $patterns)
{
    global $excludeFiles, $projectRoot;

    // Check if file should be excluded
    $relativePath = str_replace($projectRoot . '/', '', $filePath);
    if (in_array($relativePath, $excludeFiles)) {
        return ['success' => false, 'message' => 'File excluded from conversion'];
    }

    // Read file content
    $content = file_get_contents($filePath);
    if ($content === false) {
        return ['success' => false, 'message' => 'Could not read file'];
    }

    // Check if file needs conversion
    if (!containsDirectPDO($filePath)) {
        return ['success' => false, 'message' => 'No direct PDO connections found'];
    }

    // Create backup
    $backupPath = $filePath . '.backup.' . date('Y-m-d_H-i-s');
    if (!copy($filePath, $backupPath)) {
        return ['success' => false, 'message' => 'Could not create backup'];
    }

    $originalContent = $content;
    $changes = 0;

    // Apply pattern replacements
    foreach ($patterns as $pattern => $replacement) {
        $newContent = preg_replace($pattern, $replacement, $content);
        if ($newContent !== $content) {
            $content = $newContent;
            $changes++;
        }
    }

    // Ensure Database include is present if needed
    if ($changes > 0) {
        $content = ensureDatabaseInclude($content, $filePath);

        // Write converted content
        if (file_put_contents($filePath, $content) === false) {
            // Restore from backup if write fails
            copy($backupPath, $filePath);
            unlink($backupPath);
            return ['success' => false, 'message' => 'Could not write converted file'];
        }

        return [
            'success' => true,
            'message' => "Converted with $changes changes",
            'backup' => $backupPath,
            'changes' => $changes
        ];
    }

    // Remove backup if no changes were made
    unlink($backupPath);
    return ['success' => false, 'message' => 'No patterns matched for conversion'];
}

// Main execution
if (PHP_SAPI === 'cli' || isset($_GET['action'])) {
    $action = PHP_SAPI === 'cli' ? ($argv[1] ?? 'scan') : ($_GET['action'] ?? 'scan');

    switch ($action) {
        case 'scan':
            // Scan for files that need conversion
            $phpFiles = scanForPHPFiles($projectRoot);
            $needsConversion = [];

            foreach ($phpFiles as $file) {
                $relativePath = str_replace($projectRoot . '/', '', $file);
                if (!in_array($relativePath, $excludeFiles) && containsDirectPDO($file)) {
                    $needsConversion[] = $relativePath;
                }
            }

            if (isset($_GET['format']) && $_GET['format'] === 'json') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'total_files' => count($phpFiles),
                    'needs_conversion' => count($needsConversion),
                    'files' => $needsConversion
                ]);
            } else {
                echo "Database Connection Audit Report\n";
                echo "================================\n\n";
                echo "Total PHP files scanned: " . count($phpFiles) . "\n";
                echo "Files needing conversion: " . count($needsConversion) . "\n\n";

                if (!empty($needsConversion)) {
                    echo "Files with direct PDO connections:\n";
                    foreach ($needsConversion as $file) {
                        echo "  - $file\n";
                    }
                    echo "\nRun with action=convert to perform conversion.\n";
                } else {
                    echo "✅ All files are already using centralized database connections!\n";
                }
            }
            break;

        case 'convert':
            // Convert all files
            $phpFiles = scanForPHPFiles($projectRoot);
            $results = [];
            $converted = 0;
            $failed = 0;

            foreach ($phpFiles as $file) {
                $relativePath = str_replace($projectRoot . '/', '', $file);
                $result = convertFile($file, $patterns);

                if ($result['success']) {
                    $converted++;
                    $results[] = [
                        'file' => $relativePath,
                        'status' => 'converted',
                        'changes' => $result['changes'],
                        'backup' => str_replace($projectRoot . '/', '', $result['backup'])
                    ];
                } else {
                    if ($result['message'] !== 'No direct PDO connections found' &&
                        $result['message'] !== 'File excluded from conversion') {
                        $failed++;
                        $results[] = [
                            'file' => $relativePath,
                            'status' => 'failed',
                            'error' => $result['message']
                        ];
                    }
                }
            }

            if (isset($_GET['format']) && $_GET['format'] === 'json') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'converted' => $converted,
                    'failed' => $failed,
                    'results' => $results
                ]);
            } else {
                echo "Database Conversion Results\n";
                echo "===========================\n\n";
                echo "Files converted: $converted\n";
                echo "Conversion failures: $failed\n\n";

                foreach ($results as $result) {
                    if ($result['status'] === 'converted') {
                        echo "✅ {$result['file']} - {$result['changes']} changes (backup: {$result['backup']})\n";
                    } elseif ($result['status'] === 'failed') {
                        echo "❌ {$result['file']} - {$result['error']}\n";
                    }
                }

                if ($converted > 0) {
                    echo "\n🎉 Conversion completed! All files now use centralized Database class.\n";
                    echo "💾 Backups were created for all modified files.\n";
                    echo "🧪 Please test your application to ensure everything works correctly.\n";
                }
            }
            break;

        case 'test':
            // Test centralized database connection
            try {
                $pdo = Database::getInstance();
                $result = Database::queryOne("SELECT 1 as test");

                if ($result && $result['test'] == 1) {
                    if (isset($_GET['format']) && $_GET['format'] === 'json') {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'message' => 'Centralized database connection working correctly'
                        ]);
                    } else {
                        echo "✅ Centralized database connection test successful!\n";
                    }
                } else {
                    throw new Exception('Test query failed');
                }
            } catch (Exception $e) {
                if (isset($_GET['format']) && $_GET['format'] === 'json') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Database connection failed: ' . $e->getMessage()
                    ]);
                } else {
                    echo "❌ Database connection test failed: " . $e->getMessage() . "\n";
                }
            }
            break;

        default:
            echo "Invalid action. Use: scan, convert, or test\n";
    }
} else {
    // Web interface
    header('Content-Type: text/html');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Centralization Tool</title>
        
    </head>
    <body>
        <h1>🗄️ Database Centralization Tool</h1>
        <p>This tool helps convert your codebase from direct PDO connections to use the centralized Database class.</p>
        
        <div>
            <button class="button" data-action="performAction" data-params='{"action":"scan"}'>📊 Scan Files</button>
            <button class="button" data-action="confirmAndPerform" data-params='{"action":"convert","message":"This will modify files and create backups. Continue?"}'>
                🔄 Convert Files
            </button>
            <button class="button" data-action="performAction" data-params='{"action":"test"}'>🧪 Test Connection</button>
        </div>
        
        <div id="results" class="results display-none"></div>
        
        <script>
        async function performAction(action) {
            const resultsDiv = document.getElementById('results');
            resultsDiv.style.display = 'block';
            resultsDiv.innerHTML = '<p>Loading...</p>';
            
            try {
                const response = await fetch(`?action=${action}&format=json&admin_token=whimsical_admin_2024`);
                const data = await response.json();
                
                if (data.success) {
                    if (action === 'scan') {
                        resultsDiv.innerHTML = `
                            <h3>📊 Scan Results</h3>
                            <p><strong>Total PHP files:</strong> ${data.total_files}</p>
                            <p><strong>Files needing conversion:</strong> ${data.needs_conversion}</p>
                            ${data.files.length > 0 ? `
                                <h4>Files with direct PDO connections:</h4>
                                <ul>${data.files.map(f => `<li>${f}</li>`).join('')}</ul>
                            ` : '<p class="success">✅ All files already use centralized database!</p>'}
                        `;
                    } else if (action === 'convert') {
                        resultsDiv.innerHTML = `
                            <h3>🔄 Conversion Results</h3>
                            <p><strong>Files converted:</strong> ${data.converted}</p>
                            <p><strong>Failures:</strong> ${data.failed}</p>
                            <h4>Details:</h4>
                            <ul>
                                ${data.results.map(r => 
                                    r.status === 'converted' 
                                        ? `<li class="success">✅ ${r.file} - ${r.changes} changes</li>`
                                        : `<li class="error">❌ ${r.file} - ${r.error}</li>`
                                ).join('')}
                            </ul>
                        `;
                    } else if (action === 'test') {
                        resultsDiv.innerHTML = `<p class="success">✅ ${data.message}</p>`;
                    }
                } else {
                    resultsDiv.innerHTML = `<p class="error">❌ Error: ${data.message}</p>`;
                }
            } catch (error) {
                resultsDiv.innerHTML = `<p class="error">❌ Network error: ${error.message}</p>`;
            }
        }
        </script>
    </body>
    </html>
    <?php
}
?> 