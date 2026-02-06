<?php

require_once __DIR__ . '/auth.php';

/**
 * Centralized Authentication Helper for WhimsicalFrog
 * Provides consistent authentication patterns across all APIs
 */

class AuthHelper
{
    /**
     * Standard admin token for development/API access
     */
    public const ADMIN_TOKEN = 'whimsical_admin_2024';

    /**
     * Check if current request has admin privileges
     * Handles both session-based and token-based authentication
     * Supports JSON input, GET, and POST parameters
     *
     * @return bool True if user has admin privileges
     */
    public static function isAdmin(): bool
    {
        // Initialize session for session-based authentication
        if (class_exists('SessionManager')) {
            SessionManager::init();
        }
        
        // Check for localhost/dev environment bypass
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, '192.168.') !== false);
        
        if (isAdmin() || $isLocal) {
            return true;
        }

        // Dev bypass: allow any logged-in user to act as admin in local environment
        $env = getenv('WHF_ENV') ?: $_ENV['WHF_ENV'] ?? $_SERVER['WHF_ENV'] ?? 'prod';
        if ($env === 'local' && isLoggedIn()) {
            return true;
        }

        return false;
    }

    /**
     * Require admin authentication or exit with error
     *
     * @param int $httpCode HTTP status code to return on failure (default: 403)
     * @param string $message Error message to return
     */
    public static function requireAdmin(int $httpCode = 403, string $message = 'Admin access required'): void
    {
        // Initialize session to access user data (guarded for environments without SessionManager)
        try {
            if (class_exists('SessionManager')) {
                SessionManager::init();
            } elseif (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
        } catch (\Throwable $____) {
            // Non-fatal: proceed to auth check
        }
        if (!self::isAdmin()) {
            if (class_exists('Response')) {
                // Pass httpCode as third argument to set correct HTTP status code
                Response::error($message, null, $httpCode);
            } else {
                // Fallback for APIs that don't use Response class
                http_response_code($httpCode);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $message]);
                exit();
            }
        }
    }

    /**
     * Get current authenticated user data
     *
     * @return array|null User data array or null if not authenticated
     */
    public static function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            require_once __DIR__ . '/session.php';
            $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
            if (strpos($host, ':') !== false) {
                $host = explode(':', $host)[0];
            }
            $parts = explode('.', $host);
            $baseDomain = $host;
            if (count($parts) >= 2) {
                $baseDomain = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
            }
            $cookieDomain = '.' . $baseDomain;
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
            session_init([
                'name' => 'PHPSESSID',
                'lifetime' => 0,
                'path' => '/',
                'domain' => $cookieDomain,
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'None',
            ]);
        }

        if (!isset($_SESSION['user'])) {
            return null;
        }

        $userData = $_SESSION['user'];

        // Handle both string and array formats (normalize to array)
        if (is_string($userData)) {
            $decoded = json_decode($userData, true);
            if (is_array($decoded)) {
                $_SESSION['user'] = $decoded; // Normalize session storage
                return $decoded;
            } else {
                // Invalid JSON, clear session
                unset($_SESSION['user']);
                return null;
            }
        } elseif (is_array($userData)) {
            return $userData;
        } else {
            // Unexpected format, clear session
            unset($_SESSION['user']);
            return null;
        }
    }

    /**
     * Get admin token from request (for APIs that need to pass it through)
     *
     * @return string|null Admin token if present
     */
    public static function getAdminToken(): ?string
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        return $_GET['admin_token'] ?? $_POST['admin_token'] ?? $input['admin_token'] ?? null;
    }

    /**
     * Check if current user has specific role
     *
     * @param string $role Role to check for
     * @return bool True if user has the specified role
     */
    public static function hasRole(string $role): bool
    {
        $userData = self::getCurrentUser();
        return $userData && strtolower($userData['role'] ?? '') === strtolower($role);
    }

    /**
     * Compatibility: check if a user is logged in via AuthHelper
     * Delegates to global isLoggedIn() from includes/auth.php
     */
    public static function isLoggedIn(): bool
    {
        return function_exists('isLoggedIn') ? isLoggedIn() : false;
    }

    /**
     * Compatibility: get current user via AuthHelper (alias)
     * Delegates to getCurrentUser() from this helper, which wraps session access
     */
    public static function currentUser(): ?array
    {
        return self::getCurrentUser();
    }
}

/**
 * Compatibility function for existing code that calls getCurrentUser()
 * @deprecated Use AuthHelper::getCurrentUser() instead
 */
function getCurrentUserFromHelper(): ?array
{
    return AuthHelper::getCurrentUser();
}

/**
 * Compatibility function for checking if user is admin
 * @deprecated Use AuthHelper::isAdmin() instead
 */
function isAdminWithTokenHelper(): bool
{
    return AuthHelper::isAdmin();
}
