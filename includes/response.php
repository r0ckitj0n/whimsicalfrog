<?php
/**
 * Centralized Response Helper
 * 
 * This file provides standardized response functions for API endpoints
 * to ensure consistent JSON responses and error handling.
 */


require_once __DIR__ . '/security_validator.php';
class Response {
    
    /**
     * Send a JSON response and exit
     * @param mixed $data
     * @param int $httpCode
     */
    public static function json($data, $httpCode = 200) {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    /**
     * Send a success JSON response
     * @param mixed $data
     * @param string $message
     */
    public static function success($data = null, $message = null) {
        $response = ['success' => true];
        if ($data !== null) {
            $response['data'] = $data;
        }
        if ($message !== null) {
            $response['message'] = $message;
        }
        self::json($response);
    }
    
    /**
     * Send an error JSON response
     * @param string $message
     * @param mixed $details
     * @param int $httpCode
     */
    public static function error($message, $details = null, $httpCode = 400) {
        $response = [
            'success' => false,
            'error' => $message
        ];
        if ($details !== null) {
            $response['details'] = $details;
        }
        self::json($response, $httpCode);
    }
    
    /**
     * Send a validation error response
     * @param array|string $errors
     */
    public static function validationError($errors) {
        self::error('Validation failed', $errors, 422);
    }
    
    /**
     * Send an unauthorized response
     * @param string $message
     */
    public static function unauthorized($message = 'Unauthorized access') {
        self::error($message, null, 401);
    }
    
    /**
     * Send a forbidden response
     * @param string $message
     */
    public static function forbidden($message = 'Access forbidden') {
        self::error($message, null, 403);
    }
    
    /**
     * Send a not found response
     * @param string $message
     */
    public static function notFound($message = 'Resource not found') {
        self::error($message, null, 404);
    }
    
    /**
     * Send a method not allowed response
     * @param string $message
     */
    public static function methodNotAllowed($message = 'Method not allowed') {
        self::error($message, null, 405);
    }
    
    /**
     * Send an internal server error response
     * @param string $message
     * @param mixed $details
     */
    public static function serverError($message = 'Internal server error', $details = null) {
        self::error($message, $details, 500);
    }
// validateRequired function moved to form-validator.js for centralization
    
    /**
     * Validate HTTP method
     * @param string|array $allowedMethods
     */
    public static function validateMethod($allowedMethods) {
        $method = $_SERVER['REQUEST_METHOD'];
        $allowed = is_array($allowedMethods) ? $allowedMethods : [$allowedMethods];
        
        if (!in_array($method, $allowed)) {
            self::methodNotAllowed();
        }
    }
    
    /**
     * Get JSON input from request body
     * @return array|null
     */
    public static function getJsonInput() {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            return null;
        }
        
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            self::error('Invalid JSON input');
        }
        
        return $data;
    }

    /**
     * Get POST data (form or JSON)
     */
    public static function getPostData($required = false) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $data = self::getJsonInput();
            if ($data === null && $required) {
                self::error('No JSON data provided', null, 400);
            }
            return $data;
        } else {
            $data = $_POST;
            if (empty($data) && $required) {
                self::error('No POST data provided', null, 400);
            }
            return $data ?: null;
        }
    }

    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            return trim(htmlspecialchars($data, ENT_QUOTES, 'UTF-8'));
        }
        
        return $data;
    }

    /**
     * Get client IP address
     */
    public static function getClientIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check if request is AJAX
     */
    public static function isAjax() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Set CORS headers
     */
    public static function setCorsHeaders($allowedOrigins = ['*'], $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'], $allowedHeaders = ['Content-Type', 'Authorization']) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }
        
        header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24 hours
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Get current request method
     */
    public static function getMethod() {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
}
?> 