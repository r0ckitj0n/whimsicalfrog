<?php
/**
 * Session Security and Utility Helpers for SessionManager
 */
class SessionHelper
{
    /**
     * Generate session fingerprint for security
     */
    public static function generateFingerprint()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return hash('sha256', $userAgent);
    }

    /**
     * Get preferred session save paths
     */
    public static function getSessionSavePaths()
    {
        $paths = [];
        $preferredRoot = '/tmp';
        
        if ($preferredRoot !== '') {
            $preferredRoot = rtrim($preferredRoot, '/');
            if ($preferredRoot === '') {
                $preferredRoot = '/';
            }
            $paths[] = $preferredRoot . '/whimsicalfrog_sessions';
            $paths[] = $preferredRoot;
        }
        
        return $paths;
    }

    /**
     * Detect if connection is HTTPS (including behind proxies)
     */
    public static function isHttps()
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
            (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
            (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
        );
    }

    /**
     * Detect if host is local (localhost or IPv4)
     */
    public static function isLocalHost()
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($host, ':') !== false) {
            $host = explode(':', $host)[0];
        }
        $isIp = (bool) preg_match('/^\d{1,3}(?:\.\d{1,3}){3}$/', $host);
        return ($host === 'localhost' || $host === '127.0.0.1' || $isIp);
    }

    /**
     * Get canonical cookie domain
     */
    public static function getCookieDomain()
    {
        if (self::isLocalHost()) {
            return '';
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($host, ':') !== false) {
            $host = explode(':', $host)[0];
        }
        
        $parts = $host !== '' ? explode('.', $host) : [];
        if (count($parts) >= 2) {
            return '.' . $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
        }
        
        return $host;
    }
}
