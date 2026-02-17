<?php
/**
 * Add-only schema sync from local MySQL to live MySQL.
 *
 * Usage:
 *   php scripts/db/sync_local_schema_to_live.php --table=email_logs --apply
 *   php scripts/db/sync_local_schema_to_live.php --apply
 *
 * Notes:
 * - Creates missing tables on live using local SHOW CREATE TABLE.
 * - Adds missing columns and secondary indexes on live.
 * - Does not drop/rename columns, alter existing column types, or remove indexes.
 */

declare(strict_types=1);

function load_env_file(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eqPos));
        $val = trim(substr($line, $eqPos + 1));
        $val = trim($val, "\"'");
        if ($key === '') {
            continue;
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $val);
            $_ENV[$key] = $val;
        }
    }
}

function parse_args(array $argv): array
{
    $out = [
        'table' => null,
        'apply' => false,
    ];
    foreach ($argv as $arg) {
        if (strpos($arg, '--table=') === 0) {
            $out['table'] = substr($arg, 8);
            continue;
        }
        if ($arg === '--apply') {
            $out['apply'] = true;
        }
    }
    return $out;
}

function q_ident(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function connect_db(array $cfg): PDO
{
    $host = (string) ($cfg['host'] ?? '');
    $db = (string) ($cfg['db'] ?? '');
    $port = (int) ($cfg['port'] ?? 3306);
    $user = (string) ($cfg['user'] ?? '');
    $pass = (string) ($cfg['pass'] ?? '');
    $socket = (string) ($cfg['socket'] ?? '');

    if ($host === '' || $db === '' || $user === '') {
        throw new RuntimeException('Missing DB config.');
    }

    if ($socket !== '') {
        $dsn = "mysql:unix_socket={$socket};dbname={$db};charset=utf8mb4";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    }

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function table_exists(PDO $pdo, string $dbName, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS c
         FROM information_schema.tables
         WHERE table_schema = ? AND table_name = ?'
    );
    $stmt->execute([$dbName, $table]);
    $row = $stmt->fetch();
    return ((int) ($row['c'] ?? 0)) > 0;
}

function get_show_create_table(PDO $pdo, string $table): string
{
    $stmt = $pdo->query('SHOW CREATE TABLE ' . q_ident($table));
    $row = $stmt->fetch();
    if (!is_array($row)) {
        throw new RuntimeException("Unable to read CREATE TABLE for {$table}");
    }
    $sql = (string) ($row['Create Table'] ?? '');
    if ($sql === '') {
        throw new RuntimeException("Missing Create Table SQL for {$table}");
    }
    return $sql;
}

function get_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('SHOW FULL COLUMNS FROM ' . q_ident($table));
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $name = (string) ($row['Field'] ?? '');
        if ($name !== '') {
            $out[$name] = $row;
        }
    }
    return $out;
}

function build_add_column_sql(string $table, array $col): string
{
    $name = (string) $col['Field'];
    $type = (string) ($col['Type'] ?? '');
    $nullable = ((string) ($col['Null'] ?? '') === 'YES') ? 'NULL' : 'NOT NULL';
    $default = $col['Default'] ?? null;
    $extra = trim((string) ($col['Extra'] ?? ''));
    $comment = (string) ($col['Comment'] ?? '');
    $collation = (string) ($col['Collation'] ?? '');

    $sql = 'ALTER TABLE ' . q_ident($table) . ' ADD COLUMN ' . q_ident($name) . ' ' . $type;

    $isTextual = (
        stripos($type, 'char') !== false
        || stripos($type, 'text') !== false
        || stripos($type, 'enum') !== false
        || stripos($type, 'set') !== false
    );
    if ($collation !== '' && $isTextual) {
        $sql .= ' COLLATE ' . $collation;
    }

    $sql .= ' ' . $nullable;

    if ($default !== null) {
        $isExpression = strtoupper((string) $default) === 'CURRENT_TIMESTAMP';
        if ($isExpression) {
            $sql .= ' DEFAULT ' . $default;
        } else {
            $sql .= ' DEFAULT ' . connect_db_escape_literal($default);
        }
    } elseif ($nullable === 'NULL') {
        $sql .= ' DEFAULT NULL';
    }

    if ($extra !== '') {
        $sql .= ' ' . $extra;
    }

    if ($comment !== '') {
        $sql .= ' COMMENT ' . connect_db_escape_literal($comment);
    }

    return $sql;
}

