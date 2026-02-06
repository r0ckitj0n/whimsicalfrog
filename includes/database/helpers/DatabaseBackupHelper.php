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
            $filePath = '';
            $isGzip = false;

            if (isset($files['backup_file']) && $files['backup_file']['error'] === UPLOAD_ERR_OK) {
                $filePath = $files['backup_file']['tmp_name'];
                $isGzip = (bool) preg_match('/\.gz$/i', $files['backup_file']['name']);
            } elseif (!empty($input['server_backup_path'])) {
                $relPath = trim($input['server_backup_path']);
                $projectRoot = dirname(__DIR__, 2); // path to project root

                // Try relative to project root
                $candidate = realpath($projectRoot . '/' . $relPath);

                if (!$candidate || !is_file($candidate)) {
                    throw new Exception('Backup file not found: ' . $relPath . ' (tried ' . $projectRoot . '/' . $relPath . ')');
                }

                // Security check: ensure path is within allowed directories
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
                if (!$isAllowed)
                    throw new Exception('Access denied to backup file path: ' . $candidate);

                $filePath = $candidate;
                $isGzip = (bool) preg_match('/\.gz$/i', $filePath);
            } else {
                throw new Exception('No backup file provided (via upload or server path)');
            }

            $startTime = microtime(true);
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("SET SQL_MODE = ''");

            $executedStatements = 0;
            $errors = [];
            $tablesRestored = 0;
            $recordsRestored = 0;

            $handle = $isGzip ? @gzopen($filePath, 'rb') : @fopen($filePath, 'rb');
            if (!$handle)
                throw new Exception('Unable to open backup file');

            $buffer = '';
            while (!($isGzip ? gzeof($handle) : feof($handle))) {
                $line = $isGzip ? gzgets($handle) : fgets($handle);
                if ($line === false)
                    break;
                $trim = ltrim($line);
                if ($trim === '' || str_starts_with($trim, '--') || str_starts_with($trim, '/*'))
                    continue;
                $buffer .= $line;
                if (preg_match('/;\s*$/', rtrim($buffer))) {
                    $stmt = trim($buffer);
                    if ($stmt !== '') {
                        if (substr($stmt, -1) === ';')
                            $stmt = substr($stmt, 0, -1);
                        try {
                            $result = $pdo->exec($stmt);
                            $executedStatements++;
                            if (stripos($stmt, 'CREATE TABLE') !== false)
                                $tablesRestored++;
                            elseif (stripos($stmt, 'INSERT INTO') !== false)
                                $recordsRestored += $result ?: 0;
                        } catch (PDOException $e) {
                            $err = 'Error in statement: ' . substr($stmt, 0, 100) . '... - ' . $e->getMessage();
                            $errors[] = $err;
                            if (!$ignoreErrors) {
                                if ($isGzip)
                                    @gzclose($handle);
                                else
                                    @fclose($handle);
                                throw new Exception($err);
                            }
                        }
                    }
                    $buffer = '';
                }
            }
            if ($isGzip)
                @gzclose($handle);
            else
                @fclose($handle);

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

            return [
                'success' => true,
                'tables_restored' => $tablesRestored,
                'records_restored' => $recordsRestored,
                'statements_executed' => $executedStatements,
                'execution_time' => round(microtime(true) - $startTime, 2) . ' seconds',
                'warnings' => !empty($errors) ? count($errors) . ' errors encountered' : null,
                'error_details' => $errors
            ];
        } catch (Exception $e) {
            throw new Exception('Database restore failed: ' . $e->getMessage());
        }
    }

    /**
     * Drop all tables in database
     */
    public static function dropAllTables()
    {
        try {
            $pdo = Database::getInstance();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
            }
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            return [
                'success' => true,
                'tables_dropped' => count($tables),
                'tables' => $tables
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to drop tables: ' . $e->getMessage());
        }
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
