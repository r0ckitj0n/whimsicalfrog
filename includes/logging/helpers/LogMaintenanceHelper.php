<?php
// includes/logging/helpers/LogMaintenanceHelper.php

class LogMaintenanceHelper
{
    private static function isValidIdentifier($value)
    {
        return is_string($value) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) === 1;
    }

    /**
     * Create a log table if it doesn't exist
     */
    public static function createLogTable($tableName, $type)
    {
        if (!self::isValidIdentifier($tableName)) {
            error_log('createLogTable rejected invalid table identifier: ' . (string)$tableName);
            return false;
        }
        $sql = '';
        switch ($type) {
            case 'client_logs':
                $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_user_id INT NULL,
                    level VARCHAR(20) NOT NULL DEFAULT 'info',
                    message TEXT NOT NULL,
                    context_data JSON,
                    page_url VARCHAR(500),
                    user_agent TEXT,
                    ip_address VARCHAR(45),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_created_at (created_at),
                    INDEX idx_level (level),
                    INDEX idx_admin_user_id (admin_user_id)
                )";
                break;
            case 'analytics_logs':
                $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    session_id VARCHAR(255),
                    page_url VARCHAR(500),
                    event_type VARCHAR(100),
                    event_data JSON,
                    user_agent TEXT,
                    ip_address VARCHAR(45),
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_user_id (user_id),
                    INDEX idx_event_type (event_type)
                )";
                break;
            case 'error_logs':
                $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    error_type VARCHAR(100) NOT NULL DEFAULT 'ERROR',
                    message TEXT NOT NULL,
                    context_data JSON,
                    user_id INT NULL,
                    file_path VARCHAR(500),
                    line_number INT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_created_at (created_at),
                    INDEX idx_error_type (error_type),
                    INDEX idx_file_path (file_path)
                )";
                break;
            case 'user_activity_logs':
                $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    session_id VARCHAR(255),
                    activity_type VARCHAR(100) NOT NULL,
                    activity_description TEXT,
                    target_type VARCHAR(100),
                    target_id INT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_activity_type (activity_type)
                )";
                break;
            case 'admin_activity_logs':
                $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_user_id INT NOT NULL,
                    action_type VARCHAR(100) NOT NULL,
                    action_description TEXT,
                    target_type VARCHAR(100),
                    target_id INT,
                    ip_address VARCHAR(45),
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_admin_user_id (admin_user_id),
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_action_type (action_type)
                )";
                break;
            case 'order_logs':
                $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id VARCHAR(50) NOT NULL,
                    user_id INT NULL,
                    action VARCHAR(100) NOT NULL,
                    log_message TEXT,
                    previous_status VARCHAR(50),
                    new_status VARCHAR(50),
                    admin_user_id INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_order_id (order_id),
                    INDEX idx_created_at (created_at),
                    INDEX idx_action (action)
                )";
                break;
            case 'inventory_logs':
                $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    item_sku VARCHAR(50) NOT NULL,
                    action_type VARCHAR(100) NOT NULL,
                    change_description TEXT,
                    old_quantity INT,
                    new_quantity INT,
                    old_price DECIMAL(10,2),
                    new_price DECIMAL(10,2),
                    user_id INT NULL,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_item_sku (item_sku),
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_action_type (action_type)
                )";
                break;
            case 'email_logs':
                $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    to_email VARCHAR(255) NOT NULL,
                    from_email VARCHAR(255),
                    subject VARCHAR(500),
                    email_subject VARCHAR(500),
                    content LONGTEXT NULL,
                    email_type VARCHAR(100),
                    status VARCHAR(50) DEFAULT 'sent',
                    error_message TEXT NULL,
                    sent_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    order_id VARCHAR(50) NULL,
                    created_by VARCHAR(100) NULL,
                    cc_email TEXT NULL,
                    bcc_email TEXT NULL,
                    reply_to VARCHAR(255) NULL,
                    is_html TINYINT(1) NOT NULL DEFAULT 1,
                    headers_json LONGTEXT NULL,
                    attachments_json LONGTEXT NULL,
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_sent_at (sent_at),
                    INDEX idx_to_email (to_email),
                    INDEX idx_status (status),
                    INDEX idx_order_id (order_id)
                )";
                break;
        }

        if ($sql) {
            try {
                Database::execute($sql);
                return true;
            } catch (Exception $e) {
                error_log("Failed to create log table $tableName: " . $e->getMessage());
            }
        }
        return false;
    }

    /**
     * Clear all entries from a database log table
     */
    public static function clearLog($type)
    {
        $validTables = [
            'analytics_logs', 'error_logs', 'user_activity_logs',
            'admin_activity_logs', 'order_logs', 'inventory_logs', 'email_logs',
            'client_logs'
        ];

        if (!in_array($type, $validTables, true)) {
            throw new Exception('Invalid log type');
        }
        if (!self::isValidIdentifier($type)) {
            throw new Exception('Invalid log table');
        }

        try {
            $affected = Database::execute("DELETE FROM `$type`");
            if ($affected !== false) {
                Database::execute("ALTER TABLE `$type` AUTO_INCREMENT = 1");
                return true;
            }
        } catch (Exception $e) {
            throw new Exception('Database error: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * Delete log entries older than 30 days
     */
    public static function cleanupOldLogs()
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        $results = [];

        $logTables = [
            'analytics_logs' => 'timestamp',
            'error_logs' => 'created_at',
            'user_activity_logs' => 'timestamp',
            'admin_activity_logs' => 'timestamp',
            'order_logs' => 'created_at',
            'inventory_logs' => 'timestamp',
            'email_logs' => 'sent_at',
            'client_logs' => 'created_at'
        ];

        foreach ($logTables as $table => $timestampField) {
            try {
                if (!self::isValidIdentifier($table) || !self::isValidIdentifier($timestampField)) {
                    $results[$table] = ['deleted' => 0, 'status' => 'error', 'error' => 'Invalid table configuration'];
                    continue;
                }
                // Check if table exists before trying to cleanup
                $tableCheck = Database::queryOne(
                    "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
                    [$table]
                );
                if (((int)($tableCheck['c'] ?? 0)) === 0) {
                    continue;
                }

                $countRow = Database::queryOne("SELECT COUNT(*) as count FROM `$table` WHERE `$timestampField` < ?", [$cutoffDate]);
                $oldCount = $countRow ? (int)$countRow['count'] : 0;

                if ($oldCount > 0) {
                    $deleted = Database::execute("DELETE FROM `$table` WHERE `$timestampField` < ?", [$cutoffDate]);
                    $results[$table] = [
                        'deleted' => $oldCount,
                        'status' => $deleted !== false ? 'success' : 'failed'
                    ];
                } else {
                    $results[$table] = ['deleted' => 0, 'status' => 'no_old_entries'];
                }
            } catch (Exception $e) {
                $results[$table] = ['deleted' => 0, 'status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
