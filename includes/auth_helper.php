<?php
/**
 * Centralized Authentication Helper for WhimsicalFrog
 * Provides consistent authentication patterns across all APIs
 */

class AuthHelper {
    
    /**
     * Standard admin token for development/API access
     */
    const ADMIN_TOKEN = 'whimsical_admin_2024';
    
    /**
     * Check if current request has admin privileges
     * Handles both session-based and token-based authentication
     * Supports JSON input, GET, and POST parameters
     * 
     * @return bool True if user has admin privileges
     */
    public static function isAdmin(): bool {
        // Parse JSON input for token-based requests
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        
        // Check for admin token in multiple sources
        $adminToken = $_GET['admin_token'] ?? $_POST['admin_token'] ?? $input['admin_token'] ?? null;
        
        if ($adminToken === self::ADMIN_TOKEN) {
            return true;
        }
        
        // Check session-based authentication using centralized auth functions
        require_once __DIR__ . '/auth.php';
        
        if (isAdmin()) {
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
    public static function requireAdmin(int $httpCode = 403, string $message = 'Admin access required'): void {
        if (!self::isAdmin()) {
            if (class_exists('Response')) {
                Response::error($message, $httpCode);
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
    public static function getCurrentUser(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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
    public static function getAdminToken(): ?string {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        return $_GET['admin_token'] ?? $_POST['admin_token'] ?? $input['admin_token'] ?? null;
    }
    
    /**
     * Check if current user has specific role
     * 
     * @param string $role Role to check for
     * @return bool True if user has the specified role
     */
    public static function hasRole(string $role): bool {
        $userData = self::getCurrentUser();
        return $userData && strtolower($userData['role'] ?? '') === strtolower($role);
    }
}

/**
 * Compatibility function for existing code that calls getCurrentUser()
 * @deprecated Use AuthHelper::getCurrentUser() instead
 */
function getCurrentUserFromHelper(): ?array {
    return AuthHelper::getCurrentUser();
}

/**
 * Compatibility function for checking if user is admin  
 * @deprecated Use AuthHelper::isAdmin() instead
 */
function isAdminWithTokenHelper(): bool {
    return AuthHelper::isAdmin();
} 