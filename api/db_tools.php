<?php
// WhimsicalFrog Database Tools API (replaces admin/db_api.php)
// JSON endpoints with admin auth guard

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/auth_helper.php';

try {
    // Require admin access
    AuthHelper::requireAdmin(403, 'Admin access required');
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

function connectLocal() {
    try {
        return Database::getInstance();
    } catch (Throwable $e) { return null; }
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'test-css': {
        $pdo = connectLocal();
        if (!$pdo) { echo json_encode(['success' => false, 'message' => 'Database connection failed']); break; }
        try {
            $row = Database::queryOne("SELECT COUNT(*) as count FROM global_css_rules WHERE is_active = 1");
            $count = (int)($row['count'] ?? 0);
            echo json_encode(['success' => true, 'message' => "CSS Test Complete: {$count} active rules found", 'data' => ['active_rules' => $count]]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
    }
    case 'generate-css': {
        $pdo = connectLocal();
        if (!$pdo) { echo json_encode(['success' => false, 'message' => 'Database connection failed']); break; }
        try {
            $rules = Database::queryAll("SELECT * FROM global_css_rules WHERE is_active = 1 ORDER BY category, rule_name");
            $css = "/* Generated CSS from Database - " . date('Y-m-d H:i:s') . " */\n\n";
            $currentCategory = '';
            foreach ($rules as $rule) {
                if (($rule['category'] ?? '') !== $currentCategory) {
                    $css .= "\n/* " . ucfirst(str_replace('_', ' ', $rule['category'])) . " */\n";
                    $currentCategory = $rule['category'];
                }
                $css .= ":root {\n    -{$rule['rule_name']}: {$rule['css_value']};\n}\n\n";
                if (strpos($rule['rule_name'], '_utility_class') === false) {
                    $className = str_replace('_', '-', $rule['rule_name']);
                    $css .= ".{$className} {\n    {$rule['css_property']}: {$rule['css_value']};\n}\n\n";
                }
            }
            file_put_contents(__DIR__ . '/generated_css_api.css', $css);
            echo json_encode(['success' => true, 'message' => 'CSS Generated', 'data' => ['rules_count' => count($rules), 'file' => 'api/generated_css_api.css']]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
    }
    case 'status': {
        $status = [];
        // Local
        try {
            $pdo = connectLocal();
            if ($pdo) {
                $row = Database::queryOne("SELECT COUNT(*) as count FROM global_css_rules");
                $status['local'] = ['online' => true, 'css_rules' => (int)($row['count'] ?? 0) ];
            } else { $status['local'] = ['online' => false]; }
        } catch (Throwable $e) { $status['local'] = ['online' => false, 'error' => $e->getMessage()]; }

        // Live using centralized config
        try {
            $liveCfg = wf_get_db_config('live');
            $livePdo = Database::createConnection(
                $liveCfg['host'], $liveCfg['db'], $liveCfg['user'], $liveCfg['pass'], $liveCfg['port'] ?? 3306, $liveCfg['socket'] ?? null, [ PDO::ATTR_TIMEOUT => 5 ]
            );
            $stmt = $livePdo->query("SELECT COUNT(*) as count FROM global_css_rules");
            $liveCount = (int)($stmt->fetch()['count'] ?? 0);
            $status['live'] = ['online' => true, 'css_rules' => $liveCount];
        } catch (Throwable $e) { $status['live'] = ['online' => false, 'error' => $e->getMessage()]; }

        echo json_encode(['success' => true, 'data' => $status]);
        break;
    }
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
