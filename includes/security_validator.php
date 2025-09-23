<?php

/**
 * WhimsicalFrog Security Validation and Sanitization
 * Centralized PHP functions to eliminate duplication
 * Generated: 2025-07-01 23:31:50
 */

// Include security and database dependencies
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/auth.php';


/**
 * Sanitize input data
 * @param mixed $data
 * @return mixed
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}


/**
 * Sanitize filename for safe storage
 */
function sanitizeFilename($filename)
{
    // Remove or replace unsafe characters
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    $filename = preg_replace('/_+/', '_', $filename); // Remove multiple underscores
    $filename = trim($filename, '_'); // Remove leading/trailing underscores
    return $filename;
}
