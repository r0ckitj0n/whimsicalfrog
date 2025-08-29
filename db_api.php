<?php
/**
 * Simple Database API
 * Provides JSON endpoints for database operations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include database configuration
require_once __DIR__ . '/includes/database.php';

function connectLocal() {
    try {
<<<<<<< HEAD
        $dsn = "mysql:host=localhost;dbname=whimsicalfrog;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, 'root', 'Palz2516', $options);
=======
        return Database::getInstance();
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    } catch (PDOException $e) {
        return null;
    }
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'test-css':
        $pdo = connectLocal();
        if (!$pdo) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM global_css_rules WHERE is_active = 1");
            $count = $stmt->fetch()['count'];
            
            echo json_encode([
                'success' => true,
                'message' => "CSS Test Complete: {$count} active rules found",
                'data' => ['active_rules' => $count]
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'generate-css':
        $pdo = connectLocal();
        if (!$pdo) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        
        try {
            $stmt = $pdo->query("SELECT * FROM global_css_rules WHERE is_active = 1 ORDER BY category, rule_name");
            $rules = $stmt->fetchAll();
            
            $css = "/* Generated CSS from Database - " . date('Y-m-d H:i:s') . " */\n\n";
            $currentCategory = '';
            
            foreach ($rules as $rule) {
                if ($rule['category'] !== $currentCategory) {
                    $css .= "\n/* " . ucfirst(str_replace('_', ' ', $rule['category'])) . " */\n";
                    $currentCategory = $rule['category'];
                }
                
                // CSS Variable
                $css .= ":root {\n";
<<<<<<< HEAD
                $css .= "    --{$rule['rule_name']}: {$rule['css_value']};\n";
=======
                $css .= "    -{$rule['rule_name']}: {$rule['css_value']};\n";
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                $css .= "}\n\n";
                
                // Skip utility classes for this generation
                if (strpos($rule['rule_name'], '_utility_class') === false) {
                    $className = str_replace('_', '-', $rule['rule_name']);
                    $css .= ".{$className} {\n";
                    $css .= "    {$rule['css_property']}: {$rule['css_value']};\n";
                    $css .= "}\n\n";
                }
            }
            
            file_put_contents('generated_css_api.css', $css);
            
            echo json_encode([
                'success' => true,
                'message' => "CSS Generated: " . count($rules) . " rules processed",
                'data' => ['rules_count' => count($rules), 'file' => 'generated_css_api.css']
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'status':
        $status = [];
        
        // Local database
        try {
            $pdo = connectLocal();
            if ($pdo) {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM global_css_rules");
                $status['local'] = [
                    'online' => true,
                    'css_rules' => $stmt->fetch()['count']
                ];
            } else {
                $status['local'] = ['online' => false];
            }
        } catch (Exception $e) {
            $status['local'] = ['online' => false, 'error' => $e->getMessage()];
        }
        
        // Live database
        try {
            $liveDsn = "mysql:host=db5017975223.hosting-data.io;dbname=dbs14295502;charset=utf8mb4";
            $livePdo = new PDO($liveDsn, 'dbu2826619', 'Palz2516!', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            
            $stmt = $livePdo->query("SELECT COUNT(*) as count FROM global_css_rules");
            $status['live'] = [
                'online' => true,
                'css_rules' => $stmt->fetch()['count']
            ];
        } catch (Exception $e) {
            $status['live'] = ['online' => false, 'error' => $e->getMessage()];
        }
        
        echo json_encode(['success' => true, 'data' => $status]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
} 