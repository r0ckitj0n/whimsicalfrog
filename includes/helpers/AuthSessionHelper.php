<?php
/**
 * includes/helpers/AuthSessionHelper.php
 * Helper for authentication session and cookie management
 */

class AuthSessionHelper
{
    // Debug log file for tracking logout issues
    private static function debugLog(string $message): void
    {
        $logFile = __DIR__ . '/../../logs/auth_debug.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] {$message}\n";
        @file_put_contents($logFile, $line, FILE_APPEND);
    }

    public static function getCookieDomain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
        if (strpos($host, ':') !== false) {
            $host = explode(':', $host)[0];
        }

        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            self::debugLog("getCookieDomain: Returning empty for localhost/IP: {$host}");
            return '';
        }

        $parts = explode('.', $host);
        if (count($parts) >= 2) {
            $domain = '.' . $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
            self::debugLog("getCookieDomain: Calculated domain: {$domain} from host: {$host}");
            return $domain;
        }
        self::debugLog("getCookieDomain: Returning dotted host: .{$host}");
        return '.' . $host;
    }

    public static function isHttps(): bool
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
            (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
            (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
        );
    }

    public static function reconstructSessionFromCookie(): void
    {
        self::debugLog("reconstructSessionFromCookie: Called. Cookies present: " . json_encode(array_keys($_COOKIE)));

        // Do not reconstruct if we're in the middle of a logout
        if (isset($_COOKIE['WF_LOGOUT_IN_PROGRESS'])) {
            self::debugLog("reconstructSessionFromCookie: WF_LOGOUT_IN_PROGRESS detected - aborting reconstruction");

            // Immediately clear the logout marker cookie for all domain variations
            $dom = self::getCookieDomain();
            $domains = ['', $dom];
            if ($dom && !str_starts_with($dom, '.')) {
                $domains[] = '.' . $dom;
            }
            foreach ($domains as $domain) {
                $opts = ['expires' => time() - 3600, 'path' => '/'];
                if (!empty($domain)) {
                    $opts['domain'] = $domain;
                }
                @setcookie('WF_LOGOUT_IN_PROGRESS', '', $opts);
                self::debugLog("reconstructSessionFromCookie: Cleared WF_LOGOUT_IN_PROGRESS for domain: " . ($domain ?: 'empty'));
            }
            unset($_COOKIE['WF_LOGOUT_IN_PROGRESS']);

            // Also clear any auth cookies from $_COOKIE to prevent reconstruction
            unset($_COOKIE[wf_auth_cookie_name()]);
            unset($_COOKIE[wf_auth_client_cookie_name()]);
            self::debugLog("reconstructSessionFromCookie: Cleared auth cookies from \$_COOKIE");
            return;
        }

        try {
            if (empty($_SESSION['user'])) {
                self::debugLog("reconstructSessionFromCookie: Session user is empty, checking cookies");
                $cookieVal = $_COOKIE[wf_auth_cookie_name()] ?? null;
                if ($cookieVal) {
                    self::debugLog("reconstructSessionFromCookie: Found WF_AUTH cookie, parsing...");
                }
                $parsed = wf_auth_parse_cookie($cookieVal ?? '');
                if (is_array($parsed) && !empty($parsed['user_id'])) {
                    $uid = $parsed['user_id'];
                    self::debugLog("reconstructSessionFromCookie: Parsed cookie for user_id: {$uid}");
                    $row = null;
                    try {
                        $dbOk = true;
                        if (class_exists('Database') && method_exists('Database', 'isAvailableQuick')) {
                            $dbOk = \Database::isAvailableQuick(0.6);
                        }
                        if ($dbOk) {
                            $row = Database::queryOne('SELECT id, username, email, role, first_name, last_name, phone_number FROM users WHERE id = ?', [$uid]);
                        }
                        // @reason: Session reconstruction is optional - user proceeds unauthenticated if DB unavailable
                    } catch (\Throwable $e) {
                        self::debugLog("reconstructSessionFromCookie: DB query failed: " . $e->getMessage());
                        $row = null;
                    }

                    if ($row && !empty($row['id'])) {
                        $_SESSION['user'] = [
                            'user_id' => $row['id'],
                            'username' => $row['username'] ?? null,
                            'email' => $row['email'] ?? null,
                            'role' => $row['role'] ?? 'user',
                            'first_name' => $row['first_name'] ?? null,
                            'last_name' => $row['last_name'] ?? null,
                            'phone_number' => $row['phone_number'] ?? null,
                        ];
                        wf_auth_set_cookie($row['id'], self::getCookieDomain(), self::isHttps());
                        self::debugLog("reconstructSessionFromCookie: Session reconstructed for user: {$row['username']}");
                    } else {
                        $_SESSION['user'] = ['user_id' => $uid];
                        self::debugLog("reconstructSessionFromCookie: Session reconstructed with minimal data for uid: {$uid}");
                    }
                } else {
                    self::debugLog("reconstructSessionFromCookie: No valid auth cookie found");
                }
            } else {
                self::debugLog("reconstructSessionFromCookie: Session user already exists: " . ($_SESSION['user']['username'] ?? 'unknown'));
            }
            // @reason: Session reconstruction is best-effort - user proceeds normally if it fails
        } catch (\Throwable $e) {
            self::debugLog("reconstructSessionFromCookie: Exception: " . $e->getMessage());
        }
    }

    public static function logout(): void
    {
        self::debugLog("logout: Starting logout process");

        // CRITICAL: Clear auth cookies from $_COOKIE IMMEDIATELY as the very first operation
        // This prevents any subsequent authentication checks in this request from reading stale cookies
        require_once dirname(__DIR__) . '/auth_cookie.php';
        unset($_COOKIE[wf_auth_cookie_name()]);
        unset($_COOKIE[wf_auth_client_cookie_name()]);
        unset($_COOKIE[session_name()]);
        self::debugLog("logout: Cleared auth cookies from \$_COOKIE");

        // Set a temporary flag to prevent session reconstruction for the next few seconds
        $dom = self::getCookieDomain();
        $sec = self::isHttps();
        $sameSite = $sec ? 'None' : 'Lax';

        self::debugLog("logout: Domain: " . ($dom ?: 'empty') . ", Secure: " . ($sec ? 'yes' : 'no'));

        // Set logout marker (expires in 10 seconds - enough time for logout flow)
        // CRITICAL: Set for all domain variations to ensure it's detected regardless of cookie domain
        $markerOpts = [
            'expires' => time() + 10, // 10 seconds
            'path' => '/',
            'secure' => $sec,
            'httponly' => false, // Need to be readable by JavaScript if needed
            'samesite' => $sameSite,
        ];

        // Set for multiple domain variations to ensure detection
        $domains = ['', $dom];
        if ($dom && !str_starts_with($dom, '.')) {
            $domains[] = '.' . $dom;
        }

        foreach ($domains as $domain) {
            $opts = $markerOpts;
            if (!empty($domain)) {
                $opts['domain'] = $domain;
            }
            @setcookie('WF_LOGOUT_IN_PROGRESS', '1', $opts);
            self::debugLog("logout: Set WF_LOGOUT_IN_PROGRESS for domain: " . ($domain ?: 'empty'));
        }

        // CRITICAL: Also set in $_COOKIE immediately for any subsequent calls in this request
        $_COOKIE['WF_LOGOUT_IN_PROGRESS'] = '1';

        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Attempt to use central SessionManager if available to ensure correct save_path
            if (class_exists('SessionManager')) {
                SessionManager::init();
            } else {
                @session_start();
            }
        }
        $sid = session_id();
        $savePath = ini_get('session.save_path');

        self::debugLog("logout: Session ID: {$sid}, Save Path: {$savePath}");

        $_SESSION = [];
        self::debugLog("logout: Cleared session data");

        // @reason: Session cleanup is best-effort
        try {
            if (ini_get("session.use_cookies")) {
                $opts = [
                    'expires' => time() - 42000,
                    'path' => '/',
                    'secure' => $sec,
                    'httponly' => true,
                    'samesite' => $sameSite,
                ];
                // CRITICAL: Only set domain if it's a non-empty string
                if ($dom !== '') {
                    $opts['domain'] = $dom;
                }
                @setcookie(session_name(), '', $opts);
                self::debugLog("logout: Cleared session cookie with domain: " . ($dom ?: 'empty'));
            }
        } catch (\Throwable $e) {
            self::debugLog("logout: Session cookie clear failed: " . $e->getMessage());
        }

        try {
            @session_unset();
            @session_destroy();
            self::debugLog("logout: Destroyed session");
        } catch (\Throwable $e) {
            self::debugLog("logout: Session destroy failed: " . $e->getMessage());
        }

        try {
            if (!empty($sid) && !empty($savePath)) {
                $sessFile = rtrim((string) $savePath, '/') . '/sess_' . $sid;
                if (is_file($sessFile)) {
                    @unlink($sessFile);
                    self::debugLog("logout: Deleted session file: {$sessFile}");
                }
            }
            // @reason: Session file cleanup is best-effort
        } catch (\Throwable $e) {
            self::debugLog("logout: Session file delete failed: " . $e->getMessage());
        }

        $sameSite = $sec ? 'None' : 'Lax';

        // Clear cookies for multiple domain variations to ensure complete logout
        $domains = ['', $dom];
        if ($dom && !str_starts_with($dom, '.')) {
            $domains[] = '.' . $dom;
        }

        // CRITICAL: Only call the helper functions - they now properly handle domain
        // Do NOT add redundant setcookie calls here that bypass the fixed logic!
        foreach ($domains as $domain) {
            wf_auth_clear_cookie($domain, $sec);
            wf_auth_clear_client_hint($domain, $sec);
            self::debugLog("logout: Cleared WF_AUTH cookies for domain: " . ($domain ?: 'empty'));
        }

        self::debugLog("logout: Logout complete");
    }
}
