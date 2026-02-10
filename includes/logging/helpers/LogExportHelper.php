<?php
// includes/logging/helpers/LogExportHelper.php

class LogExportHelper
{
    private static function isValidIdentifier($value)
    {
        return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    /**
     * Download a specific database log as CSV
     */
    public static function downloadDatabaseLog($type, $filters = [])
    {
        $configs = LogQueryHelper::getDatabaseLogDefinitions();
        if (!isset($configs[$type])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid log type']);
            return;
        }

        $table = $configs[$type]['table'];
        $timestampField = $configs[$type]['timestamp_field'];
        if (!self::isValidIdentifier($table) || !self::isValidIdentifier($timestampField)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid log config']);
            return;
        }

        $where = [];
        $params = [];
        if (!empty($filters['from'])) {
            $from = $filters['from'];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from .= ' 00:00:00';
            $where[] = "`$timestampField` >= ?";
            $params[] = $from;
        }
        if (!empty($filters['to'])) {
            $to = $filters['to'];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to .= ' 23:59:59';
            $where[] = "`$timestampField` <= ?";
            $params[] = $to;
        }
        if (!empty($filters['status']) && $type === 'email_logs') {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['email_type']) && $type === 'email_logs') {
            $where[] = "email_type = ?";
            $params[] = $filters['email_type'];
        }

        $whereSql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';
        $entries = Database::queryAll("SELECT * FROM `$table`$whereSql ORDER BY id DESC", $params);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $type . '_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        if (!empty($entries)) {
            fputcsv($output, array_keys($entries[0]));
            foreach ($entries as $entry) {
                fputcsv($output, $entry);
            }
        }
        fclose($output);
        exit;
    }

    /**
     * Download all logs (database as CSV, files as-is) in a ZIP archive
     */
    public static function downloadAllLogs()
    {
        $zipFile = tempnam(sys_get_temp_dir(), 'whimsical_logs_') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($zipFile, ZipArchive::CREATE) !== true) {
            throw new Exception('Cannot create zip file');
        }

        $logs = LogQueryHelper::buildAvailableLogs();
        $tmpCsvFiles = [];

        foreach ($logs as $log) {
            $source = $log['log_source'] ?? '';
            $type = (string)($log['type'] ?? '');

            if ($source === 'file') {
                $relPath = (string)($log['path'] ?? '');
                if ($relPath === '') continue;
                
                $fullPath = realpath(dirname(__DIR__, 3) . '/' . $relPath);
                $logsRoot = realpath(dirname(__DIR__, 3) . '/logs');
                
                if ($fullPath && $logsRoot && strpos($fullPath, $logsRoot) === 0 && is_file($fullPath)) {
                    $zip->addFile($fullPath, 'files/' . basename($fullPath));
                }
            } else {
                if ($type === '') continue;
                $csvPath = self::writeDatabaseLogToTempCsv($type);
                $tmpCsvFiles[] = $csvPath;
                $zip->addFile($csvPath, 'database/' . $type . '.csv');
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="whimsical_logs_' . date('Y-m-d_H-i-s') . '.zip"');
        header('Content-Length: ' . filesize($zipFile));
        
        readfile($zipFile);
        @unlink($zipFile);
        foreach ($tmpCsvFiles as $p) @unlink($p);
        exit;
    }

    private static function writeDatabaseLogToTempCsv($type)
    {
        $configs = LogQueryHelper::getDatabaseLogDefinitions();
        if (!isset($configs[$type])) {
            throw new Exception('Invalid log type for export');
        }
        $table = $configs[$type]['table'];
        if (!self::isValidIdentifier($table)) {
            throw new Exception('Invalid table name for export');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'wf_log_');
        $fp = fopen($tmp, 'w');
        if (!$fp) {
            @unlink($tmp);
            throw new Exception('Cannot write temp file');
        }

        $wroteHeader = false;
        $limit = 1000;
        $offset = 0;

        while (true) {
            $rows = Database::queryAll("SELECT * FROM `$table` ORDER BY id DESC LIMIT ? OFFSET ?", [$limit, $offset]);
            if (!$rows) break;

            if (!$wroteHeader) {
                fputcsv($fp, array_keys($rows[0]));
                $wroteHeader = true;
            }

            foreach ($rows as $r) fputcsv($fp, $r);
            if (count($rows) < $limit) break;
            $offset += $limit;
        }

        fclose($fp);
        return $tmp;
    }
}
