<?php
/**
 * Centralized Session Management
 * Handles session initialization, validation, and security
 */

class SessionManager {
    private static $initialized = false;
    private static $config = [
        'name' => 'WHIMSICAL_SESSION',
        'lifetime' => 86400, // 24 hours
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true for HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ];

    /**
     * Initialize session with security settings
     */
    public static function init($config = []) {
        if (self::$initialized) {
            return true;
        }

        // Merge custom config
        self::$config = array_merge(self::$config, $config);

        // Set session configuration
        ini_set('session.name', self::$config['name']);
        ini_set('session.gc_maxlifetime', self::$config['lifetime']);
        ini_set('session.cookie_lifetime', self::$config['lifetime']);
        ini_set('session.cookie_path', self::$config['path']);
        ini_set('session.cookie_domain', self::$config['domain']);
        ini_set('session.cookie_secure', self::$config['secure']);
        ini_set('session.cookie_httponly', self::$config['httponly']);
        ini_set('session.cookie_samesite', self::$config['samesite']);
        ini_set('session.use_strict_mode', 1);

        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Initialize session security
        self::initializeSecurity();
        
        self::$initialized = true;
        return true;
    }

    /**
     * Initialize session security measures
     */
    private static function initializeSecurity() {
        // Regenerate session ID periodically
        if (!isset($_SESSION['_session_created'])) {
            $_SESSION['_session_created'] = time();
        } elseif (time() - $_SESSION['_session_created'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['_session_created'] = time();
        }

        // Set session fingerprint
        if (!isset($_SESSION['_session_fingerprint'])) {
            $_SESSION['_session_fingerprint'] = self::generateFingerprint();
        } elseif ($_SESSION['_session_fingerprint'] !== self::generateFingerprint()) {
            // Potential session hijacking
            self::destroy();
            throw new Exception('Session security violation detected');
        }

        // Set last activity
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Generate session fingerprint for security
     */
    private static function generateFingerprint() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        return hash('sha256', $userAgent . $acceptLanguage . $acceptEncoding);
    }

    /**
     * Check if session is valid and not expired
     */
    public static function isValid() {
        if (!self::$initialized) {
            return false;
        }

        // Check if session exists
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        // Check last activity
        if (isset($_SESSION['_last_activity'])) {
            if (time() - $_SESSION['_last_activity'] > self::$config['lifetime']) {
                self::destroy();
                return false;
            }
        }

        return true;
    }

    /**
     * Set session variable
     */
    public static function set($key, $value) {
        self::init();
        $_SESSION[$key] = $value;
    }

    /**
     * Get session variable
     */
    public static function get($key, $default = null) {
        self::init();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session variable exists
     */
    public static function has($key) {
        self::init();
        return isset($_SESSION[$key]);
    }
// remove function moved to file_operations.php for centralization

    /**
     * Get all session data
     */
    public static function all() {
        self::init();
        return $_SESSION;
    }

    /**
     * Clear all session data except system variables
     */
    public static function clear() {
        self::init();
        $systemKeys = ['_session_created', '_session_fingerprint', '_last_activity'];
        foreach ($_SESSION as $key => $value) {
            if (!in_array($key, $systemKeys)) {
                unset($_SESSION[$key]);
            }
        }
    }

    /**
     * Destroy session completely
     */
    public static function destroy() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        
        // Clear session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, 
                self::$config['path'], 
                self::$config['domain'], 
                self::$config['secure'], 
                self::$config['httponly']
            );
        }
        
        self::$initialized = false;
    }

    /**
     * Regenerate session ID
     */
    public static function regenerate($deleteOld = true) {
        self::init();
        session_regenerate_id($deleteOld);
        $_SESSION['_session_created'] = time();
        $_SESSION['_session_fingerprint'] = self::generateFingerprint();
    }

    /**
     * Flash message functionality
     */
    public static function flash($key, $value = null) {
        self::init();
        
        if ($value === null) {
            // Get flash message
            $message = $_SESSION['_flash'][$key] ?? null;
            unset($_SESSION['_flash'][$key]);
            return $message;
        } else {
            // Set flash message
            $_SESSION['_flash'][$key] = $value;
        }
    }

    /**
     * Get session ID
     */
    public static function getId() {
        self::init();
        return session_id();
    }

    /**
     * Get session status information
     */
    public static function getStatus() {
        return [
            'active' => session_status() === PHP_SESSION_ACTIVE,
            'id' => self::getId(),
            'name' => session_name(),
            'created' => self::get('_session_created'),
            'last_activity' => self::get('_last_activity'),
            'expires_in' => self::$config['lifetime'] - (time() - (self::get('_last_activity') ?? time()))
        ];
    }
}

// Convenience functions
function session_init($config = []) {
    return SessionManager::init($config);
}

function session_set($key, $value) {
    return SessionManager::set($key, $value);
}

function session_get($key, $default = null) {
    return SessionManager::get($key, $default);
}

function session_has($key) {
    return SessionManager::has($key);
}

function session_remove($key) {
    return SessionManager::remove($key);
}

function session_flash($key, $value = null) {
    return SessionManager::flash($key, $value);
}

function session_destroy_custom() {
    return SessionManager::destroy();
}

function session_regenerate($deleteOld = true) {
    return SessionManager::regenerate($deleteOld);
} 