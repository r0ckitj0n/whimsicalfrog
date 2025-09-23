<?php

/**
 * Centralized Logging Helper
 *
 * This file provides standardized logging functions to ensure consistent
 * error logging and debugging across the application.
 */

class Logger
{
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';

    private static $logFile = null;
    private static $enabledLevels = [
        self::LEVEL_ERROR,
        self::LEVEL_CRITICAL,
        self::LEVEL_WARNING
    ];

    /**
     * Initialize logger with custom settings
     * @param string $logFile
     * @param array $enabledLevels
     */
    public static function init($logFile = null, $enabledLevels = null)
    {
        if ($logFile) {
            self::$logFile = $logFile;
        }
        if ($enabledLevels) {
            self::$enabledLevels = $enabledLevels;
        }
    }

    /**
     * Log a message with specified level
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private static function log($level, $message, $context = [])
    {
        if (!in_array($level, self::$enabledLevels)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $requestId = self::getRequestId();
        $file = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['file'] ?? 'unknown';
        $line = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['line'] ?? 'unknown';
        $function = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'] ?? 'unknown';

        $logMessage = sprintf(
            "[%s] [%s] [%s] %s:%s %s() - %s",
            $timestamp,
            $level,
            $requestId,
            basename($file),
            $line,
            $function,
            $message
        );

        if (!empty($context)) {
            $logMessage .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES);
        }

        // Log to file if specified, otherwise use error_log
        if (self::$logFile) {
            file_put_contents(self::$logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
        } else {
            error_log($logMessage);
        }
    }

    /**
     * Log debug message
     * @param string $message
     * @param array $context
     */
    public static function debug($message, $context = [])
    {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log info message
     * @param string $message
     * @param array $context
     */
    public static function info($message, $context = [])
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning message
     * @param string $message
     * @param array $context
     */
    public static function warning($message, $context = [])
    {
        self::log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error message
     * @param string $message
     * @param array $context
     */
    public static function error($message, $context = [])
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log critical message
     * @param string $message
     * @param array $context
     */
    public static function critical($message, $context = [])
    {
        self::log(self::LEVEL_CRITICAL, $message, $context);
    }

    /**
     * Log exception with full stack trace
     * @param Exception $exception
     * @param string $message
     * @param array $context
     */
    public static function exception($exception, $message = null, $context = [])
    {
        $errorMessage = $message ?: 'Exception occurred';
        $errorMessage .= ': ' . $exception->getMessage();

        $context['exception'] = [
            'type' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        self::error($errorMessage, $context);
    }

    /**
     * Log database error with query context
     * @param PDOException $exception
     * @param string $query
     * @param array $params
     */
    public static function databaseError($exception, $query = null, $params = [])
    {
        $context = [
            'error_code' => $exception->getCode(),
            'sql_state' => $exception->errorInfo[0] ?? 'unknown'
        ];

        if ($query) {
            $context['query'] = $query;
        }

        if (!empty($params)) {
            $context['parameters'] = $params;
        }

        self::exception($exception, 'Database error', $context);
    }

    /**
     * Log API request/response for debugging
     * @param string $endpoint
     * @param array $request
     * @param array $response
     * @param float $duration
     */
    public static function apiCall($endpoint, $request = [], $response = [], $duration = null)
    {
        $context = [
            'endpoint' => $endpoint,
            'request' => $request,
            'response' => $response
        ];

        if ($duration !== null) {
            $context['duration_ms'] = round($duration * 1000, 2);
        }

        self::debug('API call', $context);
    }

    /**
     * Log user action for audit trail
     * @param string $action
     * @param array $data
     * @param string $userId
     */
    public static function userAction($action, $data = [], $userId = null)
    {
        $context = [
            'action' => $action,
            'user_id' => $userId ?: (getCurrentUser()['id'] ?? 'anonymous'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data' => $data
        ];

        self::info('User action: ' . $action, $context);
    }

    /**
     * Log performance metrics
     * @param string $operation
     * @param float $duration
     * @param array $metrics
     */
    public static function performance($operation, $duration, $metrics = [])
    {
        $context = array_merge([
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ], $metrics);

        self::info('Performance: ' . $operation, $context);
    }

    /**
     * Get unique request ID for tracking
     * @return string
     */
    private static function getRequestId()
    {
        static $requestId = null;
        if ($requestId === null) {
            $requestId = substr(uniqid(), -8);
        }
        return $requestId;
    }

    /**
     * Enable debug mode (includes DEBUG and INFO levels)
     */
    public static function enableDebug()
    {
        self::$enabledLevels = [
            self::LEVEL_DEBUG,
            self::LEVEL_INFO,
            self::LEVEL_WARNING,
            self::LEVEL_ERROR,
            self::LEVEL_CRITICAL
        ];
    }

    /**
     * Disable debug mode (only WARNING, ERROR, CRITICAL)
     */
    public static function disableDebug()
    {
        self::$enabledLevels = [
            self::LEVEL_WARNING,
            self::LEVEL_ERROR,
            self::LEVEL_CRITICAL
        ];
    }
}

/**
 * Convenience functions for global use
 */
function logError($message, $context = [])
{
    Logger::error($message, $context);
}

function logWarning($message, $context = [])
{
    Logger::warning($message, $context);
}

function logInfo($message, $context = [])
{
    Logger::info($message, $context);
}

function logDebug($message, $context = [])
{
    Logger::debug($message, $context);
}

function logException($exception, $message = null, $context = [])
{
    Logger::exception($exception, $message, $context);
}

function logDatabaseError($exception, $query = null, $params = [])
{
    Logger::databaseError($exception, $query, $params);
}
