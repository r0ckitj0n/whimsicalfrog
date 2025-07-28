<?php
/**
 * Admin Activity Logger
 * 
 * Specialized logging for SEO and retail admin activities
 */

class AdminLogger {
    private static $pdo = null;
    private static $userId = null;
    private static $sessionId = null;
    private static $ipAddress = null;
    private static $userAgent = null;
    
    /**
     * Initialize the admin logger
     */
    public static function init() {
        try {
            self::$pdo = Database::getInstance();
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
    private static function createAdminLogTable() {
        if (!self::$pdo) return;
        
        try {
            $sql = "CREATE TABLE IF NOT EXISTS admin_activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                session_id VARCHAR(255) NULL,
                activity_type VARCHAR(100) NOT NULL,
                activity_category VARCHAR(50) NOT NULL,
                activity_description TEXT NOT NULL,
                entity_type VARCHAR(50) NULL,
                entity_id VARCHAR(100) NULL,
                old_values JSON NULL,
                new_values JSON NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_activity_type (activity_type),
                INDEX idx_activity_category (activity_category),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            self::$pdo->exec($sql);
        } catch (Exception $e) {
            error_log("Failed to create admin_activity_logs table: " . $e->getMessage());
        }
    }
    
    /**
     * Log admin activity
     */
    public static function logActivity($activityType, $category, $description, $entityType = null, $entityId = null, $oldValues = null, $newValues = null) {
        if (!self::$pdo) return false;
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO admin_activity_logs (
                    user_id, session_id, activity_type, activity_category, 
                    activity_description, entity_type, entity_id, 
                    old_values, new_values, ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                self::$userId,
                self::$sessionId,
                $activityType,
                $category,
                $description,
                $entityType,
                $entityId,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                self::$ipAddress,
                self::$userAgent
            ]);
            
            // Also log to file for backup
            Logger::info("Admin Activity: $activityType", [
                'category' => $category,
                'description' => $description,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'user_id' => self::$userId
            ]);
            
            return $result;
        } catch (Exception $e) {
            error_log("Failed to log admin activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log inventory management activities
     */
    public static function logInventoryActivity($action, $sku, $oldData = null, $newData = null) {
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
    public static function logOrderActivity($action, $orderId, $oldData = null, $newData = null) {
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
    public static function logCustomerActivity($action, $customerId, $oldData = null, $newData = null) {
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
    public static function logSEOActivity($action, $description, $entityType = null, $entityId = null, $oldData = null, $newData = null) {
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
    public static function logMarketingActivity($action, $description, $entityType = null, $entityId = null, $oldData = null, $newData = null) {
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
    public static function logConfigActivity($action, $configKey, $oldValue = null, $newValue = null) {
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
    public static function logUserActivity($action, $targetUserId, $oldData = null, $newData = null) {
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
    public static function logPOSActivity($action, $description, $entityType = null, $entityId = null, $data = null) {
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
    public static function getActivityLogs($filters = [], $limit = 100, $offset = 0) {
        if (!self::$pdo) return [];
        
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
            
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get admin activity logs: " . $e->getMessage());
            return [];
        }
    }
}
