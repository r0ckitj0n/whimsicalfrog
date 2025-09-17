<?php

/**
 * Common functions for the Whimsical Frog website
 *
 * This file contains shared functions used across multiple PHP files
 * to ensure consistent functionality throughout the site.
 */




// TEMPORARILY DISABLED: Include required logging classes
// require_once __DIR__ . '/logging_config.php';
// require_once __DIR__ . '/logger.php';
// require_once __DIR__ . '/database_logger.php';
// require_once __DIR__ . '/error_logger.php';
// require_once __DIR__ . '/admin_logger.php';

// TEMPORARILY DISABLED: Initialize comprehensive logging system
/*
try {
    // Initialize logging configuration
    $loggingConfig = LoggingConfig::initializeLogging();
    $fileConfig = $loggingConfig['file_logging'];

    // Initialize Logger with application log file and comprehensive levels
    Logger::init($fileConfig['files']['application'], $fileConfig['levels']);

    // Initialize database logging (primary logging method)
    DatabaseLogger::getInstance();
    ErrorLogger::init();
    AdminLogger::init();

    // Log system initialization
    Logger::info('Comprehensive logging system initialized', [
        'file_logging' => $fileConfig['enabled'],
        'database_logging' => $loggingConfig['database_logging']['primary'],
        'admin_logging' => $loggingConfig['retail_admin_logging']['enabled'],
        'seo_logging' => $loggingConfig['seo_logging']['enabled'],
        'security_logging' => $loggingConfig['security_logging']['enabled'],
        'logs_directory' => $fileConfig['directory']
    ]);

} catch (Exception $e) {
    error_log("Failed to initialize logging system: " . $e->getMessage());
}
*/

/**
 * Generates an HTML <img> tag with WebP support and fallback to the original image format.
 *
 * @param string $originalPath The path to the original image (e.g., 'images/my_image.png').
 * @param string $altText The alt text for the image.
 * @param string $class Optional CSS classes for the image tag.
 * @param string $style Deprecated: inline styles are not allowed. This parameter is ignored.
 * @deprecated The $style parameter is ignored to comply with no-inline-styles policy.
 * @return string The HTML <img> tag.
 */
function getImageTag($imagePath, $altText = '', $class = '', $style = '')
{
    if (empty($imagePath)) {
        // Strict: do not inject placeholders
        return '';
    }

    $pathInfo = pathinfo($imagePath);
    $extension = strtolower($pathInfo['extension'] ?? '');
    $basePath = ($pathInfo['dirname'] && $pathInfo['dirname'] !== '.')
        ? $pathInfo['dirname'] . '/' . $pathInfo['filename']
        : $pathInfo['filename'];

    $classAttr = !empty($class) ? ' class="' . htmlspecialchars($class) . '"' : '';
    // Inline styles are disallowed; $style is ignored.
    $styleAttr = '';

    // If already WebP, just return img tag
    if ($extension === 'webp') {
        return '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($altText) . '"' . $classAttr . $styleAttr . '>';
    }

    // Check if WebP version exists and use picture element for better browser support
    $webpPath = $basePath . '.webp';
    if (file_exists(__DIR__ . '/../' . $webpPath)) {
        return '<picture>'
              . '<source srcset="' . htmlspecialchars($webpPath) . '" type="image/webp">'
              . '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($altText) . '"' . $classAttr . $styleAttr . '>'
              . '</picture>';
    }

    // Strict: if no WebP available, return the original image only
    return '<img src="' . htmlspecialchars($imagePath) . '" alt="' . htmlspecialchars($altText) . '"' . $classAttr . $styleAttr . '>';
}
// sanitizeInput function moved to security_validator.php for centralization

/**
 * Validate email address
 * @param string $email
 * @return bool
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

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
 * Format price for display
 * @param float $price
 * @param string $currency
 * @return string
 */
function formatPrice($price, $currency = '$')
{
    return $currency . number_format($price, 2);
}

/**
 * Format date for display
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'M j, Y')
{
    return date($format, strtotime($date));
}

/**
 * Get file size in human readable format
 * @param int $bytes
 * @return string
 */

/**
 * NOTE: The following utility functions are centralized here to eliminate duplication.
 * Previously found in multiple files:
 * - formatFileSize: api/file_manager.php, api/upload_background.php
 * - isValidEmail: includes/email_helper.php
 *
 * When adding new utility functions, add them here instead of creating duplicates.
 */
function formatFileSize($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Truncate text to specified length
 * @param string $text
 * @param int $length
 * @param string $suffix
 * @return string
 */
function truncateText($text, $length = 100, $suffix = '...')
{
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate SEO-friendly slug from text
 * @param string $text
 * @return string
 */
function generateSlug($text)
{
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

// isAjaxRequest function is available from api/config.php

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
 * Convert array to CSV string
 * @param array $data
 * @return string
 */
function arrayToCSV($data)
{
    $output = fopen('php://temp', 'r+');
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return $csv;
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

    // Convert string booleans
    if (in_array(strtolower($value), ['true', 'false'])) {
        return strtolower($value) === 'true';
    }

    return $value;
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

/**
 * Get current URL
 * @return string
 */
function getCurrentURL()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}
/**
 * Get the active background image for a specific room type.
 *
 * @param string $roomType The type of room (e.g., 'landing', 'room_main').
 * @return string The URL of the background image, or an empty string if not found.
 */
function get_active_background($roomType)
{
    try {
        $pdo = Database::getInstance();
        $roomTypeStr = strtolower((string)$roomType);
        // Strict: only support modern schema by room_number mapping; do not infer for non-room pages
        $roomNumber = null;
        if (preg_match('/^room(\d+)$/', $roomTypeStr, $m)) {
            $roomNumber = (int)$m[1];
        } elseif (ctype_digit($roomTypeStr)) {
            $roomNumber = (int)$roomTypeStr;
        } elseif ($roomTypeStr === 'landing' || $roomTypeStr === 'room_main' || $roomTypeStr === 'shop') {
            $roomNumber = 0;
        } else {
            return '';
        }

        $stmt = $pdo->prepare("SELECT image_filename, webp_filename FROM backgrounds WHERE room_number = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
        $stmt->execute([$roomNumber]);
        $background = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($background) {
            $imageFile = !empty($background['webp_filename']) ? $background['webp_filename'] : $background['image_filename'];
            $abs = __DIR__ . '/..' . '/images/backgrounds/' . ltrim($imageFile, '/');
            if (is_file($abs)) {
                return '/images/backgrounds/' . ltrim($imageFile, '/');
            }
            // File missing on disk; surface by returning empty
            return '';
        }
    } catch (Exception $e) {
        error_log('Error fetching active background: ' . $e->getMessage());
    }
    return '';
}
