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
    private $user_id = null;
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
                $this->user_id = $_SESSION['user']['id'];
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
    public static function init()
    { /* deprecated */
    }

    /**
     * Create log tables if they don't exist
     */
    private function createLogTables()
    {
        if (!$this->pdo) {
            return;
        }

        require_once __DIR__ . '/helpers/LogTableDefinitions.php';
        $tables = LogTableDefinitions::getSchemas();

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
                $this->user_id,
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
    public function logUserActivity($activityType, $description, $targetType = null, $targetId = null, $user_id = null)
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
                $user_id ?: $this->user_id,
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
                $adminUserId ?: $this->user_id,
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
                $this->user_id
            ]);
        } catch (Exception $e) {
            error_log("Failed to log error: " . $e->getMessage());
        }
    }

    /**
     * Log order activities
     */
    public function logOrderActivity($order_id, $action, $message, $previousStatus = null, $newStatus = null, $user_id = null, $adminUserId = null)
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
                $order_id,
                $user_id ?: $this->user_id,
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
    public function logInventoryChange($item_sku, $actionType, $description, $oldQuantity = null, $newQuantity = null, $oldPrice = null, $newPrice = null, $user_id = null)
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
                $item_sku,
                $actionType,
                $description,
                $oldQuantity,
                $newQuantity,
                $oldPrice,
                $newPrice,
                $user_id ?: $this->user_id
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
                INSERT INTO email_logs (to_email, from_email, email_subject, email_type, status, error_message)
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
        $inst = self::getInstance();
        if (!$inst || !$inst->pdo) {
            return;
        }

        try {
            $stmt = $inst->pdo->prepare("
                INSERT INTO analytics_logs (user_id, session_id, page_url, event_type, event_data, user_agent, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $inst->user_id,
                $inst->sessionId,
                $_SERVER['REQUEST_URI'] ?? 'unknown',
                $eventType,
                json_encode($eventData),
                $inst->userAgent,
                $inst->ipAddress
            ]);
        } catch (Exception $e) {
            error_log("Failed to log analytics event: " . $e->getMessage());
        }
    }

    public static function __callStatic($name, $arguments)
    {
        $inst = self::getInstance();
        if ($inst && method_exists($inst, $name)) {
            return $inst->$name(...$arguments);
        }
        throw new BadMethodCallException("Method $name does not exist on DatabaseLogger");
    }
}
