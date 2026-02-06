<?php

require_once __DIR__ . '/Constants.php';

/**
 * Logging Configuration
 *
 * Centralized configuration for all logging systems
 */

class LoggingConfig
{
    // Log levels
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';

    // Log categories
    public const CATEGORY_SYSTEM = WF_Constants::ACTIVITY_CATEGORY_SYSTEM;
    public const CATEGORY_ADMIN = WF_Constants::ROLE_ADMIN;
    public const CATEGORY_USER = 'user';
    public const CATEGORY_API = WF_Constants::ACTIVITY_CATEGORY_API;
    public const CATEGORY_DATABASE = WF_Constants::ACTIVITY_CATEGORY_DATABASE;
    public const CATEGORY_EMAIL = WF_Constants::ACTIVITY_CATEGORY_EMAIL;
    public const CATEGORY_SEO = WF_Constants::ACTIVITY_CATEGORY_SEO;
    public const CATEGORY_MARKETING = WF_Constants::ACTIVITY_CATEGORY_MARKETING;
    public const CATEGORY_INVENTORY = WF_Constants::ACTIVITY_CATEGORY_INVENTORY;
    public const CATEGORY_ORDERS = WF_Constants::ACTIVITY_CATEGORY_ORDERS;
    public const CATEGORY_POS = WF_Constants::ACTIVITY_CATEGORY_POS;
    public const CATEGORY_SECURITY = WF_Constants::ACTIVITY_CATEGORY_SECURITY;

    // File logging configuration
    public static function getFileLogConfig()
    {
        $logsDir = __DIR__ . '/../logs';

        return [
            'enabled' => true,
            'directory' => $logsDir,
            'files' => [
                'application' => $logsDir . '/application.log',
                'errors' => $logsDir . '/errors.log',
                WF_Constants::ROLE_ADMIN => $logsDir . '/admin_activity.log',
                WF_Constants::ACTIVITY_CATEGORY_API => $logsDir . '/api_requests.log',
                'security' => $logsDir . '/security.log',
                'email' => $logsDir . '/email.log',
                'database' => $logsDir . '/database.log'
            ],
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'max_files' => 5, // Keep 5 rotated files
            'levels' => [
                self::LEVEL_DEBUG,
                self::LEVEL_INFO,
                self::LEVEL_WARNING,
                self::LEVEL_ERROR,
                self::LEVEL_CRITICAL
            ]
        ];
    }

    // Database logging configuration
    public static function getDatabaseLogConfig()
    {
        return [
            'enabled' => true,
            'primary' => true, // Database is primary logging method
            'tables' => [
                'error_logs' => 'error_logs',
                'analytics_logs' => 'analytics_logs',
                'email_logs' => 'email_logs',
                'admin_activity_logs' => 'admin_activity_logs'
            ],
            'retention_days' => 90, // Keep logs for 90 days
            'cleanup_enabled' => true
        ];
    }

    // SEO logging configuration
    public static function getSEOLogConfig()
    {
        return [
            'enabled' => true,
            'track_page_views' => true,
            'track_search_queries' => true,
            'track_redirects' => true,
            'track_404_errors' => true,
            'track_sitemap_generation' => true,
            'track_meta_changes' => true,
            'track_schema_updates' => true
        ];
    }

    // Retail admin logging configuration
    public static function getRetailAdminLogConfig()
    {
        return [
            'enabled' => true,
            'track_inventory_changes' => true,
            'track_order_management' => true,
            'track_customer_management' => true,
            'track_pricing_changes' => true,
            'track_category_changes' => true,
            'track_user_management' => true,
            'track_pos_activities' => true,
            'track_report_generation' => true,
            'track_configuration_changes' => true,
            'track_marketing_activities' => true
        ];
    }

    // Performance logging configuration
    public static function getPerformanceLogConfig()
    {
        return [
            'enabled' => true,
            'track_slow_queries' => true,
            'slow_query_threshold' => 2.0, // seconds
            'track_memory_usage' => true,
            'track_execution_time' => true,
            'track_api_response_times' => true
        ];
    }

    // Security logging configuration
    public static function getSecurityLogConfig()
    {
        return [
            'enabled' => true,
            'track_login_attempts' => true,
            'track_failed_logins' => true,
            'track_permission_changes' => true,
            'track_suspicious_activity' => true,
            'track_admin_access' => true,
            'track_file_uploads' => true,
            'track_database_changes' => true
        ];
    }

    // Email logging configuration
    public static function getEmailLogConfig()
    {
        return [
            'enabled' => true,
            'track_all_emails' => true,
            'track_delivery_status' => true,
            'track_open_rates' => true,
            'track_click_rates' => true,
            'store_email_content' => true,
            'retention_days' => 365 // Keep email logs for 1 year
        ];
    }

    // Get complete logging configuration
    public static function getCompleteConfig()
    {
        return [
            'file_logging' => self::getFileLogConfig(),
            'database_logging' => self::getDatabaseLogConfig(),
            'seo_logging' => self::getSEOLogConfig(),
            'retail_admin_logging' => self::getRetailAdminLogConfig(),
            'performance_logging' => self::getPerformanceLogConfig(),
            'security_logging' => self::getSecurityLogConfig(),
            'email_logging' => self::getEmailLogConfig()
        ];
    }

    // Initialize all logging systems
    public static function initializeLogging()
    {
        $config = self::getCompleteConfig();

        // Ensure logs directory exists
        $logsDir = $config['file_logging']['directory'];
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }

        // Set appropriate permissions for log files
        if (is_dir($logsDir)) {
            chmod($logsDir, 0755);
        }

        return $config;
    }

    // Log rotation helper
    public static function rotateLogFile($filePath, $maxSize = null, $maxFiles = null)
    {
        $config = self::getFileLogConfig();
        $maxSize = $maxSize ?: $config['max_file_size'];
        $maxFiles = $maxFiles ?: $config['max_files'];

        if (!file_exists($filePath)) {
            return;
        }

        if (filesize($filePath) > $maxSize) {
            // Rotate existing files
            for ($i = $maxFiles - 1; $i > 0; $i--) {
                $oldFile = $filePath . '.' . $i;
                $newFile = $filePath . '.' . ($i + 1);

                if (file_exists($oldFile)) {
                    if ($i == $maxFiles - 1) {
                        unlink($oldFile); // Delete oldest file
                    } else {
                        rename($oldFile, $newFile);
                    }
                }
            }

            // Move current file to .1
            rename($filePath, $filePath . '.1');

            // Create new empty file
            touch($filePath);
            chmod($filePath, 0644);
        }
    }

    // Clean up old database logs
    public static function cleanupDatabaseLogs()
    {
        $config = self::getDatabaseLogConfig();

        if (!$config['cleanup_enabled']) {
            return;
        }

        try {
            $pdo = Database::getInstance();
            $retentionDays = $config['retention_days'];
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));

            foreach ($config['tables'] as $table) {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE created_at < ?");
                $stmt->execute([$cutoffDate]);

                $deletedRows = $stmt->rowCount();
                if ($deletedRows > 0) {
                    Logger::info("Cleaned up old logs from {$table}", [
                        'deleted_rows' => $deletedRows,
                        'cutoff_date' => $cutoffDate
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("Failed to cleanup database logs: " . $e->getMessage());
        }
    }
}
