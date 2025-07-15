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
function makeDeleteRequest($url, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

/**
 * Execute a prepared statement and return results
 * @param string $sql
 * @param array $params
 * @return PDOStatement
 */
function executeQuery($sql, $params = []) {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Execute an UPDATE/DELETE and return affected rows
 * @param string $sql
 * @param array $params
 * @return int
 */
function executeUpdate($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->rowCount();
}

?>