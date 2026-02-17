<?php
// api/asset_whitelist.php
// Simple CRUD API for user-controlled asset whitelist used by maintenance tools.
// POST with action=list|add|remove, payload: { pattern?: string, id?: int }
// Returns JSON { success, data?, error? }

header('Content-Type: application/json');
$ROOT = dirname(__DIR__);

try {
    // Auth guard if available
    $authPath = $ROOT . '/includes/auth.php';
    $authHelperPath = $ROOT . '/includes/auth_helper.php';
    if (file_exists($authPath)) {
        require_once $authPath;
        if (file_exists($authHelperPath)) require_once $authHelperPath;
        if (class_exists('AuthHelper')) {
            if (!AuthHelper::isLoggedIn()) throw new Exception('Not authorized');
        } elseif (function_exists('isLoggedIn')) {
            if (!isLoggedIn()) throw new Exception('Not authorized');
        }
    }

    // DB bootstrap
    require_once $ROOT . '/api/config.php';
    if (!class_exists('Database')) throw new Exception('Database class missing');
    $db = Database::getInstance();

    // Ensure table exists
    $db->execute('CREATE TABLE IF NOT EXISTS `asset_whitelist` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `pattern` VARCHAR(255) NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_pattern` (`pattern`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $input = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($input)) $input = [];

    $action = $_POST['action'] ?? $input['action'] ?? 'list';
    $out = [ 'success' => true, 'data' => null ];

    if ($action === 'list') {
        $rows = $db->query('SELECT id, pattern, created_at FROM asset_whitelist ORDER BY created_at DESC, id DESC');
        $out['data'] = $rows ?: [];
    } elseif ($action === 'add') {
        $pattern = trim((string)($input['pattern'] ?? $_POST['pattern'] ?? ''));
        if ($pattern === '') throw new Exception('pattern required');
        $db->execute('INSERT IGNORE INTO asset_whitelist (pattern) VALUES (?)', [$pattern]);
        $rows = $db->query('SELECT id, pattern, created_at FROM asset_whitelist ORDER BY created_at DESC, id DESC');
        $out['data'] = $rows ?: [];
    } elseif ($action === 'add_many') {
        $patterns = $input['patterns'] ?? [];
        if (!is_array($patterns)) throw new Exception('patterns[] required');
        $ins = $db->prepare('INSERT IGNORE INTO asset_whitelist (pattern) VALUES (?)');
        foreach ($patterns as $p) {
            $p = trim((string)$p);
            if ($p !== '') $ins->execute([$p]);
        }
        $rows = $db->query('SELECT id, pattern, created_at FROM asset_whitelist ORDER BY created_at DESC, id DESC');
        $out['data'] = $rows ?: [];
    } elseif ($action === 'remove') {
        $id = (int)($input['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('id required');
        $db->execute('DELETE FROM asset_whitelist WHERE id = ? LIMIT 1', [$id]);
        $rows = $db->query('SELECT id, pattern, created_at FROM asset_whitelist ORDER BY created_at DESC, id DESC');
        $out['data'] = $rows ?: [];
    } else {
        throw new Exception('unknown action');
    }

    echo json_encode($out);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([ 'success' => false, 'error' => $e->getMessage() ]);
}
