<?php
// DB Schema Audit API
// Scans INFORMATION_SCHEMA for tables/columns and cross-references codebase usage to find potential unused columns.
// Actions:
//  - scan: returns report of all columns with usage info and safe-to-drop candidates
//  - generate_sql: generates ALTER TABLE ... DROP COLUMN ... statements for selected columns
//  - execute: executes the DROP statements (LOCAL DEV ONLY, requires explicit confirms)

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/../includes/logger.php';
    require_once __DIR__ . '/../includes/auth_helper.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Bootstrap failed: ' . $e->getMessage()]);
    exit;
}

// --- Preset management (admin) ---
function wf_presets_dir(string $root): string { return rtrim($root, '/').'/reports/db_schema_audit/presets'; }
function wf_sanitize_preset(string $name): string { return preg_replace('/[^A-Za-z0-9_\-]+/', '_', trim($name)) ?: 'unnamed'; }

if ($action === 'list_presets') {
    try {
        $dir = wf_presets_dir($projectRoot);
        $out = [];
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if ($f === '.' || $f === '..') continue;
                if (substr($f, -5) !== '.json') continue;
                $p = $dir.'/'.$f; $name = substr($f, 0, -5);
                $out[] = [ 'name' => $name, 'path' => str_replace($projectRoot, '', $p), 'modified' => @date('c', @filemtime($p) ?: time()) ];
            }
        }
        respond(['success'=>true,'data'=>$out]);
    } catch (Throwable $e) { respond(['success'=>false,'error'=>'Failed to list presets'],500); }
}

if ($action === 'save_preset') {
    try {
        $name = wf_sanitize_preset((string)($body['name'] ?? ''));
        $data = $body['data'] ?? null;
        if (!$name || !is_array($data)) respond(['success'=>false,'error'=>'Invalid payload'],400);
        $dir = wf_presets_dir($projectRoot);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (!is_dir($dir) || !is_writable($dir)) respond(['success'=>false,'error'=>'Presets dir not writable'],500);
        $file = $dir.'/'.$name.'.json';
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        respond(['success'=>true,'data'=>['name'=>$name,'path'=>str_replace($projectRoot,'',$file)]]);
    } catch (Throwable $e) { respond(['success'=>false,'error'=>'Failed to save preset'],500); }
}

if ($action === 'get_preset') {
    try {
        $name = wf_sanitize_preset((string)($body['name'] ?? ($_GET['name'] ?? '')));
        if (!$name) respond(['success'=>false,'error'=>'Missing name'],400);
        $file = wf_presets_dir($projectRoot).'/'.$name.'.json';
        if (!is_file($file)) respond(['success'=>false,'error'=>'Not found'],404);
        $raw = @file_get_contents($file);
        $j = $raw!==false ? json_decode($raw, true) : null;
        respond(['success'=>true,'data'=>$j]);
    } catch (Throwable $e) { respond(['success'=>false,'error'=>'Failed to get preset'],500); }
}

if ($action === 'delete_preset') {
    try {
        $name = wf_sanitize_preset((string)($body['name'] ?? ''));
        if (!$name) respond(['success'=>false,'error'=>'Missing name'],400);
        $file = wf_presets_dir($projectRoot).'/'.$name.'.json';
        if (is_file($file)) @unlink($file);
        respond(['success'=>true]);
    } catch (Throwable $e) { respond(['success'=>false,'error'=>'Failed to delete preset'],500); }
}

// Get audit config (reserved lists)
if ($action === 'get_config') {
    try {
        $cfg = loadAuditConfig($projectRoot);
        respond(['success'=>true,'data'=>$cfg]);
    } catch (Throwable $e) { respond(['success'=>false,'error'=>'Failed to load config'], 500); }
}

