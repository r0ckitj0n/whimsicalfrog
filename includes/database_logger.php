<?php
/**
 * Database Logger - Comprehensive logging system for WhimsicalFrog
 *
 * This logger writes to the database log tables that are displayed in the Website Logs modal.
 * It integrates with the existing Logger class and provides automatic logging for user activity,
 * errors, admin actions, etc.
 */

class DatabaseLogger
{
    private static $instance = null;
    private $pdo = null;
    private $userId = null;
    private $sessionId = null;
    private $ipAddress = null;
    private $userAgent = null;

    private function __construct()
    {
        try {
            $this->pdo = Database::getInstance();
            $this->sessionId = session_id();
            $this->ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

            if (isset($_SESSION['user']['id'])) {
                $this->userId = $_SESSION['user']['id'];
            }

            $this->createLogTables();
        } catch (Exception $e) {
            error_log("DatabaseLogger init failed: " . $e->getMessage());
            $this->pdo = null; // Ensure pdo is null on failure
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * The init method is now deprecated. Use getInstance() instead.
     */
    public static function init() { /* deprecated */ }

    /**
     * Create log tables if they don't exist
     */
    private function createLogTables()
    {
        if (!$this->pdo) {
            return;
        }

        $tables = [
            'analytics_logs' => "CREATE TABLE IF NOT EXISTS analytics_logs (
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
            )",

            'error_logs' => "CREATE TABLE IF NOT EXISTS error_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                error_level VARCHAR(20) NOT NULL DEFAULT 'ERROR',
                error_message TEXT NOT NULL,
                file_path VARCHAR(500),
                line_number INT,
                stack_trace TEXT,
                user_id INT NULL,
                session_id VARCHAR(255),
                request_uri VARCHAR(500),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_timestamp (timestamp),
                INDEX idx_error_level (error_level),
                INDEX idx_file_path (file_path)
            )",

            'user_activity_logs' => "CREATE TABLE IF NOT EXISTS user_activity_logs (
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
            )",

            'admin_activity_logs' => "CREATE TABLE IF NOT EXISTS admin_activity_logs (
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
            )",

