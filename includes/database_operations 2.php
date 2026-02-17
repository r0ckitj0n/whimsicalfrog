<?php

/**
 * WhimsicalFrog Database Operation Functions
 * Centralized PHP functions to eliminate duplication
 * Generated: 2025-07-01 23:43:57
 */

// Include database dependencies
require_once __DIR__ . '/database.php';

/**
 * Make DELETE request to API endpoint
 * @param string $url
 * @param mixed $data
 * @return mixed
 */
function makeDeleteRequest($url, $data = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $result = curl_exec($ch);
    // curl_close() is deprecated in PHP 8.5 (no effect since PHP 8.0)

    return $result;
}

/**
 * Execute a prepared SELECT and return all rows
 * @param string $sql
 * @param array $params
 * @return array
 */
function executeQuery($sql, $params = [])
{
    Database::getInstance();
    return Database::queryAll($sql, $params);
}

/**
 * Execute an UPDATE/DELETE and return affected rows
 * @param string $sql
 * @param array $params
 * @return int
 */
function executeUpdate($sql, $params = [])
{
    Database::getInstance();
    return (int) Database::execute($sql, $params);
}
