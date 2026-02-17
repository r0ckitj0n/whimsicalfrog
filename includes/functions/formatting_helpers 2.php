<?php

/**
 * Data formatting utility functions
 */

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
 * Format.created_at for display
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'M j, Y')
{
    returndate($format, strtotime($date));
}

/**
 * Get file size in human readable format
 * @param int $bytes
 * @return string
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