// Save audit config (reserved lists) - admin only
if ($action === 'save_config') {
    try {
        $in = $body['data'] ?? null;
        if (!is_array($in)) respond(['success'=>false,'error'=>'Invalid payload'],400);
        $tables = isset($in['reserved_tables']) && is_array($in['reserved_tables']) ? array_values(array_unique(array_map('strval', $in['reserved_tables']))) : [];
        $cols = isset($in['reserved_columns']) && is_array($in['reserved_columns']) ? array_values(array_unique(array_map('strval', $in['reserved_columns']))) : [];
        $dir = rtrim($projectRoot, '/').'/reports/db_schema_audit';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        if (!is_dir($dir) || !is_writable($dir)) respond(['success'=>false,'error'=>'Config directory not writable'],500);
        $file = $dir.'/config.json';
        $payload = ['reserved_tables'=>$tables,'reserved_columns'=>$cols,'saved_at'=>date('c')];
        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT));
        respond(['success'=>true,'data'=>$payload]);
    } catch (Throwable $e) { respond(['success'=>false,'error'=>'Failed to save config'], 500); }
}

// List saved reports (admin only)
if ($action === 'list_reports') {
    try {
        $root = realpath(__DIR__ . '/..'); if ($root === false) { $root = dirname(__DIR__); }
        $dir = $root . '/reports/db_schema_audit';
        $out = [];
        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if ($f === '.' || $f === '..') continue;
                if (substr($f, -5) !== '.json') continue;
                $p = $dir . '/' . $f;
                $ts = substr($f, 0, -5);
                $out[] = [
                    'timestamp' => $ts,
                    'path' => str_replace($root, '', $p),
                    'bytes' => @filesize($p) ?: null,
                    'modified' => @date('c', @filemtime($p) ?: time()),
                ];
            }
            usort($out, function($a,$b){ return strcmp($b['timestamp'], $a['timestamp']); });
        }
        respond(['success'=>true,'data'=>$out]);
    } catch (Throwable $e) {
        respond(['success'=>false,'error'=>'Failed to list reports'], 500);
    }
}

// --- Additional actions (must come after $action is known) ---
if ($action === 'get_ignores') {
    try {
        $root = realpath(__DIR__ . '/..'); if ($root === false) { $root = dirname(__DIR__); }
        $dir = $root . '/reports/db_schema_audit';
        $file = $dir . '/ignore.json';
        $data = ['columns' => [], 'tables' => []];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if ($raw !== false) {
                $j = json_decode($raw, true);
                if (is_array($j)) { $data['columns'] = $j['columns'] ?? []; $data['tables'] = $j['tables'] ?? []; }
            }
        }
        respond(['success' => true, 'data' => $data]);
    } catch (Throwable $e) {
        respond(['success' => false, 'error' => 'Failed to load ignores: ' . $e->getMessage()], 500);
    }
}

if ($action === 'save_ignores') {
    try {
        $in = $body['data'] ?? null;
        if (!is_array($in)) respond(['success' => false, 'error' => 'Invalid payload'], 400);
        $columns = isset($in['columns']) && is_array($in['columns']) ? array_values(array_unique($in['columns'])) : [];
        $tables = isset($in['tables']) && is_array($in['tables']) ? array_values(array_unique($in['tables'])) : [];
        $root = realpath(__DIR__ . '/..'); if ($root === false) { $root = dirname(__DIR__); }
        $dir = $root . '/reports/db_schema_audit';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        if (!is_dir($dir) || !is_writable($dir)) {
            respond(['success' => false, 'error' => 'Ignore directory not writable: reports/db_schema_audit'], 500);
        }
        $file = $dir . '/ignore.json';
        file_put_contents($file, json_encode(['columns' => $columns, 'tables' => $tables], JSON_PRETTY_PRINT));
        respond(['success' => true, 'data' => ['columns' => $columns, 'tables' => $tables]]);
    } catch (Throwable $e) {
        respond(['success' => false, 'error' => 'Failed to save ignores: ' . $e->getMessage()], 500);
    }
}

