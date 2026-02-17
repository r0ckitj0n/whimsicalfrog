<?php

/**
 * URL and Path utility functions
 */

function wf_base_url()
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
    );

    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $base = $scheme . '://' . $host;
}

function wf_absolute_url($path)
{
    if ($path === null || $path === '') {
        return wf_base_url();
    }
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return rtrim(wf_base_url(), '/') . '/' . ltrim($path, '/');
}

/**
 * Get current URL
 * @return string
 */
function getCurrentURL()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    return $protocol . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');
}

/**
 * Redirect to URL
 * @param string $url
 * @param int $statusCode
 */
function redirect($url, $statusCode = 302)
{
    header('Location: ' . $url, true, $statusCode);
    exit;
}
