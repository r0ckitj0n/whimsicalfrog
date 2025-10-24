<?php

/**
 * Admin Activity Logger
 *
 * Specialized logging for SEO and retail admin activities
 */

class AdminLogger
{
    private static $userId = null;
    private static $sessionId = null;
    private static $ipAddress = null;
    private static $userAgent = null;

    /**
     * Initialize the admin logger
     */
    public static function init()
    {
        try {
            Database::getInstance();
            self::$sessionId = session_id();
            self::$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            self::$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            // Get current user if available
            if (isset($_SESSION['user']['id'])) {
                self::$userId = $_SESSION['user']['id'];
            }

            // Create admin log table if it doesn't exist
            self::createAdminLogTable();

        } catch (Exception $e) {
            error_log("AdminLogger init failed: " . $e->getMessage());
        }
    }

    /**
     * Create admin log table if it doesn't exist
     */
    private static function createAdminLogTable()
    {
        try {
            $exists = Database::queryAll("SHOW TABLES LIKE 'admin_activity_logs'");
            if (!$exists) {
                $sql = "CREATE TABLE admin_activity_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_user_id INT NULL,
                    action_type VARCHAR(100) NOT NULL,
                    action_description TEXT,
                    target_type VARCHAR(100) NULL,
                    target_id VARCHAR(100) NULL,
                    ip_address VARCHAR(45) NULL,
                    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_admin_user_id (admin_user_id),
                    INDEX idx_action_type (action_type),
                    INDEX idx_timestamp (`timestamp`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                Database::execute($sql);
                return;
            }

            $cols = Database::queryAll("SHOW COLUMNS FROM admin_activity_logs");
            $colNames = array_map(function($r){ return $r['Field']; }, $cols ?: []);

            if (in_array('user_id', $colNames) && !in_array('admin_user_id', $colNames)) {
                Database::execute("ALTER TABLE admin_activity_logs CHANGE `user_id` `admin_user_id` INT NULL");
            }
            if (in_array('activity_type', $colNames) && !in_array('action_type', $colNames)) {
                Database::execute("ALTER TABLE admin_activity_logs CHANGE `activity_type` `action_type` VARCHAR(100) NOT NULL");
            }
            if (in_array('activity_description', $colNames) && !in_array('action_description', $colNames)) {
                Database::execute("ALTER TABLE admin_activity_logs CHANGE `activity_description` `action_description` TEXT");
            }
            if (in_array('entity_type', $colNames) && !in_array('target_type', $colNames)) {
                Database::execute("ALTER TABLE admin_activity_logs CHANGE `entity_type` `target_type` VARCHAR(100) NULL");
            }
            if (in_array('entity_id', $colNames) && !in_array('target_id', $colNames)) {
                Database::execute("ALTER TABLE admin_activity_logs CHANGE `entity_id` `target_id` VARCHAR(100) NULL");
            }
            if (!in_array('timestamp', $colNames)) {
                Database::execute("ALTER TABLE admin_activity_logs ADD COLUMN `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            }
            try { Database::execute("CREATE INDEX idx_timestamp ON admin_activity_logs (`timestamp`)"); } catch (Exception $e) {}
            try { Database::execute("CREATE INDEX idx_admin_user_id ON admin_activity_logs (admin_user_id)"); } catch (Exception $e) {}
            try { Database::execute("CREATE INDEX idx_action_type ON admin_activity_logs (action_type)"); } catch (Exception $e) {}
        } catch (Exception $e) {
            error_log("Failed to create/migrate admin_activity_logs table: " . $e->getMessage());
        }
    }

    /**
     * Log admin activity
     */
    public static function logActivity($activityType, $category, $description, $entityType = null, $entityId = null, $oldValues = null, $newValues = null)
    {
        try {
            $actionDescription = $description;
            if ($category) {
                $actionDescription = ($description ? ($description . ' ') : '') . "[category: $category]";
            }

            $result = Database::execute(
                "INSERT INTO admin_activity_logs (admin_user_id, action_type, action_description, target_type, target_id, ip_address) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    self::$userId,
                    $activityType,
                    $actionDescription,
                    $entityType,
                    $entityId,
                    self::$ipAddress
                ]
            );

            Logger::info("Admin Activity: $activityType", [ 'category' => $category, 'description' => $description, 'entity_type' => $entityType, 'entity_id' => $entityId, 'user_id' => self::$userId ]);

            return $result;
        } catch (Exception $e) {
            error_log("Failed to log admin activity: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log inventory management activities
     */
    public static function logInventoryActivity($action, $sku, $oldData = null, $newData = null)
    {
        return self::logActivity(
            $action,
            'inventory',
            "Inventory $action for SKU: $sku",
            'item',
            $sku,
            $oldData,
            $newData
        );
    }

    /**
     * Log order management activities
     */
    public static function logOrderActivity($action, $orderId, $oldData = null, $newData = null)
    {
        return self::logActivity(
            $action,
            'orders',
            "Order $action for Order ID: $orderId",
            'order',
            $orderId,
            $oldData,
            $newData
        );
    }

    /**
     * Log customer management activities
     */
    public static function logCustomerActivity($action, $customerId, $oldData = null, $newData = null)
    {
        return self::logActivity(
            $action,
            'customers',
            "Customer $action for Customer ID: $customerId",
            'customer',
            $customerId,
            $oldData,
            $newData
        );
    }

    /**
     * Log SEO activities
     */
    public static function logSEOActivity($action, $description, $entityType = null, $entityId = null, $oldData = null, $newData = null)
    {
        return self::logActivity(
            $action,
            'seo',
            $description,
            $entityType,
            $entityId,
            $oldData,
            $newData
        );
    }

    /**
     * Log marketing activities
     */
    public static function logMarketingActivity($action, $description, $entityType = null, $entityId = null, $oldData = null, $newData = null)
    {
        return self::logActivity(
            $action,
            'marketing',
            $description,
            $entityType,
            $entityId,
            $oldData,
            $newData
        );
    }

    /**
     * Log system configuration changes
     */
    public static function logConfigActivity($action, $configKey, $oldValue = null, $newValue = null)
    {
        return self::logActivity(
            $action,
            'configuration',
            "Configuration change for: $configKey",
            'config',
            $configKey,
            $oldValue ? ['value' => $oldValue] : null,
            $newValue ? ['value' => $newValue] : null
        );
    }

    /**
     * Log user management activities
     */
    public static function logUserActivity($action, $targetUserId, $oldData = null, $newData = null)
    {
        return self::logActivity(
            $action,
            'user_management',
            "User $action for User ID: $targetUserId",
            'user',
            $targetUserId,
            $oldData,
            $newData
        );
    }

    /**
     * Log POS activities
     */
    public static function logPOSActivity($action, $description, $entityType = null, $entityId = null, $data = null)
    {
        return self::logActivity(
            $action,
            'pos',
            $description,
            $entityType,
            $entityId,
            null,
            $data
        );
    }

    /**
     * Get admin activity logs with filtering
     */
    public static function getActivityLogs($filters = [], $limit = 100, $offset = 0)
    {

        try {
            $whereConditions = ['1=1'];
            $params = [];

            if (!empty($filters['user_id'])) {
                $whereConditions[] = 'user_id = ?';
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['category'])) {
                $whereConditions[] = 'activity_category = ?';
                $params[] = $filters['category'];
            }

            if (!empty($filters['activity_type'])) {
                $whereConditions[] = 'activity_type = ?';
                $params[] = $filters['activity_type'];
            }

            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'created_at >= ?';
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'created_at <= ?';
                $params[] = $filters['date_to'];
            }

            $sql = "SELECT * FROM admin_activity_logs 
                    WHERE " . implode(' AND ', $whereConditions) . "
                    ORDER BY created_at DESC 
                    LIMIT ? OFFSET ?";

            $params[] = $limit;
            $params[] = $offset;

            return Database::queryAll($sql, $params);
        } catch (Exception $e) {
            error_log("Failed to get admin activity logs: " . $e->getMessage());
            return [];
        }
    }
}
