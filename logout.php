<?php
<<<<<<< HEAD
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout action before destroying session
$username = getUsername();
$userId = getUserId();

// Logout the user (clears session data)
logoutUser();

// Log the logout action for audit trail
if (class_exists('DatabaseLogger') && $username) {
    try {
        DatabaseLogger::logUserActivity(
            $userId ?: 'unknown',
            'logout',
            "User '$username' logged out successfully",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
    } catch (Exception $e) {
        error_log("Logout logging error: " . $e->getMessage());
    }
}

// Clear any persistent cookies
$cookieParams = session_get_cookie_params();
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', time() - 42000,
        $cookieParams['path'], 
        $cookieParams['domain'],
        $cookieParams['secure'], 
        $cookieParams['httponly']
    );
}

// Destroy the session completely
session_destroy();

// Redirect to landing page with logout success parameter
header('Location: /?page=landing&logout=success');
=======
session_start();
require_once __DIR__ . '/includes/auth.php';

// Logout the user
logoutUser();

// Redirect to landing page
header('Location: /?page=landing');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
exit;
?> 