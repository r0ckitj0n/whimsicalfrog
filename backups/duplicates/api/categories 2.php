<?php
// Categories API: list/add/update/delete with safe migrations
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

function respond($ok, $data = null, $error = null, $code = 200) {
    http_response_code($code);
    echo json_encode([ 'success' => $ok, 'data' => $data, 'error' => $error ]);
    exit;
}

if (!isAdminWithToken()) {
    respond(false, null, 'Unauthorized', 401);
}

// Utilities
function wf_trim($v) { return is_string($v) ? trim($v) : $v; }
function wf_code_from_name($name) {
    $map = [
        'T-Shirts' => 'TS',
        'Tumblers' => 'TU',
        'Artwork' => 'AR',
        'Sublimation' => 'SU',
        'WindowWraps' => 'WW'
    ];
    if (isset($map[$name])) return $map[$name];
    $s = preg_replace('/[^A-Za-z0-9]/', '', (string)$name);
    $s = strtoupper($s);
    if ($s === '') return 'XX';
    return substr($s, 0, 2);
}
function ensure_categories_table() {
    try {
        $exists = Database::queryOne("SHOW TABLES LIKE 'categories'");
        if (empty($exists)) {
            Database::execute("CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL UNIQUE,
                code VARCHAR(16) NOT NULL UNIQUE,
                slug VARCHAR(191) NULL UNIQUE,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // Schema migration: ensure required columns exist (legacy tables may lack these)
        $cols = Database::queryAll("SHOW COLUMNS FROM categories");
        $have = [];
        foreach ($cols as $c) { $have[strtolower($c['Field'])] = true; }

        // Add missing columns in a backwards-compatible way
        if (empty($have['code'])) {
            try { Database::execute("ALTER TABLE categories ADD COLUMN code VARCHAR(16) NULL AFTER name"); } catch (Exception $e) {}
        }
        if (empty($have['slug'])) {
            try { Database::execute("ALTER TABLE categories ADD COLUMN slug VARCHAR(191) NULL AFTER code"); } catch (Exception $e) {}
        }
        if (empty($have['is_active'])) {
            try { Database::execute("ALTER TABLE categories ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER slug"); } catch (Exception $e) {}
        }

        // Backfill code/slug if null
        try {
            $rows = Database::queryAll("SELECT id, name, code, slug FROM categories");
            // Build existing code set to avoid duplicates
            $existingCodes = [];
            foreach ($rows as $r) {
                $code = strtoupper(trim((string)($r['code'] ?? '')));
                if ($code !== '') $existingCodes[$code] = true;
            }
            foreach ($rows as $r) {
                $id = (int)$r['id'];
                $name = trim((string)$r['name']);
                $code = strtoupper(trim((string)($r['code'] ?? '')));
                $slug = trim((string)($r['slug'] ?? ''));
                $needUpdate = false; $newCode = $code; $newSlug = $slug;
                if ($newCode === '') {
                    $base = wf_code_from_name($name);
                    $candidate = $base; $i = 1;
                    while (isset($existingCodes[$candidate])) { $i++; $candidate = $base . $i; if ($i > 99) break; }
                    $newCode = $candidate; $existingCodes[$candidate] = true; $needUpdate = true;
                }
                if ($newSlug === '' && $name !== '') { $newSlug = strtolower(preg_replace('/[^a-z0-9]+/i','-', $name)); $needUpdate = true; }
                if ($needUpdate) {
                    try { Database::execute("UPDATE categories SET code = ?, slug = ? WHERE id = ?", [ $newCode, $newSlug, $id ]); } catch (Exception $e) {}
                }
            }
            // Enforce NOT NULL and UNIQUE constraints safely
            try { Database::execute("ALTER TABLE categories MODIFY code VARCHAR(16) NOT NULL"); } catch (Exception $e) {}
            try { Database::execute("ALTER TABLE categories ADD UNIQUE INDEX idx_categories_code (code)"); } catch (Exception $e) {}
            try { Database::execute("ALTER TABLE categories ADD UNIQUE INDEX idx_categories_slug (slug)"); } catch (Exception $e) {}
        } catch (Exception $e) { /* ignore */ }

        // Seed from existing items.category if table is empty
        $countRow = Database::queryOne("SELECT COUNT(*) AS c FROM categories");
        $hasAny = (int)($countRow['c'] ?? 0) > 0;
        if (!$hasAny) {
            $rows = Database::queryAll("SELECT DISTINCT category AS name FROM items WHERE category IS NOT NULL AND category <> '' ORDER BY category");
            foreach ($rows as $r) {
                $name = wf_trim($r['name'] ?? '');
                if ($name === '') continue;
                $code = wf_code_from_name($name);
                try {
                    Database::execute("INSERT IGNORE INTO categories (name, code, slug) VALUES (?, ?, ?)", [ $name, $code, strtolower(preg_replace('/[^a-z0-9]+/i','-', $name)) ]);
                } catch (Exception $e) { /* ignore seed row errors */ }
            }
        }
    } catch (Exception $e) {
        // ignore
    }
}

$action = isset($_REQUEST['action']) ? strtolower($_REQUEST['action']) : 'list';
ensure_categories_table();

switch ($action) {
    case 'preview_update': {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $newName = isset($_POST['name']) || isset($_GET['name']) ? wf_trim($_POST['name'] ?? $_GET['name']) : null;
        $newCode = isset($_POST['code']) || isset($_GET['code']) ? strtoupper(wf_trim($_POST['code'] ?? $_GET['code'])) : null;
        if ($id <= 0) respond(false, null, 'Invalid id', 400);
        try {
            $row = Database::queryOne("SELECT id, name, code FROM categories WHERE id = ?", [ $id ]);
            if (!$row) respond(false, null, 'Not found', 404);
            $oldName = $row['name'];
            $oldCode = $row['code'];
            $nameChange = ($newName !== null && $newName !== '' && $newName !== $oldName);
            $codeChange = ($newCode !== null && $newCode !== '' && $newCode !== $oldCode);
            $itemsToRename = 0; $skusToRewrite = 0;
            if ($nameChange) {
                $cnt = Database::queryOne("SELECT COUNT(*) AS c FROM items WHERE category = ?", [ $oldName ]);
                $itemsToRename = (int)($cnt['c'] ?? 0);
            }
            if ($codeChange) {
                $cnt = Database::queryOne("SELECT COUNT(*) AS c FROM items WHERE sku LIKE CONCAT('WF-', ?, '-%')", [ $oldCode ]);
                $skusToRewrite = (int)($cnt['c'] ?? 0);
            }
            respond(true, [
                'old' => ['name' => $oldName, 'code' => $oldCode],
                'new' => ['name' => $newName, 'code' => $newCode],
                'items_rename_count' => $itemsToRename,
                'sku_rewrite_count' => $skusToRewrite,
            ]);
        } catch (Exception $e) {
            respond(false, null, $e->getMessage(), 500);
        }
        break;
    }
    case 'start_rewrite_job': {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $newCode = isset($_POST['code']) || isset($_GET['code']) ? strtoupper(wf_trim($_POST['code'] ?? $_GET['code'])) : null;
        if ($id <= 0 || !$newCode) respond(false, null, 'Invalid params', 400);
        try {
            $row = Database::queryOne("SELECT id, code FROM categories WHERE id = ?", [ $id ]);
            if (!$row) respond(false, null, 'Not found', 404);
            $oldCode = $row['code'];
            if ($oldCode === $newCode) respond(true, [ 'message' => 'No change' ]);
            // Update category first to lock new code
            Database::execute("UPDATE categories SET code = ? WHERE id = ?", [ $newCode, $id ]);
            // Count affected
            $cnt = Database::queryOne("SELECT COUNT(*) AS c FROM items WHERE sku LIKE CONCAT('WF-', ?, '-%')", [ $oldCode ]);
            $total = (int)($cnt['c'] ?? 0);
            // Background processing: end response early then continue
            $jobId = 'sku-rewrite-' . $id . '-' . time();
            if (function_exists('fastcgi_finish_request')) {
                respond(true, [ 'job_id' => $jobId, 'estimated' => $total ]);
                ignore_user_abort(true);
                fastcgi_finish_request();
            } else {
                // Send response buffer then continue best-effort
                echo json_encode([ 'success' => true, 'data' => [ 'job_id' => $jobId, 'estimated' => $total ] ]);
                @ob_flush(); @flush();
                ignore_user_abort(true);
            }
            // Process in batches of 500
            $batch = 500; $offset = 0; $done = 0;
            while (true) {
                $rows = Database::queryAll(
                    "SELECT id, sku FROM items WHERE sku LIKE CONCAT('WF-', ?, '-%') LIMIT ? OFFSET ?",
                    [ $oldCode, $batch, $offset ]
                );
                if (!$rows || count($rows) === 0) break;
                foreach ($rows as $r) {
                    try {
                        $sku = $r['sku'];
                        // Replace prefix WF-OLD- with WF-NEW-
                        $pos1 = strpos($sku, '-') + 1; // after WF-
                        $pos2 = strpos($sku, '-', $pos1); // end of code
                        if ($pos1 && $pos2 && $pos2 > $pos1) {
                            $rest = substr($sku, $pos2); // includes '-...'
                            $newSku = 'WF-' . $newCode . $rest;
                            Database::execute("UPDATE items SET sku = ? WHERE id = ?", [ $newSku, $r['id'] ]);
                            $done++;
                        }
                    } catch (Exception $e) { /* best-effort */ }
                }
                $offset += $batch;
                if (connection_aborted()) break;
            }
            // Optionally: write a small log row/table or file; skipped for brevity
            exit;
        } catch (Exception $e) {
            respond(false, null, $e->getMessage(), 500);
        }
        break;
    }
    case 'list': {
        try {
            // If empty, try to seed once
            $cnt = Database::queryOne("SELECT COUNT(*) AS c FROM categories");
            if ((int)($cnt['c'] ?? 0) === 0) {
                // best-effort seed
                $seedRows = Database::queryAll("SELECT DISTINCT category AS name FROM items WHERE category IS NOT NULL AND category <> '' ORDER BY category");
                foreach ($seedRows as $r) {
                    $name = wf_trim($r['name'] ?? ''); if ($name === '') continue;
                    $code = wf_code_from_name($name);
                    try { Database::execute("INSERT IGNORE INTO categories (name, code, slug) VALUES (?, ?, ?)", [ $name, $code, strtolower(preg_replace('/[^a-z0-9]+/i','-', $name)) ]); } catch (Exception $e) {}
                }
            }
            $rows = Database::queryAll("SELECT id, name, code, is_active, created_at, updated_at FROM categories ORDER BY name");
            respond(true, [ 'categories' => $rows ]);
        } catch (Exception $e) {
            respond(false, null, $e->getMessage(), 500);
        }
        break;
    }
    case 'add': {
        $name = wf_trim($_POST['name'] ?? ($_GET['name'] ?? ''));
        $code = strtoupper(wf_trim($_POST['code'] ?? ($_GET['code'] ?? '')));
        if ($name === '') respond(false, null, 'Name is required', 400);
        if ($code === '') $code = wf_code_from_name($name);
        if (!preg_match('/^[A-Z0-9]{2,8}$/', $code)) respond(false, null, 'Invalid code. Use 2-8 letters/numbers.', 400);
        try {
            Database::execute("INSERT INTO categories (name, code, slug) VALUES (?, ?, ?)", [ $name, $code, strtolower(preg_replace('/[^a-z0-9]+/i','-', $name)) ]);
            $id = Database::lastInsertId();
            $row = Database::queryOne("SELECT id, name, code, is_active FROM categories WHERE id = ?", [ $id ]);
            respond(true, [ 'category' => $row ]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                respond(false, null, 'Name or code already exists', 409);
            }
            respond(false, null, $e->getMessage(), 500);
        }
        break;
    }
    case 'update': {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $newName = isset($_POST['name']) || isset($_GET['name']) ? wf_trim($_POST['name'] ?? $_GET['name']) : null;
        $newCode = isset($_POST['code']) || isset($_GET['code']) ? strtoupper(wf_trim($_POST['code'] ?? $_GET['code'])) : null;
        if ($id <= 0) respond(false, null, 'Invalid id', 400);
        try {
            $row = Database::queryOne("SELECT id, name, code FROM categories WHERE id = ?", [ $id ]);
            if (!$row) respond(false, null, 'Not found', 404);
            $oldName = $row['name'];
            $oldCode = $row['code'];
            $fields = [];
            $params = [];
            if ($newName !== null && $newName !== '' && $newName !== $oldName) { $fields[] = 'name = ?'; $params[] = $newName; $fields[] = 'slug = ?'; $params[] = strtolower(preg_replace('/[^a-z0-9]+/i','-', $newName)); }
            if ($newCode !== null && $newCode !== '' && $newCode !== $oldCode) {
                if (!preg_match('/^[A-Z0-9]{2,8}$/', $newCode)) respond(false, null, 'Invalid code. Use 2-8 letters/numbers.', 400);
                $fields[] = 'code = ?'; $params[] = $newCode;
            }
            if (empty($fields)) {
                respond(true, [ 'category' => $row, 'message' => 'No changes' ]);
            }
            $params[] = $id;
            Database::execute("UPDATE categories SET " . implode(', ', $fields) . " WHERE id = ?", $params);

            // On rename: update items.category from oldName to newName
            if ($newName !== null && $newName !== '' && $newName !== $oldName) {
                try {
                    Database::execute("UPDATE items SET category = ? WHERE category = ?", [ $newName, $oldName ]);
                } catch (Exception $e) { /* log but do not fail */ }
            }
            // On code change: rewrite existing SKU prefixes WF-OLD-XXX -> WF-NEW-XXX
            if ($newCode !== null && $newCode !== '' && $newCode !== $oldCode) {
                try {
                    // Update any SKU that starts with WF-OLDCODE-
                    Database::execute(
                        "UPDATE items SET sku = CONCAT('WF-', ?, SUBSTRING(sku, LOCATE('-', sku, LOCATE('-', sku)+1))) WHERE sku LIKE CONCAT('WF-', ?, '-%')",
                        [ $newCode, $oldCode ]
                    );
                } catch (Exception $e) { /* log but do not fail */ }
            }
            $out = Database::queryOne("SELECT id, name, code, is_active FROM categories WHERE id = ?", [ $id ]);
            respond(true, [ 'category' => $out ]);
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                respond(false, null, 'Name or code already exists', 409);
            }
            respond(false, null, $e->getMessage(), 500);
        }
        break;
    }
    case 'delete': {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $force = ($_POST['force'] ?? $_GET['force'] ?? '') === '1';
        $targetId = (int)($_POST['target_id'] ?? $_GET['target_id'] ?? 0);
        if ($id <= 0) respond(false, null, 'Invalid id', 400);
        try {
            $row = Database::queryOne("SELECT id, name FROM categories WHERE id = ?", [ $id ]);
            if (!$row) respond(false, null, 'Not found', 404);
            $name = $row['name'];
            $cnt = Database::queryOne("SELECT COUNT(*) AS c FROM items WHERE category = ?", [ $name ]);
            $count = (int)($cnt['c'] ?? 0);
            if ($count > 0 && !$force) {
                respond(false, [ 'count' => $count ], 'Category in use. Use force with target_id to remap items.', 409);
            }
            if ($count > 0 && $force && $targetId > 0) {
                $t = Database::queryOne("SELECT name FROM categories WHERE id = ?", [ $targetId ]);
                if (!$t) respond(false, null, 'Target not found', 400);
                Database::execute("UPDATE items SET category = ? WHERE category = ?", [ $t['name'], $name ]);
            }
            Database::execute("DELETE FROM categories WHERE id = ?", [ $id ]);
            respond(true, [ 'deleted' => $id ]);
        } catch (Exception $e) {
            respond(false, null, $e->getMessage(), 500);
        }
        break;
    }
    default:
        respond(false, null, 'Unknown action', 400);
}
