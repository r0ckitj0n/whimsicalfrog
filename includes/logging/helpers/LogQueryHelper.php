<?php
// includes/logging/helpers/LogQueryHelper.php

class LogQueryHelper
{
    /**
     * Build list of available database and file logs
     */
    public static function buildAvailableLogs()
    {
        $logs = [];
        $databaseLogs = self::getDatabaseLogDefinitions();

        foreach ($databaseLogs as $type => $info) {
            $tableName = $info['table'];

            // Ensure table exists
            if (empty(Database::queryAll("SHOW TABLES LIKE '$tableName'"))) {
                LogMaintenanceHelper::createLogTable($tableName, $type);
            }

            $count = 0;
            $lastEntryTime = null;
            try {
                $rowCount = Database::queryOne("SELECT COUNT(*) as count FROM $tableName");
                $count = $rowCount ? (int) $rowCount['count'] : 0;

                $timestampField = $info['timestamp_field'];
                $lastResult = Database::queryOne("SELECT MAX($timestampField) as last_entry FROM $tableName");
                if ($lastResult && $lastResult['last_entry']) {
                    $lastEntryTime = $lastResult['last_entry'];
                }
            } catch (Exception $e) {
            }

            $logs[] = array_merge($info, [
                'type' => $type,
                'entries' => $count,
                'last_entry' => $lastEntryTime,
                'log_source' => 'database'
            ]);
        }

        // File-based logs
        $logs = array_merge($logs, self::getFileLogs());

        return $logs;
    }

