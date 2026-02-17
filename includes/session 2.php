<?php

/**
 * Centralized Session Management
 * Handles session initialization, validation, and security
 */

class SessionManager
{
    private static $initialized = false;
    private static $config = [
        // Use default PHP session name so API uses the same session cookie
        'name' => 'PHPSESSID',
        'lifetime' => 86400, // 24 hours
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true for HTTPS
        'httponly' => true,
        // Default to Lax; will be dynamically elevated to None on HTTPS below
        'samesite' => 'Lax'
    ];

    /**
     * Initialize session with security settings
     */
    public static function init($config = [])
    {
        if (self::$initialized) {
            return true;
        }

        // Merge custom config
        self::$config = array_merge(self::$config, $config);

        // Start session (configure ONLY if not already active)
        if (session_status() === PHP_SESSION_NONE) {
            // Store sessions under /tmp only (best practice), falling back to bare /tmp if needed
            try {
                $chosen = null;
                $pathsToTry = [];

                $preferredRoot = '/tmp';
                if ($preferredRoot !== '') {
                    $preferredRoot = rtrim($preferredRoot, '/');
                    if ($preferredRoot === '') {
                        $preferredRoot = '/';
                    }
                    if (is_dir($preferredRoot) || @mkdir($preferredRoot, 0777, true)) {
                        $pathsToTry[] = $preferredRoot . '/whimsicalfrog_sessions';
                        $pathsToTry[] = $preferredRoot;
                    }
                }

                foreach ($pathsToTry as $candidate) {
                    if (!is_string($candidate) || $candidate === '') {
                        continue;
                    }
                    $created = false;
                    if (!is_dir($candidate)) {
                        $created = @mkdir($candidate, 0777, true);
                    }
                    if ($created) {
                        @chmod($candidate, 0777);
                    }
                    if (is_dir($candidate) && is_writable($candidate)) {
                        $chosen = $candidate;
                        break;
                    }
                }
                if ($chosen) {
                    ini_set('session.save_path', $chosen);
                }
            } catch (\Throwable $e) {
                error_log('[session] save_path selection failed: ' . $e->getMessage());
            }

            // Robust HTTPS detection (behind proxies)
            $isHttps = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
                (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
                (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
            );

            // For localhost and IP addresses, avoid setting a Domain attribute at all
            try {
                $hostCheck = $_SERVER['HTTP_HOST'] ?? '';
                if (strpos($hostCheck, ':') !== false) {
                    $hostCheck = explode(':', $hostCheck)[0];
                }
                $isIpHost = (bool) preg_match('/^\d{1,3}(?:\.\d{1,3}){3}$/', $hostCheck);
                $isLocalHost = ($hostCheck === 'localhost' || $hostCheck === '127.0.0.1' || $isIpHost);
                if ($isLocalHost) {
                    self::$config['domain'] = '';
                }
            } catch (\Throwable $e) { /* noop */
            }
            
            // Set session configuration BEFORE starting session
            ini_set('session.name', self::$config['name']);
            // If lifetime is 0, we still want a sane GC window so data persists across requests.
            // Use 24h for GC when lifetime<=0, but keep cookie_lifetime=0 (session cookie) for the browser.
            $cfgLifetime = (int) self::$config['lifetime'];
            $gcLifetime = ($cfgLifetime > 0) ? $cfgLifetime : 86400; // 24h default GC if not specified
            $cookieLifetime = $cfgLifetime; // 0 means session cookie in browser
            ini_set('session.gc_maxlifetime', $gcLifetime);
            ini_set('session.cookie_lifetime', $cookieLifetime);
            ini_set('session.cookie_path', self::$config['path']);
            ini_set('session.cookie_domain', self::$config['domain']);
            ini_set('session.cookie_secure', $isHttps ? 1 : 0);
            ini_set('session.cookie_httponly', self::$config['httponly']);
            // Use SameSite=None only when secure over HTTPS; otherwise Lax to ensure cookies are accepted on localhost/dev
            $sameSite = $isHttps ? 'None' : 'Lax';
            ini_set('session.cookie_samesite', $sameSite);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_cookies', 1);
            ini_set('session.use_only_cookies', 1);
            
            session_start();
            // Normalize cookie to base domain to avoid duplicate host-only vs domain cookies
            try {
                $host = $_SERVER['HTTP_HOST'] ?? '';
                if (strpos($host, ':') !== false) {
                    $host = explode(':', $host)[0];
                }
                $parts = $host !== '' ? explode('.', $host) : [];
                $baseDomain = $host;
                if (count($parts) >= 2) {
                    $baseDomain = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
                }
                // Determine if host is local (localhost or IPv4)
                $isIp = (bool) preg_match('/^\d{1,3}(?:\.\d{1,3}){3}$/', $host);
                $isLocalhost = ($host === 'localhost' || $host === '127.0.0.1' || $isIp);
                $cookieDomain = $isLocalhost ? '' : ('.' . $baseDomain);
                $isHttps = (
                    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                    (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
                    (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
                    (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
                );
                $sameSite = $isHttps ? 'None' : 'Lax';
                // 1) Clear any host-only cookie by setting expired cookie without Domain
                $optsClear = [
                    'expires' => time() - 3600,
                    'path' => self::$config['path'],
                    'secure' => $isHttps,
                    'httponly' => self::$config['httponly'],
                    'samesite' => $sameSite,
                ];
                @setcookie(session_name(), '', $optsClear);
                // 2) Set the canonical cookie (domain-scoped only when not local)
                $optsSet = [
                    'expires' => 0,
                    'path' => self::$config['path'],
                    'secure' => $isHttps,
                    'httponly' => self::$config['httponly'],
                    'samesite' => $sameSite,
                ];
                if (!empty($cookieDomain)) {
                    $optsSet['domain'] = $cookieDomain;
                }
                @setcookie(session_name(), session_id(), $optsSet);
            } catch (\Throwable $e) {
                error_log('[session] cookie normalization failed: ' . $e->getMessage());
            }
        }

        // Initialize session security
        self::initializeSecurity();

        self::$initialized = true;
        return true;
    }

    /**
     * Initialize session security measures
     */
    private static function initializeSecurity()
    {
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
    private static function generateFingerprint()
    {
        // Use only User-Agent for fingerprint to prevent false invalidations across request types
        // (e.g., differences in Accept-Language/Encoding between fetch and navigation)
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', $userAgent);
    }

    /**
     * Check if session is valid and not expired
     */
    public static function isValid()
    {
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
    public static function set($key, $value)
    {
        self::init();
        $_SESSION[$key] = $value;
    }

    /**
     * Get session variable
     */
    public static function get($key, $default = null)
    {
        self::init();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session variable exists
     */
    public static function has($key)
    {
        self::init();
        return isset($_SESSION[$key]);
    }
    // remove function moved to file_operations.php for centralization

    /**
     * Get all session data
     */
    public static function all()
    {
        self::init();
        return $_SESSION;
    }

    /**
     * Clear all session data except system variables
     */
    public static function clear()
    {
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
    public static function destroy()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        // Clear session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(
                session_name(),
                '',
                time() - 3600,
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
    public static function regenerate($deleteOld = true)
    {
        self::init();
        session_regenerate_id($deleteOld);
        $_SESSION['_session_created'] = time();
        $_SESSION['_session_fingerprint'] = self::generateFingerprint();
    }

    /**
     * Flash message functionality
     */
    public static function flash($key, $value = null)
    {
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
    public static function getId()
    {
        self::init();
        return session_id();
    }

    /**
     * Get session status information
     */
    public static function getStatus()
    {
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
function session_init($config = [])
{
    return SessionManager::init($config);
}

function session_set($key, $value)
{
    return SessionManager::set($key, $value);
}

function session_get($key, $default = null)
{
    return SessionManager::get($key, $default);
}

function session_has($key)
{
    return SessionManager::has($key);
}

function session_remove($key)
{
    return SessionManager::remove($key);
}

function session_flash($key, $value = null)
{
    return SessionManager::flash($key, $value);
}

function session_destroy_custom()
{
    return SessionManager::destroy();
}

function session_regenerate($deleteOld = true)
{
    return SessionManager::regenerate($deleteOld);
}
