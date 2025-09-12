<?php
// Centralized session bootstrap to ensure consistent cookie parameters across pages and endpoints
// Must be included BEFORE any session_start() calls.

if (session_status() !== PHP_SESSION_ACTIVE) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $isLocal = (strpos($host, 'localhost') !== false) || (strpos($host, '127.0.0.1') !== false);

    // Detect HTTPS (may be unreliable behind proxies)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    // Use a shared cookie domain in production to avoid www/non-www or subdomain mismatches
    $cookieDomain = '';
    $isProdDomain = false;
    if (!$isLocal && $host) {
        // Normalize to apex domain whimsicalfrog.us (works for both whimsicalfrog.us and www.whimsicalfrog.us)
        if (stripos($host, 'whimsicalfrog.us') !== false) {
            $cookieDomain = '.whimsicalfrog.us';
            $isProdDomain = true;
        } else {
            // Fallback: derive base domain (simple heuristic)
            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                $cookieDomain = '.' . $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
            }
        }
    }

    // Set session cookie params before starting session
    $params = [
        'lifetime' => 86400, // 24h
        'path'     => '/',
        'domain'   => $cookieDomain,
        // Force Secure on production domain even if HTTPS detection fails due to proxy/CDN
        'secure'   => !$isLocal && ($isHttps || $isProdDomain),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($params);
    } else {
        // PHP <7.3 fallback (no associative array support for samesite)
        session_set_cookie_params($params['lifetime'], $params['path'] . '; samesite=' . $params['samesite'], $params['domain'], $params['secure'], $params['httponly']);
    }

    // Standardize session name
    if (session_name() !== 'PHPSESSID') {
        session_name('PHPSESSID');
    }

    session_start();
}