    /**
     * Get paginated log content from database
     */
    public static function getDatabaseLogContent($type, $page = 1, $limit = 50, $filters = [])
    {
        $configs = self::getDatabaseLogDefinitions();
        if (!isset($configs[$type]))
            throw new Exception('Unknown log type');

        $config = $configs[$type];
        $table = $config['table'];
        $timestampField = $config['timestamp_field'];
        $fields = $config['select_fields'] ?? '*';
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        // Apply filters (standardized for all types)
        if (!empty($filters['from'])) {
            $from = $filters['from'];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from))
                $from .= ' 00:00:00';
            $where[] = "$timestampField >= ?";
            $params[] = $from;
        }
        if (!empty($filters['to'])) {
            $to = $filters['to'];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))
                $to .= ' 23:59:59';
            $where[] = "$timestampField <= ?";
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

        // Sorting
        $orderSql = "$timestampField DESC";
        if ($type === 'email_logs' && !empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'sent_at_asc':
                    $orderSql = "$timestampField ASC";
                    break;
                case 'subject_asc':
                    $orderSql = "email_subject ASC, $timestampField DESC";
                    break;
                case 'subject_desc':
                    $orderSql = "email_subject DESC, $timestampField DESC";
                    break;
            }
        }

        $countRow = Database::queryOne("SELECT COUNT(*) as total FROM $table$whereSql", $params);
        $totalCount = $countRow ? (int) $countRow['total'] : 0;

        $entries = Database::queryAll(
            "SELECT $fields FROM $table$whereSql ORDER BY $orderSql LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        return [
            'entries' => $entries,
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalCount / $limit)
        ];
    }

    /**
     * Get paginated log content from a file
     */
    public static function getFileLogContent($type, $page = 1, $limit = 50)
    {
        $filename = str_replace('file:', '', $type);
        $logsDir = dirname(__DIR__, 3) . '/logs';
        $path = $logsDir . '/' . $filename;

        if (!file_exists($path) || !is_file($path)) {
            throw new Exception("Log file not found: $filename");
        }

        // Validate that it's actually in the logs dir (security)
        $realLogsDir = realpath($logsDir);
        $realPath = realpath($path);
        if ($realLogsDir === false || $realPath === false || strpos($realPath, $realLogsDir) !== 0) {
            throw new Exception("Access denied to log file: $filename");
        }

        // We want most recent logs first, so we read the file backwards or skip to the end
        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $entries = [];
        $startLine = max(0, $totalLines - ($page * $limit));
        $endLine = max(0, $totalLines - (($page - 1) * $limit));

        // Basic line-based parsing for file logs
        // This is a simplified version, ideally we'd parse timestamps/levels if possible
        for ($i = $endLine - 1; $i >= $startLine; $i--) {
            $file->seek($i);
            $line = trim($file->current());
            if (empty($line))
                continue;

            $entries[] = [
                'timestamp' => date('Y-m-d H:i:s', $file->getMTime()),
                'level' => 'INFO', // Default for file logs
                'message' => $line
            ];
        }

        return [
            'entries' => $entries,
            'total' => $totalLines,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalLines / $limit)
        ];
    }

    /**
     * Search across log tables
     */
    public static function searchLogs($query, $type = '', $filters = [])
    {
        $results = [];
        $searchDefinitions = [
            'client_logs' => ['level', 'message', 'page_url'],
            'analytics_logs' => ['page_url', 'event_type', 'event_data'],
            'error_logs' => ['error_type', 'message', 'file_path'],
            'user_activity_logs' => ['activity_type', 'activity_description'],
            'admin_activity_logs' => ['action_type', 'action_description'],
            'order_logs' => ['order_id', 'action', 'log_message'],
            'inventory_logs' => ['item_sku', 'change_description'],
            'email_logs' => ['to_email', 'email_subject', 'email_type']
        ];

        $targets = (!empty($type) && isset($searchDefinitions[$type]))
            ? [$type => $searchDefinitions[$type]]
            : $searchDefinitions;

        $configs = self::getDatabaseLogDefinitions();

        foreach ($targets as $table => $searchFields) {
            try {
                $config = $configs[$table];
                $timestampField = $config['timestamp_field'];

                $whereOr = [];
                $params = [];
                foreach ($searchFields as $field) {
                    $whereOr[] = "$field LIKE ?";
                    $params[] = "%$query%";
                }

                $sql = "SELECT *, '$table' as source_table FROM $table WHERE (" . implode(' OR ', $whereOr) . ")";

                // Add common filters if needed (e.g. date range)
                // ... (simplified for now to match original)

                $sql .= " ORDER BY $timestampField DESC LIMIT 50";
                $tableResults = Database::queryAll($sql, $params);
                $results = array_merge($results, $tableResults);
            } catch (Exception $e) {
            }
        }

        return $results;
    }

    public static function getLoggingStatus()
    {
        $logsDir = dirname(__DIR__, 3) . '/logs';
        $fileSize = 0;
        if (is_dir($logsDir)) {
            foreach (glob($logsDir . '/*') as $file) {
                if (is_file($file))
                    $fileSize += filesize($file);
            }
        }

        $counts = [];
        foreach (['error_logs', 'client_logs', 'analytics_logs', 'admin_activity_logs', 'email_logs'] as $table) {
            try {
                $row = Database::queryOne("SELECT COUNT(*) as count FROM $table");
                $counts[$table] = $row ? (int) $row['count'] : 0;
            } catch (Exception $e) {
                $counts[$table] = 0;
            }
        }

        return [
            'file_logging' => [
                'enabled' => is_dir($logsDir),
                'total_size' => round($fileSize / (1024 * 1024), 2) . ' MB'
            ],
            'database_logging' => array_merge(['enabled' => true], $counts),
            'retention_days' => 30
        ];
    }

    public static function getDistinctEmailTypes()
    {
        try {
            $rows = Database::queryAll("SELECT DISTINCT email_type FROM email_logs WHERE email_type IS NOT NULL AND email_type != '' ORDER BY email_type ASC");
            $types = [];
            foreach ($rows as $r) {
                if (!empty($r['email_type']))
                    $types[] = $r['email_type'];
            }
            return $types;
        } catch (Exception $e) {
            return [];
        }
    }

    public static function getDatabaseLogDefinitions()
    {
        return [
            'client_logs' => [
                'name' => 'Client Logs',
                'description' => 'Browser-side warnings and errors from the admin UI',
                'category' => 'Application',
                'table' => 'client_logs',
                'timestamp_field' => 'created_at',
                'message_field' => 'message',
                'select_fields' => 'id, admin_user_id, level, message, context_data, page_url, ip_address, created_at'
            ],
            'analytics_logs' => [
                'name' => 'Analytics Logs',
                'description' => 'User activity and page view tracking',
                'category' => 'Analytics',
                'table' => 'analytics_logs',
                'timestamp_field' => 'timestamp',
                'message_field' => 'event_data',
                'select_fields' => 'id, user_id, session_id, page_url, event_type, event_data, ip_address, timestamp'
            ],
            'order_logs' => [
                'name' => 'Order Processing Logs',
                'description' => 'Order creation, updates, and fulfillment',
                'category' => 'E-commerce',
                'table' => 'order_logs',
                'timestamp_field' => 'created_at',
                'message_field' => 'log_message',
                'select_fields' => 'id, order_id, user_id, action, log_message, previous_status, new_status, admin_user_id, created_at'
            ],
            'inventory_logs' => [
                'name' => 'Inventory Change Logs',
                'description' => 'Stock updates and inventory modifications',
                'category' => 'Inventory',
                'table' => 'inventory_logs',
                'timestamp_field' => 'timestamp',
                'message_field' => 'change_description',
                'select_fields' => 'id, item_sku, action_type, change_description, old_quantity, new_quantity, old_price, new_price, user_id, timestamp'
            ],
            'user_activity_logs' => [
                'name' => 'User Activity Logs',
                'description' => 'User authentication and account activity',
                'category' => 'Security',
                'table' => 'user_activity_logs',
                'timestamp_field' => 'timestamp',
                'message_field' => 'activity_description',
                'select_fields' => 'id, user_id, session_id, activity_type, activity_description, target_type, target_id, ip_address, timestamp'
            ],
            'error_logs' => [
                'name' => 'Application Error Logs',
                'description' => 'PHP errors, exceptions, and debugging info',
                'category' => 'Application',
                'table' => 'error_logs',
                'timestamp_field' => 'created_at',
                'message_field' => 'message',
                'select_fields' => 'id, error_type, message, context_data, user_id, file_path, line_number, ip_address, created_at'
            ],
            'admin_activity_logs' => [
                'name' => 'Admin Activity Logs',
                'description' => 'Administrative actions and system changes',
                'category' => 'Administration',
                'table' => 'admin_activity_logs',
                'timestamp_field' => 'timestamp',
                'message_field' => 'action_description',
                'select_fields' => 'id, admin_user_id, action_type, action_description, target_type, target_id, ip_address, timestamp'
            ],
            'email_logs' => [
                'name' => 'Email Logs',
                'description' => 'Email sending history and delivery status',
                'category' => 'Communication',
                'table' => 'email_logs',
                'timestamp_field' => 'sent_at',
                'message_field' => 'email_subject',
                'select_fields' => 'id, to_email, from_email, email_subject, email_type, status, error_message, sent_at'
            ]
        ];
    }

    private static function getFileLogs()
    {
        $logs = [];
        $logsDir = dirname(__DIR__, 3) . '/logs';
        if (!$logsDir || !is_dir($logsDir))
            return [];

        $friendly = [
            'php_error.log' => ['PHP Error Log', 'PHP errors and exceptions'],
            'application.log' => ['Application Log', 'App-level info/debug messages'],
            'square_diagnostics.log' => ['Square Diagnostics Log', 'Square API connection and sync diagnostics'],
            'vite_server.log' => ['Vite Dev Server Log', 'Front-end dev server output'],
            'php_server.log' => ['PHP Server Log', 'Built-in PHP server output'],
            'monitor.log' => ['Monitor Log', 'Process and health monitor'],
            'monitor_root.log' => ['Monitor (Root) Log', 'Root-level monitor messages'],
            'autostart.log' => ['Autostart Log', 'Startup and bootstrap output'],
        ];

        foreach (glob($logsDir . '/*') as $file) {
            if (!is_file($file))
                continue;
            $base = basename($file);
            if ($base[0] === '.' || !preg_match('/\.log(\.|$)/', $base))
                continue;

            $mtime = @filemtime($file) ?: null;
            $size = @filesize($file) ?: 0;

            $logs[] = [
                'type' => 'file:' . $base,
                'name' => $friendly[$base][0] ?? $base,
                'description' => $friendly[$base][1] ?? 'Log file',
                'category' => 'File',
                'last_entry' => $mtime ? date('c', $mtime) : null,
                'log_source' => 'file',
                'filename' => $base,
                'path' => 'logs/' . $base,
                'size_bytes' => $size,
                'size' => round($size / (1024 * 1024), 2) . ' MB'
            ];
        }
        return $logs;
    }
}
