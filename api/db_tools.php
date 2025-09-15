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
    // Require admin session/login
    AuthHelper::requireAdmin(403, 'Admin access required');
} catch (Throwable $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

// --- Capability model ---
// Define fine-grained permissions per action.
// By default only admins can access, but some actions require elevated roles.
$ROLE_ALLOW = [
    'status'        => ['admin','superadmin','devops'],
    'version'       => ['admin','superadmin','devops'],
    'table_counts'  => ['admin','superadmin','devops'],
    'db_size'       => ['admin','superadmin','devops'],
    'list_tables'   => ['admin','superadmin','devops'],
    'describe'      => ['superadmin','devops'],
    'test-css'      => ['admin','superadmin','devops'],
    'generate-css'  => ['superadmin','devops'],
];

function user_has_any_role(array $roles): bool {
    foreach ($roles as $r) { if (AuthHelper::hasRole($r)) return true; }
    // If role missing, treat classic admin as lowest grant
    return AuthHelper::isAdmin() && in_array('admin', $roles, true);
}

// --- CSRF guard ---
// For mutating actions, require a CSRF token header that matches session token.
function require_csrf_if_mutating(string $action): void {
    $mutating = in_array($action, ['generate-css'], true);
    if (!$mutating) return;
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }
    if (empty($_SESSION['csrf_token'])) {
        // Lazily create to allow clients to fetch it first
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        http_response_code(428); // Precondition Required
        header('X-CSRF-Token: ' . $_SESSION['csrf_token']);
        echo json_encode(['success' => false, 'message' => 'CSRF token required', 'csrf_token' => $_SESSION['csrf_token']]);
        exit;
    }
    $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $hdr)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

function connectLocal() {
    try {
        return Database::getInstance();
    } catch (Throwable $e) { return null; }
}

function getPdoForEnv(string $env = 'local') {
    if ($env === 'local' || $env === 'current') { return connectLocal(); }
    // live requires elevated role
    if (!user_has_any_role(['superadmin','devops'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions for live environment']);
        exit;
    }
    try {
        $cfg = wf_get_db_config('live');
        return Database::createConnection(
            $cfg['host'], $cfg['db'], $cfg['user'], $cfg['pass'], $cfg['port'] ?? 3306, $cfg['socket'] ?? null, [ PDO::ATTR_TIMEOUT => 5 ]
        );
    } catch (Throwable $e) { return null; }
}

$action = $_GET['action'] ?? '';
$env = $_GET['env'] ?? 'local';

// Permission gate
if (isset($ROLE_ALLOW[$action]) && !user_has_any_role($ROLE_ALLOW[$action])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// CSRF for mutating actions
require_csrf_if_mutating($action);

switch ($action) {
    case 'csrf_token': {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        header('X-CSRF-Token: ' . $_SESSION['csrf_token']);
        echo json_encode(['success' => true, 'data' => ['csrf_token' => $_SESSION['csrf_token']]]);
        break;
    }
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
        foreach (['local','live'] as $target) {
            if ($target === 'live' && !user_has_any_role(['superadmin','devops'])) { continue; }
            try {
                $pdo = ($target === 'local') ? connectLocal() : getPdoForEnv('live');
                if ($pdo) {
                    $row = Database::queryOne("SELECT COUNT(*) as count FROM global_css_rules");
                    $status[$target] = ['online' => true, 'css_rules' => (int)($row['count'] ?? 0) ];
                } else { $status[$target] = ['online' => false]; }
            } catch (Throwable $e) { $status[$target] = ['online' => false, 'error' => $e->getMessage()]; }
        }
        echo json_encode(['success' => true, 'data' => $status]);
        break;
    }

    // --- Safe introspection endpoints ---
    case 'version': {
        $pdo = getPdoForEnv($env);
        if (!$pdo) { echo json_encode(['success'=>false,'message'=>'Database connection failed']); break; }
        $row = $pdo->query('SELECT VERSION() AS version')->fetch(PDO::FETCH_ASSOC) ?: [];
        echo json_encode(['success'=>true,'data'=>['version'=>$row['version'] ?? null]]);
        break;
    }
    case 'table_counts': {
        $pdo = getPdoForEnv($env);
        if (!$pdo) { echo json_encode(['success'=>false,'message'=>'Database connection failed']); break; }
        $dbName = wf_get_db_config($env === 'live' ? 'live' : 'local')['db'] ?? '';
        $stmt = $pdo->query("SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = '" . addslashes($dbName) . "'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
        echo json_encode(['success'=>true,'data'=>['table_count'=>(int)($row['table_count'] ?? 0)]]);
        break;
    }
    case 'db_size': {
        $pdo = getPdoForEnv($env);
        if (!$pdo) { echo json_encode(['success'=>false,'message'=>'Database connection failed']); break; }
        $dbName = wf_get_db_config($env === 'live' ? 'live' : 'local')['db'] ?? '';
        $stmt = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = '" . addslashes($dbName) . "'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
        echo json_encode(['success'=>true,'data'=>['size_mb'=>(float)($row['size_mb'] ?? 0)]]);
        break;
    }
    case 'list_tables': {
        $pdo = getPdoForEnv($env);
        if (!$pdo) { echo json_encode(['success'=>false,'message'=>'Database connection failed']); break; }
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        $names = array_slice(array_map(fn($r)=>$r[0] ?? null, $tables), 0, 200);
        echo json_encode(['success'=>true,'data'=>['tables'=>$names]]);
        break;
    }
    case 'describe': {
        // Describe table only for elevated roles
        $pdo = getPdoForEnv($env);
        if (!$pdo) { echo json_encode(['success'=>false,'message'=>'Database connection failed']); break; }
        $table = $_GET['table'] ?? '';
        if (!$table || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            echo json_encode(['success'=>false,'message'=>'Invalid table']);
            break;
        }
        $stmt = $pdo->query("DESCRIBE `" . str_replace('`','', $table) . "`");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        echo json_encode(['success'=>true,'data'=>['structure'=>$rows]]);
        break;
    }
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
