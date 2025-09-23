<?php

/**
 * Error Logger - Comprehensive error tracking for WhimsicalFrog
 *
 * This logger captures PHP errors, exceptions, and custom errors
 * and writes them to the error_logs database table for display
 * in the Website Logs modal.
 */

class ErrorLogger
{
    private static $pdo = null;
    private static $isInitialized = false;

    /**
     * Initialize the error logger
     */
    public static function init()
    {
        if (self::$isInitialized) {
            return;
        }

        try {
            self::$pdo = Database::getInstance();

            // Set up PHP error handlers
            set_error_handler([self::class, 'handleError']);
            set_exception_handler([self::class, 'handleException']);
            register_shutdown_function([self::class, 'handleFatalError']);

            self::$isInitialized = true;
        } catch (Exception $e) {
            error_log("ErrorLogger initialization failed: " . $e->getMessage());
        }
    }

    /**
     * Handle PHP errors
     */
    public static function handleError($severity, $message, $file, $line)
    {
        // Don't log suppressed errors (@ operator)
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $errorType = self::getErrorTypeName($severity);
        $context = [
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
            'error_type' => $errorType
        ];

        self::logError($errorType, $message, $context);

        // Don't prevent PHP's normal error handling
        return false;
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handleException($exception)
    {
        $context = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode()
        ];

        self::logError(
            get_class($exception),
            $exception->getMessage(),
            $context
        );
    }

    /**
     * Handle fatal errors
     */
    public static function handleFatalError()
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $context = [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ];

            self::logError(
                'FATAL_ERROR',
                $error['message'],
                $context
            );
        }
    }

    /**
     * Log an error to the database
     */
    public static function logError($errorType, $message, $context = [], $userId = null)
    {
        if (!self::$pdo) {
            try {
                self::$pdo = Database::getInstance();
            } catch (Exception $e) {
                error_log("Cannot log error - database unavailable: " . $e->getMessage());
                return false;
            }
        }

        try {
            // Get current user ID if not provided
            if (!$userId && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user'])) {
                $userId = $_SESSION['user']['userId'] ?? null;
            }

            // Get request information
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $httpMethod = $_SERVER['REQUEST_METHOD'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

            // Prepare context data
            $contextJson = json_encode(array_merge($context, [
                'request_uri' => $requestUri,
                'http_method' => $httpMethod,
                'user_agent' => substr($userAgent, 0, 255), // Limit length
                'ip_address' => $ipAddress,
                'timestamp' => date('Y-m-d H:i:s')
            ]));

            $stmt = self::$pdo->prepare("
                INSERT INTO error_logs (
                    error_type, 
                    message, 
                    context_data, 
                    user_id, 
                    file_path, 
                    line_number, 
                    ip_address, 
                    user_agent, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $result = $stmt->execute([
                substr($errorType, 0, 100),              // error_type
                substr($message, 0, 1000),               // message
                $contextJson,                            // context_data
                $userId,                                 // user_id
                isset($context['file']) ? substr($context['file'], 0, 255) : null, // file_path
                isset($context['line']) ? $context['line'] : null,                  // line_number
                $ipAddress,                              // ip_address
                substr($userAgent, 0, 255),              // user_agent
            ]);

            return $result;
        } catch (Exception $e) {
            error_log("Failed to log error to database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log custom application errors
     */
    public static function logCustomError($type, $message, $details = [], $userId = null)
    {
        $context = array_merge($details, [
            'custom_error' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        return self::logError($type, $message, $context, $userId);
    }

    /**
     * Log database errors
     */
    public static function logDatabaseError($query, $error, $params = [])
    {
        $context = [
            'query' => $query,
            'parameters' => $params,
            'database_error' => true
        ];

        return self::logError('DATABASE_ERROR', $error, $context);
    }

    /**
     * Log API errors
     */
    public static function logApiError($endpoint, $error, $requestData = [], $userId = null)
    {
        $context = [
            'endpoint' => $endpoint,
            'request_data' => $requestData,
            'api_error' => true
        ];

        return self::logError('API_ERROR', $error, $context, $userId);
    }

    /**
     * Get error type name from PHP error constant
     */
    private static function getErrorTypeName($type)
    {
        $errorTypes = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            // E_STRICT is deprecated in PHP 8+, use E_NOTICE instead
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];

        return $errorTypes[$type] ?? 'UNKNOWN_ERROR';
    }

    /**
     * Get recent errors for debugging
     */
    public static function getRecentErrors($limit = 10)
    {
        if (!self::$pdo) {
            return [];
        }

        try {
            $stmt = self::$pdo->prepare("
                SELECT * FROM error_logs 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$limit]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to fetch recent errors: " . $e->getMessage());
            return [];
        }
    }
}

// Convenience functions (renamed to avoid conflicts)
/*
function logCustomError($type, $message, $details = [], $userId = null)
{
    return ErrorLogger::logCustomError($type, $message, $details, $userId);
}

function logDbError($query, $error, $params = [])
{
    return ErrorLogger::logDatabaseError($query, $error, $params);
}

function logApiError($endpoint, $error, $requestData = [], $userId = null)
{
    return ErrorLogger::logApiError($endpoint, $error, $requestData, $userId);
}
*/
