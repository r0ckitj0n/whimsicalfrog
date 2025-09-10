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
        $imagePath = 'images/items/placeholder.webp'; // Default placeholder if path is empty
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

    // Fallback: try WebP first, then swap to original on error via data-fallback-src handled by JS
    $webpPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.webp';
    return '<img src="' . htmlspecialchars($webpPath) . '" alt="' . htmlspecialchars($altText) . '"' . $classAttr . $styleAttr
          . ' data-fallback-src="' . htmlspecialchars($imagePath) . '">';
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
        // Normalize and handle modern schema first
        $roomTypeStr = strtolower((string)$roomType);
        $background = null;

        // If request is for non-room pages (landing, room_main, shop, about, contact),
        // do not guess from DB. Let caller fallback to helper/static assets.
        $nonRoomPages = ['about','contact'];
        if (in_array($roomTypeStr, $nonRoomPages, true)) {
            $background = null; // cause caller fallback in header.php
        } else {
            // If matches roomN or numeric N, use room_number-based lookup
            $roomNumber = null;
            // Map symbolic contexts to canonical room numbers
            if ($roomTypeStr === 'room_main' || $roomTypeStr === 'shop' || $roomTypeStr === 'landing') {
                $roomNumber = 0; // main room stored as 0 in current schema
            }
            if (preg_match('/^room(\d+)$/', $roomTypeStr, $m)) {
                $roomNumber = (int)$m[1];
            } elseif (ctype_digit($roomTypeStr)) {
                $roomNumber = (int)$roomTypeStr;
            }

            if (!is_null($roomNumber)) {
                // Modern schema: backgrounds.room_number
                try {
                    if ($roomNumber === 0) {
                        // Special handling: two logical contexts share room_number=0
                        if ($roomTypeStr === 'landing') {
                            // Prefer filenames indicating home
                            $stmt = $pdo->prepare(
                                "SELECT image_filename, webp_filename FROM backgrounds 
                                 WHERE room_number = 0 AND is_active = 1 
                                 ORDER BY (image_filename LIKE '%home%' OR webp_filename LIKE '%home%') DESC, id DESC LIMIT 1"
                            );
                            $stmt->execute();
                            $background = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                        } else {
                            // room_main or shop: prefer room_main assets
                            $stmt = $pdo->prepare(
                                "SELECT image_filename, webp_filename FROM backgrounds 
                                 WHERE room_number = 0 AND is_active = 1 
                                 ORDER BY (image_filename LIKE '%room_main%' OR webp_filename LIKE '%room_main%') DESC, id DESC LIMIT 1"
                            );
                            $stmt->execute();
                            $background = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                        }
                        // If still null, fallback to any active under room 0
                        if ($background === null) {
                            $stmt = $pdo->prepare("SELECT image_filename, webp_filename FROM backgrounds WHERE room_number = 0 AND is_active = 1 ORDER BY id DESC LIMIT 1");
                            $stmt->execute();
                            $background = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                        }
                    } else if ($roomNumber > 0) {
                        $stmt = $pdo->prepare("SELECT image_filename, webp_filename FROM backgrounds WHERE room_number = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
                        $stmt->execute([$roomNumber]);
                        $background = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                    }
                } catch (Throwable $eModern) {
                    // fall through to legacy attempt below
                }
            }

            // Legacy compatibility: try room_type column if background is still null
            if ($background === null) {
                try {
                    $stmt = $pdo->prepare("SELECT image_filename, webp_filename FROM backgrounds WHERE room_type = ? AND is_active = 1 LIMIT 1");
                    $stmt->execute([$roomType]);
                    $background = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
                } catch (Throwable $eLegacy) {
                    // If room_type no longer exists, do NOT select a generic background here for non-numeric contexts
                    // to avoid wrong images on landing/room_main. Just leave $background as null.
                    if (!((string)($eLegacy->getCode()) === '42S22' || str_contains(strtolower($eLegacy->getMessage() ?? ''), 'unknown column'))) {
                        // Re-throw unexpected errors to be handled by outer catch
                        throw $eLegacy;
                    }
                }
            }
        }

        if ($background) {
                        // Prefer WebP, otherwise fallback to original extension
            $imageFile = !empty($background['webp_filename']) ? $background['webp_filename'] : $background['image_filename'];

            // Build a list of candidate paths to try, keeping the original name first
            $candidates = [];
            $candidates[] = '/images/backgrounds/' . $imageFile;
            $candidates[] = '/images/' . $imageFile;

            // If not already prefixed, also try with the legacy "background_" prefix
            if (strpos($imageFile, 'background_') !== 0) {
                $prefixed = 'background_' . $imageFile;
                $candidates[] = '/images/backgrounds/' . $prefixed;
                $candidates[] = '/images/' . $prefixed;
            }

            // Return the first candidate that actually exists on disk
            foreach ($candidates as $relPath) {
                if (file_exists(__DIR__ . '/..' . $relPath)) {
                    return $relPath;
                }
            }

            // If nothing is found, fall back to first candidate (may 404 but avoids crash)
            return $candidates[0];
        }
    } catch (Exception $e) {
        error_log('Error fetching active background: ' . $e->getMessage());
    }

    return ''; // Return empty string on failure or if no background is set
}
