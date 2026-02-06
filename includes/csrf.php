<?php

/**
 * Simple CSRF helper utilities.
 * Usage:
 *  - $token = csrf_token('admin_secrets');
 *  - if (!csrf_validate('admin_secrets', $_POST['csrf'] ?? '')) { // handle error }
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

/**
 * Get or create a CSRF token for a given namespace.
 * @param string $ns
 * @return string
 */
function csrf_token(string $ns): string
{
    $key = 'csrf_' . preg_replace('/[^a-z0-9_\-]/i', '_', $ns);
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(16));
    }
    return (string)$_SESSION[$key];
}

/**
 * Validate a CSRF token for a given namespace.
 * @param string $ns
 * @param string $token
 * @param bool $rotate Whether to rotate token on success (single-use)
 * @return bool
 */
function csrf_validate(string $ns, $token, bool $rotate = true): bool
{
    $key = 'csrf_' . preg_replace('/[^a-z0-9_\-]/i', '_', $ns);
    $token = (string)$token;
    $valid = isset($_SESSION[$key]) && hash_equals((string)$_SESSION[$key], $token);
    if ($valid && $rotate) {
        // Rotate token to prevent replay
        $_SESSION[$key] = bin2hex(random_bytes(16));
    }
    return $valid;
}
