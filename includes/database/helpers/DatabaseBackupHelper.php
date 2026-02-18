<?php
// includes/database/helpers/DatabaseBackupHelper.php

class DatabaseBackupHelper
{
    /**
     * List available backup files
     */
    public static function listBackups()
    {
        try {
            $backupDir = dirname(__DIR__, 3) . '/backups/';
            $backups = [];

            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $files = glob($backupDir . '*.sql');

            foreach ($files as $file) {
                $filename = basename($file);
                $size = filesize($file);
                $mtime = filemtime($file);
                $created = date('Y-m-d H:i:s', $mtime);
                $age = time() - $mtime;

                if ($age < 3600) {
                    $ageFormatted = round($age / 60) . ' minutes ago';
                } elseif ($age < 86400) {
                    $ageFormatted = round($age / 3600) . ' hours ago';
                } else {
                    $ageFormatted = round($age / 86400) . ' days ago';
                }

                $backups[] = [
                    'filename' => $filename,
                    'path' => $file,
                    'size' => self::formatBytes($size),
                    'created' => $created,
                    'age' => $ageFormatted,
                    'timestamp' => $mtime
                ];
            }

            usort($backups, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

            return [
                'success' => true,
                'backups' => $backups
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to list backups: ' . $e->getMessage());
        }
    }

    /**
     * Create a database backup
     */
    public static function createBackup()
    {
        try {
            $pdo = Database::getInstance();
            global $db;

            $backupDir = dirname(__DIR__, 3) . '/backups/';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            $filename = "backup_" . date('Y-m-d_H-i-s') . ".sql";
            $filePath = $backupDir . $filename;

            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $backup = "-- Database Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n-- Database: $db\n\n";

            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
                $createTable = $stmt->fetch(PDO::FETCH_ASSOC);

                $backup .= "-- Table structure for `$table`\nDROP TABLE IF EXISTS `$table`;\n" . $createTable['Create Table'] . ";\n\n";

                $stmt = $pdo->query("SELECT * FROM `$table`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $backup .= "-- Data for table `$table`\n";
                    $columns = array_keys($rows[0]);
                    $columnList = '`' . implode('`, `', $columns) . '`';
                    $backup .= "INSERT INTO `$table` ($columnList) VALUES\n";

                    $values = [];
                    foreach ($rows as $row) {
                        $escapedRow = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), $row);
                        $values[] = '(' . implode(', ', $escapedRow) . ')';
                    }
                    $backup .= implode(",\n", $values) . ";\n\n";
                }
            }

            if (file_put_contents($filePath, $backup) === false) {
                throw new Exception('Failed to write backup file');
            }

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filePath,
                'size' => self::formatBytes(filesize($filePath)),
                'tables' => count($tables)
            ];
        } catch (Exception $e) {
            throw new Exception('Backup creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Restore database from backup
     */
    public static function restoreDatabase($input, $files)
    {
        try {
            $pdo = Database::getInstance();
            $ignoreErrors = isset($input['ignore_errors']) && $input['ignore_errors'] === '1';
            $tableWhitelist = self::resolveTableWhitelist($input, $files);
            $source = self::resolveRestoreSource($input, $files);
            $filePath = $source['filePath'];
            $isGzip = $source['isGzip'];

            $startTime = microtime(true);
            $preflight = self::analyzeBackupFile($filePath, $isGzip, $tableWhitelist);

            // Mandatory safety snapshot before any destructive step.
            $preRestoreBackup = self::createBackup();
            if (empty($preRestoreBackup['success'])) {
                throw new Exception('Failed to create pre-restore safety backup');
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $pdo->exec("SET SQL_MODE = ''");
            self::wipeRestoredTables($pdo, $preflight);

            $executedStatements = 0;
            $errors = [];
            $tablesRestored = 0;
            $recordsRestored = 0;

            $handle = self::openBackupStream($filePath, $isGzip);

            $buffer = '';
            while (!self::backupEof($handle, $isGzip)) {
                $line = self::backupGets($handle, $isGzip);
                if ($line === false) {
                    break;
                }
                $trim = ltrim($line);
                if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '/*')) {
                    continue;
                }
                $buffer .= $line;
                if (preg_match('/;\s*$/', rtrim($buffer))) {
                    $stmt = trim($buffer);
                    if ($stmt !== '') {
                        if (substr($stmt, -1) === ';') {
                            $stmt = substr($stmt, 0, -1);
                        }
                        $table = self::extractTableNameFromStatement($stmt);
                        if (!empty($tableWhitelist) && $table !== null && !isset($tableWhitelist[strtolower($table)])) {
                            $buffer = '';
                            continue;
                        }
                        try {
                            $result = $pdo->exec($stmt);
                            $executedStatements++;
                            if (stripos($stmt, 'CREATE TABLE') !== false) {
                                $tablesRestored++;
                            } elseif (stripos($stmt, 'INSERT INTO') !== false || stripos($stmt, 'REPLACE INTO') !== false) {
                                $recordsRestored += $result ?: 0;
                            }
                        } catch (PDOException $e) {
                            $err = 'Error in statement: ' . substr($stmt, 0, 100) . '... - ' . $e->getMessage();
                            $errors[] = $err;
                            if (!$ignoreErrors) {
                                self::closeBackupStream($handle, $isGzip);
                                throw new Exception($err);
                            }
                        }
                    }
                    $buffer = '';
                }
            }
            self::closeBackupStream($handle, $isGzip);

            if (trim($buffer) !== '') {
                throw new Exception('Backup failed completeness check: SQL appears truncated (unterminated statement detected)');
            }

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            return [
                'success' => true,
                'tables_restored' => $tablesRestored,
                'records_restored' => $recordsRestored,
                'statements_executed' => $executedStatements,
                'execution_time' => round(microtime(true) - $startTime, 2) . ' seconds',
                'pre_restore_backup' => $preRestoreBackup['filename'] ?? null,
                'preflight' => [
                    'statements' => $preflight['statementCount'],
                    'tables_touched' => count($preflight['tablesTouched']),
                    'tables_recreated' => count($preflight['tablesWithCreate']),
                    'tables_data_restored' => count($preflight['tablesWithData'])
                ],
                'table_whitelist' => !empty($tableWhitelist) ? array_values(array_keys($tableWhitelist)) : [],
                'warnings' => !empty($errors) ? count($errors) . ' errors encountered' : null,
                'error_details' => $errors
            ];
        } catch (Exception $e) {
            try {
                $pdo = Database::getInstance();
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            } catch (Exception $_) {
                // Ignore reset failures while surfacing root restore error.
            }
            throw new Exception('Database restore failed: ' . $e->getMessage());
        }
    }

    /**
     * Drop all tables in database
     */
    public static function dropAllTables(array $skipTables = [])
    {
        try {
            $pdo = Database::getInstance();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

            $skip = [];
            foreach ($skipTables as $t) {
                if (is_string($t) && $t !== '') {
                    $skip[strtolower($t)] = true;
                }
            }

            $dropped = 0;
            $skipped = [];
            foreach ($tables as $table) {
                if (isset($skip[strtolower((string)$table)])) {
                    $skipped[] = $table;
                    continue;
                }
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
                $dropped++;
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            return [
                'success' => true,
                'tables_dropped' => $dropped,
                'tables_skipped' => $skipped,
                'tables' => $tables
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to drop tables: ' . $e->getMessage());
        }
    }

    private static function resolveRestoreSource($input, $files): array
    {
        if (isset($files['backup_file']) && isset($files['backup_file']['error']) && (int)$files['backup_file']['error'] === UPLOAD_ERR_OK) {
            $filePath = $files['backup_file']['tmp_name'];
            $isGzip = (bool)preg_match('/\.gz$/i', (string)($files['backup_file']['name'] ?? ''));
            self::assertValidBackupFile($filePath);
            return ['filePath' => $filePath, 'isGzip' => $isGzip];
        }

        if (isset($files['backup_file']) && isset($files['backup_file']['error']) && (int)$files['backup_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $code = (int)$files['backup_file']['error'];
            throw new Exception('Backup upload failed: ' . self::mapUploadErrorCode($code));
        }

        if (empty($input['server_backup_path'])) {
            $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
            if ($contentLength > 0) {
                $postMaxBytes = self::iniSizeToBytes((string)ini_get('post_max_size'));
                $uploadMaxBytes = self::iniSizeToBytes((string)ini_get('upload_max_filesize'));
                if (($postMaxBytes > 0 && $contentLength > $postMaxBytes) || ($uploadMaxBytes > 0 && $contentLength > $uploadMaxBytes)) {
                    throw new Exception(
                        'Backup upload exceeded PHP limits (post_max_size=' . (string)ini_get('post_max_size') .
                        ', upload_max_filesize=' . (string)ini_get('upload_max_filesize') .
                        '). Upload a smaller/compressed file or place it under backups/sql and restore via server path.'
                    );
                }
            }
            throw new Exception('No backup file provided (via upload or server path)');
        }

        $relPath = trim((string)$input['server_backup_path']);
        $projectRoot = dirname(__DIR__, 3);
        $candidate = realpath($projectRoot . '/' . $relPath);
        if (!$candidate || !is_file($candidate)) {
            throw new Exception('Backup file not found: ' . $relPath . ' (tried ' . $projectRoot . '/' . $relPath . ')');
        }

        $allowedDirs = [
            realpath($projectRoot . '/backups'),
            realpath($projectRoot . '/api/uploads')
        ];
        $isAllowed = false;
        foreach ($allowedDirs as $dir) {
            if ($dir && strpos($candidate, $dir) === 0) {
                $isAllowed = true;
                break;
            }
        }
        if (!$isAllowed) {
            throw new Exception('Access denied to backup file path: ' . $candidate);
        }

        self::assertValidBackupFile($candidate);
        return ['filePath' => $candidate, 'isGzip' => (bool)preg_match('/\.gz$/i', $candidate)];
    }

    private static function resolveTableWhitelist($input, $files): array
    {
        $rawDataGroups = $input['data_groups'] ?? null;
        $dataGroups = self::resolveJsonArrayInput($rawDataGroups);
        $normalizedGroups = self::normalizeDataGroups($dataGroups ?? []);
        if ($rawDataGroups !== null && $dataGroups !== null && empty($normalizedGroups)) {
            throw new Exception('No valid data groups were provided for scoped restore.');
        }
        if (!empty($normalizedGroups)) {
            $tables = self::tablesForDataGroups($normalizedGroups);
            if (empty($tables)) {
                throw new Exception('Selected data groups did not map to any restorable tables.');
            }
            $out = [];
            foreach ($tables as $table) {
                $name = strtolower(trim((string)$table));
                if ($name !== '') {
                    $out[$name] = $name;
                }
            }
            return $out;
        }

        $raw = self::resolveJsonArrayInput($input['table_whitelist'] ?? null);
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $table) {
            $name = strtolower(trim((string)$table));
            if ($name === '') {
                continue;
            }
            if (!preg_match('/^[a-z0-9_]+$/', $name)) {
                throw new Exception('Invalid table name in whitelist: ' . (string)$table);
            }
            $out[$name] = $name;
        }
        return $out;
    }

    private static function resolveJsonArrayInput($raw): ?array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }
        return is_array($raw) ? $raw : null;
    }

    private static function normalizeDataGroups(array $raw): array
    {
        $allowed = ['room_maps', 'customers', 'inventory', 'orders'];
        $groups = [];
        foreach ($raw as $group) {
            $value = strtolower(trim((string)$group));
            if ($value !== '' && in_array($value, $allowed, true) && !in_array($value, $groups, true)) {
                $groups[] = $value;
            }
        }
        return $groups;
    }

    private static function getExistingTables(): array
    {
        $rows = Database::queryAll('SHOW TABLES');
        $tables = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row)) {
                continue;
            }
            $name = (string)reset($row);
            if ($name !== '') {
                $tables[strtolower($name)] = $name;
            }
        }
        return $tables;
    }

    private static function tablesForDataGroups(array $groups): array
    {
        $wanted = [];
        $add = static function (string $name) use (&$wanted): void {
            $value = strtolower(trim($name));
            if ($value !== '' && !in_array($value, $wanted, true)) {
                $wanted[] = $value;
            }
        };

        foreach ($groups as $group) {
            if ($group === 'room_maps') {
                $add('room_maps');
            } elseif ($group === 'customers') {
                $add('users');
                $add('users_meta');
                $add('addresses');
                $add('customer_notes');
            } elseif ($group === 'inventory') {
                $add('items');
                $add('item_images');
                $add('categories');
                $add('inventory_option_links');
                $add('gender_template_items');
                $add('size_template_items');
                $add('color_template_items');
            } elseif ($group === 'orders') {
                $add('orders');
                $add('order_items');
            }
        }

        $existing = self::getExistingTables();
        if (in_array('inventory', $groups, true)) {
            foreach (array_keys($existing) as $table) {
                if (str_starts_with($table, 'inventory_') && !in_array($table, $wanted, true)) {
                    $wanted[] = $table;
                }
            }
        }
        return $wanted;
    }

    private static function assertValidBackupFile(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new Exception('Backup file not found');
        }
        $size = @filesize($filePath);
        if ($size === false || (int)$size <= 0) {
            throw new Exception('Backup file is empty');
        }
    }

    private static function mapUploadErrorCode(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'file exceeds upload_max_filesize (' . (string)ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE => 'file exceeds MAX_FILE_SIZE from form',
            UPLOAD_ERR_PARTIAL => 'file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'no file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'missing temporary upload directory',
            UPLOAD_ERR_CANT_WRITE => 'failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'a PHP extension stopped the upload',
            default => 'unknown upload error code ' . $code,
        };
    }

    private static function iniSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float)$value;
        if ($unit === 'g') {
            return (int)($number * 1024 * 1024 * 1024);
        }
        if ($unit === 'm') {
            return (int)($number * 1024 * 1024);
        }
        if ($unit === 'k') {
            return (int)($number * 1024);
        }
        return (int)$number;
    }

    private static function analyzeBackupFile(string $filePath, bool $isGzip, array $tableWhitelist = []): array
    {
        $handle = self::openBackupStream($filePath, $isGzip);
        $buffer = '';
        $statementCount = 0;
        $tablesWithCreate = [];
        $tablesWithData = [];
        $tablesTouched = [];

        while (!self::backupEof($handle, $isGzip)) {
            $line = self::backupGets($handle, $isGzip);
            if ($line === false) {
                break;
            }
            $trim = ltrim($line);
            if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '/*')) {
                continue;
            }
            $buffer .= $line;
            if (!preg_match('/;\s*$/', rtrim($buffer))) {
                continue;
            }

            $stmt = trim($buffer);
            $buffer = '';
            if ($stmt === '') {
                continue;
            }
            if (substr($stmt, -1) === ';') {
                $stmt = substr($stmt, 0, -1);
            }

            $table = self::extractTableNameFromStatement($stmt);
            if (!empty($tableWhitelist) && $table !== null && !isset($tableWhitelist[strtolower($table)])) {
                continue;
            }

            $statementCount++;
            if ($table === null) {
                continue;
            }
            $tablesTouched[$table] = true;
            if (preg_match('/^\s*CREATE\s+TABLE\b/i', $stmt)) {
                $tablesWithCreate[$table] = true;
            }
            if (preg_match('/^\s*(INSERT|REPLACE|UPDATE|DELETE|TRUNCATE)\b/i', $stmt)) {
                $tablesWithData[$table] = true;
            }
        }

        self::closeBackupStream($handle, $isGzip);

        if (trim($buffer) !== '') {
            throw new Exception('Backup failed completeness check: SQL appears truncated (unterminated statement detected)');
        }
        if ($statementCount < 1) {
            throw new Exception('Backup failed completeness check: no SQL statements found');
        }
        if (count($tablesTouched) < 1) {
            throw new Exception('Backup failed completeness check: no target tables detected');
        }
        if (count($tablesWithData) < 1 && count($tablesWithCreate) < 1) {
            throw new Exception('Backup failed completeness check: backup does not include restorable table data/schema');
        }

        return [
            'statementCount' => $statementCount,
            'tablesTouched' => array_keys($tablesTouched),
            'tablesWithCreate' => array_keys($tablesWithCreate),
            'tablesWithData' => array_keys($tablesWithData)
        ];
    }

    private static function extractTableNameFromStatement(string $stmt): ?string
    {
        $patterns = [
            '/^\s*CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*DROP\s+TABLE(?:\s+IF\s+EXISTS)?\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*REPLACE\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*UPDATE\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*DELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i',
            '/^\s*TRUNCATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i'
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $stmt, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    private static function wipeRestoredTables(PDO $pdo, array $preflight): void
    {
        $tablesTouched = $preflight['tablesTouched'] ?? [];
        $tablesWithCreate = array_fill_keys($preflight['tablesWithCreate'] ?? [], true);

        if (empty($tablesTouched)) {
            throw new Exception('Backup preflight did not identify any tables to wipe');
        }

        foreach ($tablesTouched as $table) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$table)) {
                throw new Exception('Unsafe table name in backup preflight: ' . (string)$table);
            }
            $quoted = '`' . $table . '`';

            if (isset($tablesWithCreate[$table])) {
                $pdo->exec('DROP TABLE IF EXISTS ' . $quoted);
                continue;
            }

            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            $exists = (bool)$stmt->fetchColumn();
            if (!$exists) {
                throw new Exception("Backup expects existing table `{$table}` but it does not exist (no CREATE TABLE statement provided)");
            }
            $pdo->exec('TRUNCATE TABLE ' . $quoted);
        }
    }

    private static function openBackupStream(string $filePath, bool $isGzip)
    {
        $handle = $isGzip ? @gzopen($filePath, 'rb') : @fopen($filePath, 'rb');
        if ($handle === false) {
            throw new Exception('Unable to open backup file');
        }
        return $handle;
    }

    private static function backupGets($handle, bool $isGzip)
    {
        return $isGzip ? gzgets($handle) : fgets($handle);
    }

    private static function backupEof($handle, bool $isGzip): bool
    {
        return $isGzip ? gzeof($handle) : feof($handle);
    }

    private static function closeBackupStream($handle, bool $isGzip): void
    {
        if ($isGzip) {
            @gzclose($handle);
            return;
        }
        @fclose($handle);
    }

    private static function formatBytes($size)
    {
        if ($size == 0)
            return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = floor(log($size, 1024));
        return round(pow(1024, log($size, 1024) - $unit), 2) . ' ' . $units[$unit];
    }
}