if ($action === 'save_report') {
    try {
        $payload = $body['data'] ?? null;
        if (!$payload || !is_array($payload)) {
            respond(['success' => false, 'error' => 'Invalid payload'], 400);
        }
        $root = realpath(__DIR__ . '/..');
        if ($root === false) { $root = dirname(__DIR__); }
        $dir = $root . '/reports/db_schema_audit';
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        if (!is_dir($dir) || !is_writable($dir)) {
            respond(['success' => false, 'error' => 'Report directory not writable: reports/db_schema_audit'], 500);
        }
        $ts = date('Ymd_His');
        $file = $dir . '/' . $ts . '.json';
        $payload['saved_at'] = date('c');
        $payload['host'] = $_SERVER['HTTP_HOST'] ?? 'cli';
        file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT));
        respond(['success' => true, 'data' => ['timestamp' => $ts, 'path' => str_replace($root, '', $file)]]);
    } catch (Throwable $e) {
        respond(['success' => false, 'error' => 'Save failed: ' . $e->getMessage()], 500);
    }
}

// (handlers moved below after $action is established)

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rawBody = file_get_contents('php://input');
$body = null;
if ($rawBody) {
    try { $body = json_decode($rawBody, true); } catch (Throwable $e) { $body = null; }
}
$action = $_GET['action'] ?? ($body['action'] ?? 'scan');

// Public: whoami (no admin required)
if ($action === 'whoami') {
    try {
        $isAdmin = false;
        try { $isAdmin = AuthHelper::isAdmin(); } catch (Throwable $e) { $isAdmin = false; }
        $user = null;
        try { $user = AuthHelper::getCurrentUser(); } catch (Throwable $e) { $user = null; }
        respond(['success'=>true,'data'=>[
            'admin' => (bool)$isAdmin,
            'user' => $user,
        ]]);
    } catch (Throwable $e) {
        respond(['success'=>false,'error'=>'whoami failed'], 500);
    }
}

// Admin access: allow session-admin OR a valid admin token
$__wf_is_admin_via = 'session';
try {
    $adminToken = method_exists('AuthHelper','getAdminToken') ? AuthHelper::getAdminToken() : null;
    if (is_string($adminToken) && defined('AuthHelper::ADMIN_TOKEN') ? ($adminToken === AuthHelper::ADMIN_TOKEN) : false) {
        $__wf_is_admin_via = 'token';
    } else {
        // fall back to standard admin requirement
        AuthHelper::requireAdmin(403, 'Admin access required');
        $__wf_is_admin_via = 'session';
    }
} catch (Throwable $e) {
    respond(['success'=>false,'error'=>'Auth check failed'],403);
}

// Utility: JSON response
function respond($data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Determine project root for scanning
$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) { $projectRoot = dirname(__DIR__); }

// File scanning configuration
// Defaults; can be overridden by client in scan payload
$EXCLUDED_DIRS = [
    'backups', 'dist', 'node_modules', 'vendor', '.git', 'images', 'reports/restore_backups', 'public/assets', 'attached_assets'
];
$ALLOWED_EXTS = ['php','js','mjs','ts','tsx','sql','html','htm','cjs'];

// Timestamp helper
function wf_ts(): string {
    $d = new DateTime('now');
    return $d->format('Ymd_His');
}

// Load audit config (reserved tables/columns)
function loadAuditConfig(string $projectRoot): array {
    $cfg = ['reserved_tables' => [], 'reserved_columns' => [], 'allow_tables' => [], 'allow_columns' => []];
    try {
        $path = rtrim($projectRoot, '/').'/reports/db_schema_audit/config.json';
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                $j = json_decode($raw, true);
                if (is_array($j)) {
                    $cfg['reserved_tables'] = array_values(array_unique(array_map('strval', $j['reserved_tables'] ?? [])));
                    $cfg['reserved_columns'] = array_values(array_unique(array_map('strval', $j['reserved_columns'] ?? [])));
                    $cfg['allow_tables'] = array_values(array_unique(array_map('strval', $j['allow_tables'] ?? [])));
                    $cfg['allow_columns'] = array_values(array_unique(array_map('strval', $j['allow_columns'] ?? [])));
                }
            }
        }
    } catch (Throwable $e) { /* ignore */ }
    return $cfg;
}