function connect_db_escape_literal(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

function get_indexes(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('SHOW INDEX FROM ' . q_ident($table));
    $rows = $stmt->fetchAll();
    $indexes = [];
    foreach ($rows as $row) {
        $key = (string) ($row['Key_name'] ?? '');
        if ($key === '') {
            continue;
        }
        if (!isset($indexes[$key])) {
            $indexes[$key] = [
                'non_unique' => (int) ($row['Non_unique'] ?? 1),
                'index_type' => (string) ($row['Index_type'] ?? 'BTREE'),
                'columns' => [],
            ];
        }
        $seq = (int) ($row['Seq_in_index'] ?? 1);
        $indexes[$key]['columns'][$seq] = (string) ($row['Column_name'] ?? '');
    }

    foreach ($indexes as &$idx) {
        ksort($idx['columns']);
        $idx['columns'] = array_values($idx['columns']);
    }

    return $indexes;
}

function build_add_index_sql(string $table, string $name, array $idx): ?string
{
    if ($name === 'PRIMARY') {
        return null;
    }
    $columns = $idx['columns'] ?? [];
    if (!is_array($columns) || count($columns) === 0) {
        return null;
    }
    $cols = implode(', ', array_map('q_ident', $columns));
    $isUnique = ((int) ($idx['non_unique'] ?? 1) === 0);
    $indexType = strtoupper((string) ($idx['index_type'] ?? 'BTREE'));
    $kind = $isUnique ? 'ADD UNIQUE INDEX' : 'ADD INDEX';
    $using = ($indexType !== '' && $indexType !== 'BTREE') ? (' USING ' . $indexType) : '';

    return 'ALTER TABLE ' . q_ident($table) . ' ' . $kind . ' ' . q_ident($name) . ' (' . $cols . ')' . $using;
}

function run_sql(PDO $pdo, string $sql, bool $apply): void
{
    if ($apply) {
        $pdo->exec($sql);
        echo "[APPLIED] {$sql}\n";
        return;
    }
    echo "[DRY-RUN] {$sql}\n";
}

$root = dirname(__DIR__, 2);
load_env_file($root . '/.env');
load_env_file($root . '/.env.live');
require_once $root . '/includes/config_helper.php';

$args = parse_args(array_slice($argv, 1));
$tableFilter = $args['table'];
$apply = (bool) $args['apply'];

$localCfg = wf_get_db_config('local');
$liveCfg = wf_get_db_config('live');

$localPdo = connect_db($localCfg);
$livePdo = connect_db($liveCfg);

$localDb = (string) ($localCfg['db'] ?? '');
$liveDb = (string) ($liveCfg['db'] ?? '');

$tables = [];
if ($tableFilter !== null && $tableFilter !== '') {
    $tables[] = $tableFilter;
} else {
    $stmt = $localPdo->prepare('SELECT table_name FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name ASC');
    $stmt->execute([$localDb]);
    $rows = $stmt->fetchAll();
    foreach ($rows as $row) {
        $tables[] = (string) $row['table_name'];
    }
}

echo "Schema sync mode: " . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo "Source(local): {$localDb}\n";
echo "Target(live): {$liveDb}\n";
echo "Tables: " . implode(', ', $tables) . "\n";

foreach ($tables as $table) {
    if ($table === '') {
        continue;
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        throw new RuntimeException("Invalid table name: {$table}");
    }

    if (!table_exists($localPdo, $localDb, $table)) {
        throw new RuntimeException("Source table does not exist: {$table}");
    }

    if (!table_exists($livePdo, $liveDb, $table)) {
        $createSql = get_show_create_table($localPdo, $table);
        run_sql($livePdo, $createSql, $apply);
        continue;
    }

    $localCols = get_columns($localPdo, $table);
    $liveCols = get_columns($livePdo, $table);
    foreach ($localCols as $name => $col) {
        if (!isset($liveCols[$name])) {
            $sql = build_add_column_sql($table, $col);
            run_sql($livePdo, $sql, $apply);
        }
    }

    $localIndexes = get_indexes($localPdo, $table);
    $liveIndexes = get_indexes($livePdo, $table);
    foreach ($localIndexes as $name => $idx) {
        if (isset($liveIndexes[$name])) {
            continue;
        }
        $sql = build_add_index_sql($table, $name, $idx);
        if ($sql !== null) {
            run_sql($livePdo, $sql, $apply);
        }
    }
}

echo "Done.\n";
