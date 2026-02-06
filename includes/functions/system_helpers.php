<?php

/**
 * System and Security utility functions
 */

/**
 * Generate a secure random token
 * @param int $length
 * @return string
 */
function generateSecureToken($length = 32)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Get client IP address
 * @return string
 */
function getClientIP()
{
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Calculate time ago from timestamp
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime)
{
    $time = time() - strtotime($datetime);

    if ($time < 60) {
        return 'just now';
    }
    if ($time < 3600) {
        return floor($time / 60) . ' minutes ago';
    }
    if ($time < 86400) {
        return floor($time / 3600) . ' hours ago';
    }
    if ($time < 2592000) {
        return floor($time / 86400) . ' days ago';
    }
    if ($time < 31536000) {
        return floor($time / 2592000) . ' months ago';
    }

    return floor($time / 31536000) . ' years ago';
}

/**
 * Validate and sanitize SKU
 * @param string $sku
 * @return string|false
 */
function validateSKU($sku)
{
    $sku = trim(strtoupper($sku));
    if (preg_match('/^[A-Z0-9\-_]+$/', $sku)) {
        return $sku;
    }
    return false;
}

/**
 * Check if string is JSON
 * @param string $string
 * @return bool
 */
function isJSON($string)
{
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Get environment variable with default
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env($key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    if (in_array(strtolower($value), ['true', 'false'])) {
        return strtolower($value) === 'true';
    }

    return $value;
}

/**
 * Validate email address
 * @param string $email
 * @return bool
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