// Gather schema info
function getSchema(PDO $pdo): array {
    $schema = ['tables' => [], 'columns' => [], 'table_meta' => []];
    $cols = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, COLUMN_KEY, EXTRA, ORDINAL_POSITION FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        $t = $c['TABLE_NAME'];
        if (!isset($schema['tables'][$t])) $schema['tables'][$t] = ['columns' => []];
        $schema['tables'][$t]['columns'][] = $c['COLUMN_NAME'];
        if (!isset($schema['table_meta'][$t])) $schema['table_meta'][$t] = ['name' => $t, 'row_estimate' => null, 'fk_inbound' => 0, 'fk_outbound' => 0, 'indexes' => 0];
        $schema['columns'][$t . '.' . $c['COLUMN_NAME']] = [
            'table' => $t,
            'column' => $c['COLUMN_NAME'],
            'data_type' => $c['DATA_TYPE'],
            'is_nullable' => $c['IS_NULLABLE'],
            'default' => $c['COLUMN_DEFAULT'],
            'key' => $c['COLUMN_KEY'],
            'extra' => $c['EXTRA'],
            'indexes' => [],
            'fks' => [],
        ];
    }

    // Indexes
    $idx = $pdo->query("SELECT TABLE_NAME, COLUMN_NAME, INDEX_NAME, NON_UNIQUE FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($idx as $i) {
        $k = $i['TABLE_NAME'] . '.' . $i['COLUMN_NAME'];
        if (isset($schema['columns'][$k])) {
            $schema['columns'][$k]['indexes'][] = [
                'name' => $i['INDEX_NAME'],
                'non_unique' => (int)$i['NON_UNIQUE'],
            ];
        }
        if (isset($schema['table_meta'][$i['TABLE_NAME']])) {
            $schema['table_meta'][$i['TABLE_NAME']]['indexes']++;
        }
    }

    // Foreign keys
    $fk = $pdo->query("SELECT kcu.TABLE_NAME, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
                       FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
                       WHERE kcu.TABLE_SCHEMA = DATABASE() AND kcu.REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($fk as $f) {
        $k = $f['TABLE_NAME'] . '.' . $f['COLUMN_NAME'];
        if (isset($schema['columns'][$k])) {
            $schema['columns'][$k]['fks'][] = [
                'ref_table' => $f['REFERENCED_TABLE_NAME'],
                'ref_column' => $f['REFERENCED_COLUMN_NAME'],
            ];
        }
        if (isset($schema['table_meta'][$f['TABLE_NAME']])) {
            $schema['table_meta'][$f['TABLE_NAME']]['fk_outbound']++;
        }
        if (isset($schema['table_meta'][$f['REFERENCED_TABLE_NAME']])) {
            $schema['table_meta'][$f['REFERENCED_TABLE_NAME']]['fk_inbound']++;
        }
    }

    // Table row estimates
    $rows = $pdo->query("SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()")
                 ->fetchAll(PDO::FETCH_ASSOC);
    $rowMap = [];
    $sizeMap = [];
    foreach ($rows as $r) { 
        $rowMap[$r['TABLE_NAME']] = (int)$r['TABLE_ROWS'];
        $sizeMap[$r['TABLE_NAME']] = (int)($r['DATA_LENGTH'] + $r['INDEX_LENGTH']);
    }
    foreach ($schema['tables'] as $t => &$meta) { 
        $meta['row_estimate'] = $rowMap[$t] ?? null; 
        $meta['size_bytes'] = $sizeMap[$t] ?? null;
        if (isset($schema['table_meta'][$t])) { 
            $schema['table_meta'][$t]['row_estimate'] = $rowMap[$t] ?? null; 
            $schema['table_meta'][$t]['size_bytes'] = $sizeMap[$t] ?? null; 
        } 
    }

    return $schema;
}

