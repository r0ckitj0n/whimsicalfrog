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
require_once __DIR__ . '/../includes/Constants.php';
require_once __DIR__ . '/../includes/auth_helper.php';
require_once __DIR__ . '/../includes/response.php';

try {
    // Require admin session/login
    AuthHelper::requireAdmin(403, 'Admin access required');
} catch (Throwable $e) {
    Response::json(['success' => false, 'message' => 'Forbidden'], 403);
    exit;
}

// --- Capability model ---
// Define fine-grained permissions per action.
// By default only admins can access, but some actions require elevated roles.
$ROLE_ALLOW = [
    'status' => [WF_Constants::ROLE_ADMIN, WF_Constants::ROLE_SUPERADMIN, WF_Constants::ROLE_DEVOPS],
    'version' => [WF_Constants::ROLE_ADMIN, WF_Constants::ROLE_SUPERADMIN, WF_Constants::ROLE_DEVOPS],
    'table_counts' => [WF_Constants::ROLE_ADMIN, WF_Constants::ROLE_SUPERADMIN, WF_Constants::ROLE_DEVOPS],
    'db_size' => [WF_Constants::ROLE_ADMIN, WF_Constants::ROLE_SUPERADMIN, WF_Constants::ROLE_DEVOPS],
    'list_tables' => [WF_Constants::ROLE_ADMIN, WF_Constants::ROLE_SUPERADMIN, WF_Constants::ROLE_DEVOPS],
    'describe' => [WF_Constants::ROLE_SUPERADMIN, WF_Constants::ROLE_DEVOPS],
    'query' => [WF_Constants::ROLE_ADMIN, WF_Constants::ROLE_SUPERADMIN, WF_Constants::ROLE_DEVOPS],
    'test-css' => [WF_Constants::ROLE_ADMIN, WF_Constants::ROLE_SUPERADMIN, WF_Constants::ROLE_DEVOPS],
    'generate-css' => [WF_Constants::ROLE_SUPERADMIN, WF_Constants::ROLE_DEVOPS],
];

function user_has_any_role(array $roles): bool
{
    foreach ($roles as $r) {
        if (AuthHelper::hasRole($r)) {
            return true;
        }
    }
    // If role missing, treat classic admin as lowest grant
    return AuthHelper::isAdmin() && in_array(WF_Constants::ROLE_ADMIN, $roles, true);
}

// --- CSRF guard ---
// For mutating actions, require a CSRF token header that matches session token.
function require_csrf_if_mutating(string $action): void
{
    $mutating = in_array($action, ['generate-css'], true);
    if (!$mutating) {
        return;
    }
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        // Lazily create to allow clients to fetch it first
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        http_response_code(428); // Precondition Required
        header('X-CSRF-Token: ' . $_SESSION['csrf_token']);
        Response::json(['success' => false, 'message' => 'CSRF token required', 'csrf_token' => $_SESSION['csrf_token']], 428);
        exit;
    }
    $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $hdr)) {
        Response::json(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        exit;
    }
}

