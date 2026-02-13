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
        // Fast-path: if a session is already active, do not re-init (avoids session lock churn).
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user'])) {
            $userData = $_SESSION['user'];
            if (is_string($userData)) {
                $decoded = json_decode($userData, true);
                if (is_array($decoded)) $userData = $decoded;
            }
            if (is_array($userData)) {
                $role = strtolower(trim((string) ($userData['role'] ?? '')));
                return in_array($role, [
                    'admin',
                    'superadmin',
                    'devops',
                    'administrator',
                ], true);
            }
        }

        // Session-based auth (may block if another request is holding the lock).
        try {
            if (class_exists('SessionManager')) {
                SessionManager::init();
            }
        } catch (\Throwable $_ignored) {
            // If session cannot be established, treat as non-admin.
            return false;
        }

        return isAdmin();
    }

    /**
     * Require admin authentication or exit with error
     *
     * @param int $httpCode HTTP status code to return on failure (default: 403)
     * @param string $message Error message to return
     */
    public static function requireAdmin(int $httpCode = 403, string $message = 'Admin access required'): void
    {
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

        // IMPORTANT: Release the PHP session lock ASAP after auth checks.
        // Many API endpoints do not need to write to the session, but long-running requests
        // (AI calls, large queries) can otherwise block concurrent requests and trigger 30s timeouts.
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
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