// Build list of files to scan
function listFiles(string $root, array $excludedDirs, array $exts): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $f) {
        if (!$f->isFile()) continue;
        $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $f->getPathname());
        // Exclude directories
        $skip = false;
        foreach ($excludedDirs as $d) {
            if (strpos($rel, $d . '/') === 0 || strpos($rel, '/' . $d . '/') !== false) { $skip = true; break; }
        }
        if ($skip) continue;
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (!in_array($ext, $exts, true)) continue;
        // Rough binary guard: skip files > 2MB
        if ($f->getSize() > 2_000_000) continue;
        $files[] = $root . DIRECTORY_SEPARATOR . $rel;
    }
    return $files;
}

// Scan files for occurrences of a token (column or table name)
function countOccurrencesInFiles(array $files, string $token, int $maxExamples = 5): array {
    $count = 0; $examples = [];
    $tokenLc = strtolower($token);
    foreach ($files as $filePath) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        // Skip minified bundles
        if (strpos($filePath, '.min.') !== false) continue;
        $contents = @file_get_contents($filePath);
        if ($contents === false) continue;
        $contentsLc = strtolower($contents);
        // Basic contains
        if (strpos($contentsLc, $tokenLc) !== false) {
            $count++;
            if (count($examples) < $maxExamples) {
                $examples[] = $filePath;
            }
        }
    }
    return ['files' => $count, 'examples' => $examples];
}

// Heuristic: determine if a column is safe-to-drop candidate based on schema and references
function isSafeCandidate(array $col, int $refCount, array $reservedTables = [], array $reservedColumns = [], array $allowTables = [], array $allowColumns = []): array {
    $reasons = [];
    $safe = true;
    if ($refCount > 0) { $safe = false; $reasons[] = 'Referenced in code (' . $refCount . ' files)'; }
    if (!empty($col['key']) && strtoupper($col['key']) === 'PRI') { $safe = false; $reasons[] = 'Primary key'; }
    if (!empty($col['fks'])) { $safe = false; $reasons[] = 'Foreign key reference'; }
    if (!empty($col['indexes'])) { $safe = false; $reasons[] = 'Indexed'; }
    $reserved = ['created_at','updated_at','deleted_at','createdon','updatedon','created_on','updated_on'];
    if (in_array(strtolower($col['column']), $reserved, true)) { $safe = false; $reasons[] = 'Conventional timestamp'; }
    // Config-reserved checks
    $t = strtolower($col['table'] ?? '');
    $c = strtolower($col['column'] ?? '');
    $reservedTablesLc = array_map('strtolower', $reservedTables);
    $reservedColumnsLc = array_map('strtolower', $reservedColumns);
    $allowTablesLc = array_map('strtolower', $allowTables);
    $allowColumnsLc = array_map('strtolower', $allowColumns);
    if ($t && in_array($t, $reservedTablesLc, true)) { $safe = false; $reasons[] = 'Reserved by config (table)'; }
    if ($c && (in_array($c, $reservedColumnsLc, true) || ($t && in_array($t.'.'.$c, $reservedColumnsLc, true)))) {
        $safe = false; $reasons[] = 'Reserved by config (column)';
    }
    // *_id heuristic: likely foreign key pattern; allowlist can override
    if ($c && str_ends_with($c, '_id')) {
        $allowed = in_array($c, $allowColumnsLc, true) || ($t && in_array($t.'.'.$c, $allowColumnsLc, true)) || ($t && in_array($t, $allowTablesLc, true));
        if (!$allowed) { $safe = false; $reasons[] = 'Likely foreign key pattern (*_id)'; }
    }
    return [$safe, $reasons];
}