function connectLocal()
{
    try {
        return Database::getInstance();
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}

function getPdoForEnv(string $env = WF_Constants::ENV_LOCAL)
{
    if ($env === WF_Constants::ENV_LOCAL || $env === 'current') {
        return connectLocal();
    }
    // live requires elevated role
    if (!user_has_any_role([WF_Constants::ROLE_SUPERADMIN, WF_Constants::ROLE_DEVOPS])) {
        return ['error' => 'Insufficient permissions for live environment'];
    }
    try {
        $cfg = wf_get_db_config(WF_Constants::ENV_LIVE);
        $pdo = Database::createConnection(
            $cfg['host'],
            $cfg['db'],
            $cfg['user'],
            $cfg['pass'],
            $cfg['port'] ?? 3306,
            $cfg['socket'] ?? null,
            [PDO::ATTR_TIMEOUT => 5]
        );
        return $pdo;
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}

$action = $_GET['action'] ?? '';
$env = $_GET['env'] ?? WF_Constants::ENV_LOCAL;

// Permission gate
if (isset($ROLE_ALLOW[$action]) && !user_has_any_role($ROLE_ALLOW[$action])) {
    Response::json(['success' => false, 'message' => 'Insufficient permissions'], 403);
    exit;
}

// CSRF for mutating actions
require_csrf_if_mutating($action);

switch ($action) {
    case 'csrf_token': {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        header('X-CSRF-Token: ' . $_SESSION['csrf_token']);
        Response::json(['success' => true, 'data' => ['csrf_token' => $_SESSION['csrf_token']]]);
        break;
    }
    case 'test-css': {
        $result = connectLocal();
        if (!($result instanceof PDO)) {
            Response::json(['success' => false, 'message' => 'Database connection failed: ' . (is_array($result) ? ($result['error'] ?? 'Unknown error') : 'Connection failed')]);
            break;
        }
        $pdo = $result;
        try {
            $row = Database::queryOne("SELECT COUNT(*) as count FROM global_css_rules WHERE is_active = 1");
            $count = (int) ($row['count'] ?? 0);
            Response::json(['success' => true, 'message' => "CSS Test Complete: {$count} active rules found", 'data' => ['active_rules' => $count]]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
    }
    case 'generate-css': {
        $pdo = connectLocal();
        if (!$pdo) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            break;
        }
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
            Response::json(['success' => true, 'message' => 'CSS Generated', 'data' => ['rules_count' => count($rules), 'file' => 'api/generated_css_api.css']]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
    }
    case 'status': {
        $status = [];

        // Always try to get local status for any admin
        try {
            $result = getPdoForEnv(WF_Constants::ENV_LOCAL);
            if ($result instanceof PDO) {
                $pdo = $result;

                // Get database info
                $verRow = QueryExecutor::queryOne($pdo, "SELECT VERSION() as version");
                $dbRow = QueryExecutor::queryOne($pdo, "SELECT DATABASE() as db");

                $cfg = wf_get_db_config(WF_Constants::ENV_LOCAL);

                $status['local'] = [
                    'online' => true,
                    'mysql_version' => $verRow['version'] ?? 'Unknown',
                    'database' => $dbRow['db'] ?? $cfg['db'],
                    'host' => $cfg['host']
                ];
            } else {
                $status['local'] = [
                    'online' => false,
                    'error' => is_array($result) ? ($result['error'] ?? 'Unknown error') : 'Connection failed'
                ];
            }
        } catch (Throwable $e) {
            $status['local'] = ['online' => false, 'error' => $e->getMessage()];
        }

        // Only try live if user has elevated permissions
        if (user_has_any_role([WF_Constants::ROLE_SUPERADMIN, WF_Constants::ROLE_DEVOPS])) {
            try {
                $result = getPdoForEnv(WF_Constants::ENV_LIVE);
                if ($result instanceof PDO) {
                    $pdo = $result;

                    // Get database info
                    $verRow = QueryExecutor::queryOne($pdo, "SELECT VERSION() as version");
                    $dbRow = QueryExecutor::queryOne($pdo, "SELECT DATABASE() as db");

                    $cfg = wf_get_db_config(WF_Constants::ENV_LIVE);

                    $status['live'] = [
                        'online' => true,
                        'mysql_version' => $verRow['version'] ?? 'Unknown',
                        'database' => $dbRow['db'] ?? $cfg['db'],
                        'host' => $cfg['host']
                    ];
                } else {
                    $status['live'] = [
                        'online' => false,
                        'error' => is_array($result) ? ($result['error'] ?? 'Unknown error') : 'Connection failed'
                    ];
                }
            } catch (Throwable $e) {
                $status['live'] = ['online' => false, 'error' => $e->getMessage()];
            }
        }

        Response::json(['success' => true, 'data' => $status]);
        break;
    }

    // --- Safe introspection endpoints ---
    case 'version': {
        $pdo = getPdoForEnv($env);
        if (!$pdo) {
            Response::json(['success' => false, 'message' => 'Database connection failed']);
            break;
        }
        $row = $pdo->query('SELECT VERSION() AS version')->fetch(PDO::FETCH_ASSOC) ?: [];
        Response::json(['success' => true, 'data' => ['version' => $row['version'] ?? null]]);
        break;
    }
    case 'table_counts': {
        $pdo = getPdoForEnv($env);
        if (!$pdo) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            break;
        }
        $dbName = wf_get_db_config($env === WF_Constants::ENV_LIVE ? WF_Constants::ENV_LIVE : WF_Constants::ENV_LOCAL)['db'] ?? '';
        $stmt = $pdo->query("SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = '" . addslashes($dbName) . "'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
        Response::json(['success' => true, 'data' => ['table_count' => (int) ($row['table_count'] ?? 0)]]);
        break;
    }
    case 'db_size': {
        $pdo = getPdoForEnv($env);
        if (!$pdo) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            break;
        }
        $dbName = wf_get_db_config($env === WF_Constants::ENV_LIVE ? WF_Constants::ENV_LIVE : WF_Constants::ENV_LOCAL)['db'] ?? '';
        $stmt = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = '" . addslashes($dbName) . "'");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : [];
        Response::json(['success' => true, 'data' => ['size_mb' => (float) ($row['size_mb'] ?? 0)]]);
        break;
    }
    case 'list_tables': {
        $pdo = getPdoForEnv($env);
        if (!$pdo) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            break;
        }
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        $names = array_slice(array_map(fn($r) => $r[0] ?? null, $tables), 0, 200);
        Response::json(['success' => true, 'data' => ['tables' => $names]]);
        break;
    }
    case 'describe': {
        // Describe table only for elevated roles
        $pdo = getPdoForEnv($env);
        if (!$pdo) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            break;
        }
        $table = $_GET['table'] ?? '';
        if (!$table || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            Response::json(['success' => false, 'message' => 'Invalid table']);
            break;
        }
        $stmt = $pdo->query("DESCRIBE `" . str_replace('`', '', $table) . "`");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        Response::json(['success' => true, 'data' => ['structure' => $rows]]);
        break;
    }
    case 'query': {
        $pdo = getPdoForEnv($env);
        if (!($pdo instanceof PDO)) {
            Response::json(['success' => false, 'message' => 'Database connection failed: ' . (is_array($pdo) ? ($pdo['error'] ?? 'Unknown error') : 'Connection failed')]);
            break;
        }

        $sql = $_REQUEST['sql'] ?? '';
        if (empty($sql)) {
            Response::json(['success' => false, 'message' => 'SQL query is empty']);
            break;
        }

        // Basic safety check: Only SELECT, SHOW, DESCRIBE allowed
        $trimmed = trim(strtoupper($sql));
        $allowed = false;
        foreach (['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'] as $verb) {
            if (strpos($trimmed, $verb) === 0) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            Response::json(['success' => false, 'message' => 'Insufficient Permissions: Only SELECT, SHOW, DESCRIBE, and EXPLAIN are allowed.']);
            break;
        }

        try {
            $stmt = $pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            Response::json(['success' => true, 'data' => ['rows' => $rows]]);
        } catch (Throwable $e) {
            Response::json(['success' => false, 'message' => 'Query Error: ' . $e->getMessage()]);
        }
        break;
    }
    default:
        Response::json(['success' => false, 'message' => 'Invalid action']);
}
