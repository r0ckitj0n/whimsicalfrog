<?php
/**
 * Centralized Authentication System for WhimsicalFrog
 * 
 * This file provides consistent authentication and authorization
 * functions across the entire application.
 */

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
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
 * Check if current user is admin
 * @return bool
 */
function isAdmin() {
    $user = getCurrentUser();
    if (!$user) {
        return false;
    }
    
    $role = strtolower($user['role'] ?? '');
    return $role === 'admin';
}

/**
 * Get user role
 * @return string
 */
function getUserRole() {
    $user = getCurrentUser();
    return $user['role'] ?? 'guest';
}

/**
 * Get user ID
 * @return string|null
 */
function getUserId() {
    $user = getCurrentUser();
    return $user['userId'] ?? null;
}

/**
 * Get username
 * @return string|null
 */
function getUsername() {
    $user = getCurrentUser();
    return $user['username'] ?? null;
}

/**
 * Get user's full name
 * @return string
 */
function getUserFullName() {
    $user = getCurrentUser();
    if (!$user) {
        return 'Guest';
    }
    
    $firstName = $user['firstName'] ?? '';
    $lastName = $user['lastName'] ?? '';
    $fullName = trim($firstName . ' ' . $lastName);
    
    return !empty($fullName) ? $fullName : ($user['username'] ?? 'User');
}

/**
 * Require authentication (redirect if not logged in)
 * @param string $redirectTo Where to redirect after login
 */
function requireAuth($redirectTo = null) {
    if (!isLoggedIn()) {
        if ($redirectTo) {
            $_SESSION['redirect_after_login'] = $redirectTo;
        }
        header('Location: /?page=login');
        exit;
    }
}

/**
 * Require admin privileges
 * @param bool $apiResponse Whether to return JSON response instead of redirect
 */
function requireAdmin($apiResponse = false) {
    if (!isLoggedIn()) {
        if ($apiResponse) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        } else {
            header('Location: /?page=login');
            exit;
        }
    }
    
    if (!isAdmin()) {
        if ($apiResponse) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Admin privileges required']);
            exit;
        } else {
            header('Location: /?page=main_room');
            exit;
        }
    }
}

/**
 * Check admin with fallback token (for API endpoints)
 * @param string $validToken Optional admin token for fallback auth
 * @return bool
 */
function isAdminWithToken($validToken = 'whimsical_admin_2024') {
    // First check session-based admin
    if (isAdmin()) {
        return true;
    }
    
    // Check for admin token fallback in POST/GET
    $providedToken = $_POST['admin_token'] ?? $_GET['admin_token'] ?? '';
    
    // Also check JSON body
    if (empty($providedToken)) {
        $jsonInput = json_decode(file_get_contents('php://input'), true);
        $providedToken = $jsonInput['admin_token'] ?? '';
    }
    
    return $providedToken === $validToken;
}

/**
 * API Authentication check with JSON response
 * @param bool $requireAdmin Whether admin privileges are required
 * @param string $adminToken Optional admin token for fallback
 */
function checkApiAuth($requireAdmin = false, $adminToken = 'whimsical_admin_2024') {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        // Check for admin token fallback if admin is required
        if ($requireAdmin && $adminToken) {
            // Check POST, GET, and JSON body for admin token
            $providedToken = $_POST['admin_token'] ?? $_GET['admin_token'] ?? '';
            
            // Also check JSON body
            if (empty($providedToken)) {
                $jsonInput = json_decode(file_get_contents('php://input'), true);
                $providedToken = $jsonInput['admin_token'] ?? '';
            }
            
            if ($providedToken !== $adminToken) {
                http_response_code(401);
                echo json_encode(['error' => 'Authentication required']);
                exit;
            }
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
    }
    
    if ($requireAdmin && !isAdminWithToken($adminToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin privileges required']);
        exit;
    }
}

/**
 * Login user
 * @param array $userData User data from database
 */
function loginUser($userData) {
    $_SESSION['user'] = [
        'userId' => $userData['id'] ?? $userData['userId'],
        'username' => $userData['username'],
        'email' => $userData['email'],
        'role' => $userData['role'],
        'firstName' => $userData['firstName'] ?? null,
        'lastName' => $userData['lastName'] ?? null
    ];
}

/**
 * Logout user
 */
function logoutUser() {
    unset($_SESSION['user']);
    
    // Clear any other auth-related session data
    unset($_SESSION['redirect_after_login']);
    unset($_SESSION['isAdmin']);
    unset($_SESSION['role']);
    unset($_SESSION['user_role']);
    
    // Optionally destroy entire session
    // session_destroy();
}

/**
 * Get welcome message for user
 * @return string
 */
function getWelcomeMessage() {
    $user = getCurrentUser();
    if (!$user) {
        return '';
    }
    
    $firstName = $user['firstName'] ?? '';
    $lastName = $user['lastName'] ?? '';
    
    if (!empty($firstName) || !empty($lastName)) {
        return "Welcome, " . trim($firstName . ' ' . $lastName);
    }
    
    return "Welcome, " . ($user['username'] ?? 'User');
}

/**
 * Debug authentication state (for troubleshooting)
 * @return array
 */
function getAuthDebugInfo() {
    return [
        'session_id' => session_id(),
        'session_status' => session_status(),
        'is_logged_in' => isLoggedIn(),
        'is_admin' => isAdmin(),
        'user_role' => getUserRole(),
        'user_id' => getUserId(),
        'username' => getUsername(),
        'full_name' => getUserFullName(),
        'session_keys' => array_keys($_SESSION),
        'user_data' => getCurrentUser()
    ];
}

/**
 * Set global variables for backward compatibility
 */
function setGlobalAuthVars() {
    $GLOBALS['isLoggedIn'] = isLoggedIn();
    $GLOBALS['isAdmin'] = isAdmin();
    $GLOBALS['userData'] = getCurrentUser() ?? [];
    $GLOBALS['welcomeMessage'] = getWelcomeMessage();
}

// Auto-set global variables when this file is included
setGlobalAuthVars(); 