try {
    $pdo = Database::getInstance();
} catch (Throwable $e) {
    respond(['success' => false, 'error' => 'DB connection failed: ' . $e->getMessage()], 500);
}

if ($action === 'scan') {
    try {
        @set_time_limit(300);
        $schema = getSchema($pdo);
        // Allow client to customize excludes/exts in request body (optional)
        $clientExcludes = $body['excludes'] ?? [];
        $clientExts = $body['exts'] ?? [];
        if (is_array($clientExcludes) && !empty($clientExcludes)) { $EXCLUDED_DIRS = array_values(array_unique(array_map('strval', array_merge($EXCLUDED_DIRS, $clientExcludes)))); }
        if (is_array($clientExts) && !empty($clientExts)) { $ALLOWED_EXTS = array_values(array_unique(array_map('strtolower', $clientExts))); }
        $files = listFiles($projectRoot, $EXCLUDED_DIRS, $ALLOWED_EXTS);
        $cfg = loadAuditConfig($projectRoot);
        $reservedTables = $cfg['reserved_tables'] ?? [];
        $reservedColumns = $cfg['reserved_columns'] ?? [];
        $allowTables = $cfg['allow_tables'] ?? [];
        $allowColumns = $cfg['allow_columns'] ?? [];

        $results = [];
        $candidates = 0;
        $tableRefCounts = [];
        // Precompute table reference counts
        foreach (array_keys($schema['tables']) as $tableName) {
            $occT = countOccurrencesInFiles($files, $tableName);
            $tableRefCounts[$tableName] = (int)$occT['files'];
        }
        foreach ($schema['columns'] as $k => $meta) {
            // Skip very short / generic column names to reduce noise when assessing zero refs only
            if (strlen($meta['column']) < 3) {
                $occ = ['files' => 0, 'examples' => []];
            } else {
                $occ = countOccurrencesInFiles($files, $meta['column']);
                // If none, try table-qualified token (table.column)
                if ($occ['files'] === 0) {
                    $occ = countOccurrencesInFiles($files, $meta['table'] . '.' . $meta['column']);
                }
                // If still none, try bracket/array key pattern like ['column'] (approx)
                if ($occ['files'] === 0) {
                    $occ = countOccurrencesInFiles($files, "['" . $meta['column'] . "']");
                }
            }
            list($safe, $reasons) = isSafeCandidate($meta, (int)$occ['files'], $reservedTables, $reservedColumns, $allowTables, $allowColumns);
            if ($safe) $candidates++;
            $results[] = [
                'table' => $meta['table'],
                'column' => $meta['column'],
                'data_type' => $meta['data_type'],
                'key' => $meta['key'],
                'extra' => $meta['extra'],
                'index_count' => count($meta['indexes'] ?? []),
                'fk_count' => count($meta['fks'] ?? []),
                'ref_files' => (int)$occ['files'],
                'examples' => $occ['examples'],
                'safe_candidate' => $safe,
                'not_safe_reasons' => $safe ? [] : $reasons,
            ];
        }

        // Unused tables heuristic
        $unusedTables = [];
        foreach ($schema['table_meta'] as $t => $tm) {
            $refs = (int)($tableRefCounts[$t] ?? 0);
            $inbound = (int)($tm['fk_inbound'] ?? 0);
            $outbound = (int)($tm['fk_outbound'] ?? 0);
            $idxs = (int)($tm['indexes'] ?? 0);
            $rows = (int)($tm['row_estimate'] ?? 0);
            $size = (int)($tm['size_bytes'] ?? 0);
            $colsCount = isset($schema['tables'][$t]['columns']) ? count($schema['tables'][$t]['columns']) : 0;
            $safe = ($refs === 0) && ($inbound === 0) && ($outbound === 0);
            $reasons = [];
            if ($refs > 0) $reasons[] = 'Referenced in code (' . $refs . ' files)';
            if ($inbound > 0) $reasons[] = 'Inbound foreign keys';
            if ($outbound > 0) $reasons[] = 'Outbound foreign keys';
            $unusedTables[] = [
                'table' => $t,
                'row_estimate' => $rows,
                'size_bytes' => $size,
                'columns' => $colsCount,
                'ref_files' => $refs,
                'fk_inbound' => $inbound,
                'fk_outbound' => $outbound,
                'indexes' => $idxs,
                'safe_candidate' => $safe,
                'not_safe_reasons' => $safe ? [] : $reasons,
            ];
        }

        respond(['success' => true, 'data' => [
            'tables' => array_keys($schema['tables']),
            'summary' => ['columns' => count($schema['columns']), 'candidates' => $candidates, 'tables' => count($schema['tables'])],
            'columns' => $results,
            'unused_tables' => $unusedTables,
            'scan_config' => [ 'excludes' => $EXCLUDED_DIRS, 'exts' => $ALLOWED_EXTS ],
        ]]);
    } catch (Throwable $e) {
        Logger::exception('db_schema_audit.scan_failed', $e, ['endpoint' => 'db_schema_audit', 'action' => 'scan']);
        respond(['success' => false, 'error' => 'Scan failed: ' . $e->getMessage()], 500);
    }
}

