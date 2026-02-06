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
    $parts = explode('.', $host);
    $baseDomain = $host;
    if (count($parts) >= 2) {
        $baseDomain = $parts[count($parts) - 2] . '.' . $parts[count($parts) - 1];
    }
    $cookieDomain = '.' . $baseDomain;

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
    );

    // 3. Initialize Session
    require_once __DIR__ . '/session.php';
    session_init([
        'name' => 'PHPSESSID',
        'lifetime' => 0,
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'None',
    ]);

    // 4. Security definition
    if (!defined('INCLUDED_FROM_INDEX')) {
        define('INCLUDED_FROM_INDEX', true);
    }
}
