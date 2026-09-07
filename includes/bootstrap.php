<?php
/**
 * Whimsical Frog Bootstrap
 * Handles canonical host enforcement and session initialization.
 */

function wf_bootstrap() {
    // 1. Canonical Host Enforcement
    try {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host && stripos($host, 'www.whimsicalfrog.us') === 0) {
            $scheme = 'https';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            $target = $scheme . '://whimsicalfrog.us' . $uri;
            header('Location: ' . $target, true, 301);
            exit;
        }
    } catch (\Throwable $e) {
        error_log('[bootstrap] canonical host enforcement failed: ' . $e->getMessage());
    }

    // 2. Derive base domain and cookie settings
    $host = $_SERVER['HTTP_HOST'] ?? 'whimsicalfrog.us';
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host)[0];
    }
    require_once __DIR__ . '/helpers/AuthSessionHelper.php';
    $cookieDomain = AuthSessionHelper::getCookieDomain();
    $isHttps = AuthSessionHelper::isHttps();

    // 3. Initialize Session
    require_once __DIR__ . '/session.php';
    try {
        session_init([
            'name' => 'PHPSESSID',
            'lifetime' => 0,
            'path' => '/',
            'domain' => $cookieDomain,
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'None',
        ]);
    } catch (\Throwable $e) {
        // Fail closed but do not crash the request: a session integrity failure
        // (e.g. fingerprint mismatch, corrupt session store) should degrade to an
        // anonymous/logged-out request instead of surfacing as an HTTP 500 to every
        // visitor who happens to hit it. The underlying condition is still logged.
        error_log('[bootstrap] session initialization failed: ' . $e->getMessage());
    }

    // 4. Security definition
    if (!defined('INCLUDED_FROM_INDEX')) {
        define('INCLUDED_FROM_INDEX', true);
    }
}