if ($action === 'generate_sql') {
    try {
        $columns = $body['columns'] ?? ($_POST['columns'] ?? []);
        $tables = $body['tables'] ?? ($_POST['tables'] ?? []);
        $stmts = [];
        if (is_array($columns)) {
            foreach ($columns as $c) {
                $t = $c['table'] ?? '';
                $col = $c['column'] ?? '';
                if (!$t || !$col) continue;
                $stmts[] = sprintf('ALTER TABLE `%s` DROP COLUMN `%s`;', str_replace('`','',$t), str_replace('`','',$col));
            }
        }
        if (is_array($tables)) {
            foreach ($tables as $t) {
                $tn = is_array($t) ? ($t['table'] ?? '') : (string)$t;
                if (!$tn) continue;
                $stmts[] = sprintf('DROP TABLE `%s`;', str_replace('`','',$tn));
            }
        }
        // Build dependency warnings by scanning views/routines/triggers for table mentions
        $warnings = [];
        try {
            $tablesSet = [];
            if (is_array($columns)) { foreach ($columns as $c) { if (!empty($c['table'])) $tablesSet[$c['table']] = true; } }
            if (is_array($tables)) { foreach ($tables as $t) { $tn = is_array($t) ? ($t['table'] ?? '') : (string)$t; if ($tn) $tablesSet[$tn] = true; } }
            $tablesList = array_keys($tablesSet);
            if (!empty($tablesList)) {
                $likeClauses = array_map(function($t){ return "VIEW_DEFINITION LIKE '%" . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $t) . "%'"; }, $tablesList);
                $sqlViews = 'SELECT TABLE_NAME, VIEW_DEFINITION FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND (' . implode(' OR ', $likeClauses) . ')';
                $viewHits = $pdo->query($sqlViews)->fetchAll(PDO::FETCH_ASSOC);
                foreach ($viewHits as $vh) { $warnings[] = 'View ' . $vh['TABLE_NAME'] . ' may reference selected tables.'; }

                $likeClausesR = array_map(function($t){ return "ROUTINE_DEFINITION LIKE '%" . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $t) . "%'"; }, $tablesList);
                $sqlR = 'SELECT ROUTINE_NAME, ROUTINE_TYPE FROM INFORMATION_SCHEMA.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND (' . implode(' OR ', $likeClausesR) . ')';
                $routineHits = $pdo->query($sqlR)->fetchAll(PDO::FETCH_ASSOC);
                foreach ($routineHits as $rh) { $warnings[] = $rh['ROUTINE_TYPE'] . ' ' . $rh['ROUTINE_NAME'] . ' may reference selected tables.'; }

                $likeClausesT = array_map(function($t){ return "ACTION_STATEMENT LIKE '%" . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $t) . "%'"; }, $tablesList);
                $sqlT = 'SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND (' . implode(' OR ', $likeClausesT) . ')';
                $trigHits = $pdo->query($sqlT)->fetchAll(PDO::FETCH_ASSOC);
                foreach ($trigHits as $th) { $warnings[] = 'Trigger ' . $th['TRIGGER_NAME'] . ' may reference selected tables.'; }
            }
        } catch (Throwable $e) { /* best-effort */ }

        $sql = implode("\n", $stmts);
        respond(['success' => true, 'data' => ['sql' => $sql, 'count' => count($stmts), 'warnings' => $warnings]]);
    } catch (Throwable $e) {
        respond(['success' => false, 'error' => 'Failed to generate SQL: ' . $e->getMessage()], 500);
    }
}