            'order_logs' => "CREATE TABLE IF NOT EXISTS order_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
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
            )",

            'inventory_logs' => "CREATE TABLE IF NOT EXISTS inventory_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_id INT NOT NULL,
                action_type VARCHAR(100) NOT NULL,
                change_description TEXT,
                old_quantity INT,
                new_quantity INT,
                old_price DECIMAL(10,2),
                new_price DECIMAL(10,2),
                user_id INT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_item_id (item_id),
                INDEX idx_timestamp (timestamp),
                INDEX idx_action_type (action_type)
            )",

            'email_logs' => "CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                from_email VARCHAR(255),
                subject VARCHAR(500),
                email_type VARCHAR(100),
                status VARCHAR(50) DEFAULT 'sent',
                error_message TEXT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_sent_at (sent_at),
                INDEX idx_to_email (to_email),
                INDEX idx_status (status)
            )"
        ];

        foreach ($tables as $tableName => $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (Exception $e) {
                error_log("Failed to create table $tableName: " . $e->getMessage());
            }
        }
    }

    /**
     * Log page view and user activity
     */
    public function logPageView($pageUrl, $eventData = [])
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO analytics_logs (user_id, session_id, page_url, event_type, event_data, user_agent, ip_address)
                VALUES (?, ?, ?, 'page_view', ?, ?, ?)
            ");

            $stmt->execute([
                $this->userId,
                $this->sessionId,
                $pageUrl,
                json_encode($eventData),
                $this->userAgent,
                $this->ipAddress
            ]);
        } catch (Exception $e) {
            error_log("Failed to log page view: " . $e->getMessage());
        }
    }

    /**
     * Log user activity (login, logout, registration, etc.)
     */
    public function logUserActivity($activityType, $description, $targetType = null, $targetId = null, $userId = null)
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_activity_logs (user_id, session_id, activity_type, activity_description, target_type, target_id, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId ?: $this->userId,
                $this->sessionId,
                $activityType,
                $description,
                $targetType,
                $targetId,
                $this->ipAddress,
                $this->userAgent
            ]);
        } catch (Exception $e) {
            error_log("Failed to log user activity: " . $e->getMessage());
        }
    }

    /**
     * Log admin actions
     */
    public function logAdminActivity($actionType, $description, $targetType = null, $targetId = null, $adminUserId = null)
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_activity_logs (admin_user_id, action_type, action_description, target_type, target_id, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $adminUserId ?: $this->userId,
                $actionType,
                $description,
                $targetType,
                $targetId,
                $this->ipAddress
            ]);
        } catch (Exception $e) {
            error_log("Failed to log admin activity: " . $e->getMessage());
        }
    }

    /**
     * Log application errors
     */
    public function logError($level, $message, $file = null, $line = null, $stackTrace = null)
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO error_logs (error_type, message, file_path, line_number, ip_address, user_agent, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $level,
                $message,
                $file,
                $line,
                $this->ipAddress,
                $this->userAgent,
                $this->userId
            ]);
        } catch (Exception $e) {
            error_log("Failed to log error: " . $e->getMessage());
        }
    }

    /**
     * Log order activities
     */
    public function logOrderActivity($orderId, $action, $message, $previousStatus = null, $newStatus = null, $userId = null, $adminUserId = null)
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO order_logs (order_id, user_id, action, log_message, previous_status, new_status, admin_user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $orderId,
                $userId ?: $this->userId,
                $action,
                $message,
                $previousStatus,
                $newStatus,
                $adminUserId
            ]);
        } catch (Exception $e) {
            error_log("Failed to log order activity: " . $e->getMessage());
        }
    }

    /**
     * Log inventory changes
     */
    public function logInventoryChange($itemSku, $actionType, $description, $oldQuantity = null, $newQuantity = null, $oldPrice = null, $newPrice = null, $userId = null)
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO inventory_logs (item_sku, action_type, change_description, old_quantity, new_quantity, old_price, new_price, user_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $itemSku,
                $actionType,
                $description,
                $oldQuantity,
                $newQuantity,
                $oldPrice,
                $newPrice,
                $userId ?: $this->userId
            ]);
        } catch (Exception $e) {
            error_log("Failed to log inventory change: " . $e->getMessage());
        }
    }

    /**
     * Log email sending
     */
    public function logEmail($toEmail, $fromEmail, $subject, $emailType, $status = 'sent', $errorMessage = null)
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_logs (to_email, from_email, subject, email_type, status, error_message)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $toEmail,
                $fromEmail,
                $subject,
                $emailType,
                $status,
                $errorMessage
            ]);
        } catch (Exception $e) {
            error_log("Failed to log email: " . $e->getMessage());
        }
    }

    /**
     * Log custom analytics events
     */
    public static function logAnalyticsEvent($eventType, $eventData = [])
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO analytics_logs (user_id, session_id, page_url, event_type, event_data, user_agent, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $this->userId,
                $this->sessionId,
                $_SERVER['REQUEST_URI'] ?? 'unknown',
                $eventType,
                json_encode($eventData),
                $this->userAgent,
                $this->ipAddress
            ]);
        } catch (Exception $e) {
            error_log("Failed to log analytics event: " . $e->getMessage());
        }
    }
}

// Auto-initialize if database is available
if (class_exists('Database')) {
    try {
        DatabaseLogger::getInstance()->logError('INFO', 'DatabaseLogger initialized');
    } catch (Exception $e) {
        // Fail silently if database is not available
    }
}

// Global error handler to capture PHP errors
function whimsicalfrog_error_handler($severity, $message, $file, $line)
{
    $errorLevel = 'ERROR';
    switch ($severity) {
        case E_WARNING:
        case E_USER_WARNING:
            $errorLevel = 'WARNING';
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $errorLevel = 'INFO';
            break;
        case E_ERROR:
        case E_USER_ERROR:
        case E_RECOVERABLE_ERROR:
            $errorLevel = 'ERROR';
            break;
    }

    DatabaseLogger::getInstance()->logError($errorLevel, $message, $file, $line);

    // Don't prevent normal error handling
    return false;
}

// Global exception handler
function whimsicalfrog_exception_handler($exception)
{
    $logger = DatabaseLogger::getInstance();
    $logger->logError('ERROR', 'Uncaught exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine());
}

// Set error and exception handlers
set_error_handler('whimsicalfrog_error_handler');
set_exception_handler('whimsicalfrog_exception_handler');
?>