if ($action === 'execute') {
    // HARD GUARD: Only allow on localhost and only via POST with explicit confirms
    $isLocal = false;
    try {
        $hostHeader = $_SERVER['HTTP_HOST'] ?? '';
        $isLocal = (PHP_SAPI === 'cli') || (strpos($hostHeader, 'localhost') !== false) || (strpos($hostHeader, '127.0.0.1') !== false);
    } catch (Throwable $e) { $isLocal = false; }
    if (!$isLocal || $method !== 'POST') {
        respond(['success' => false, 'error' => 'Execution allowed only on local via POST'], 403);
    }

    $columns = $body['columns'] ?? ($_POST['columns'] ?? []);
    $tables = $body['tables'] ?? ($_POST['tables'] ?? []);
    $confirm = $body['confirm'] ?? ($_POST['confirm'] ?? '');
    $backupAck = (bool)($body['backup_ack'] ?? ($_POST['backup_ack'] ?? false));

    if ((!is_array($columns) || empty($columns)) && (!is_array($tables) || empty($tables))) {
        respond(['success' => false, 'error' => 'No columns or tables provided'], 400);
    }
    if ($confirm !== 'DROP' || !$backupAck) {
        respond(['success' => false, 'error' => 'Missing required confirmations'], 400);
    }

    $errors = [];
    $executed = 0;
    try {
        Database::beginTransaction();
        if (is_array($columns)) {
            foreach ($columns as $c) {
                $t = $c['table'] ?? '';
                $col = $c['column'] ?? '';
                if (!$t || !$col) continue;
                $sql = sprintf('ALTER TABLE `%s` DROP COLUMN `%s`', str_replace('`','',$t), str_replace('`','',$col));
                try {
                    $pdo->exec($sql);
                    $executed++;
                } catch (Throwable $e) {
                    $errors[] = ['table' => $t, 'column' => $col, 'error' => $e->getMessage()];
                }
            }
        }
        if (is_array($tables)) {
            foreach ($tables as $t) {
                $tn = is_array($t) ? ($t['table'] ?? '') : (string)$t;
                if (!$tn) continue;
                $sql = sprintf('DROP TABLE `%s`', str_replace('`','',$tn));
                try {
                    $pdo->exec($sql);
                    $executed++;
                } catch (Throwable $e) {
                    $errors[] = ['table' => $tn, 'error' => $e->getMessage()];
                }
            }
        }
        if (!empty($errors)) {
            Database::rollBack();
            respond(['success' => false, 'error' => 'Execution aborted; no changes applied', 'details' => $errors], 400);
        }
        Database::commit();
        respond(['success' => true, 'data' => ['executed' => $executed]]);
    } catch (Throwable $e) {
        try { Database::rollBack(); } catch (Throwable $e2) {}
        Logger::exception('db_schema_audit.execute_failed', $e, ['endpoint' => 'db_schema_audit', 'action' => 'execute']);
        respond(['success' => false, 'error' => 'Execution failed: ' . $e->getMessage()], 500);
    }
}

respond(['success' => false, 'error' => 'Unknown action'], 400);
