<?php
/**
 * Centralized Database Connection Helper
 * 
 * This file provides a single point for database connections and common database operations
 * to ensure consistency and reduce code duplication across the application.
 */

require_once __DIR__ . '/../api/config.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $this->pdo = new PDO($GLOBALS['dsn'], $GLOBALS['user'], $GLOBALS['pass'], $GLOBALS['options']);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get singleton database instance
     * @return PDO
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }
    
    /**
     * Get a fresh database connection (for cases that need it)
     * @return PDO
     */
    public static function getConnection() {
        return new PDO($GLOBALS['dsn'], $GLOBALS['user'], $GLOBALS['pass'], $GLOBALS['options']);
    }
    
    /**
     * Execute a prepared statement and return results
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public static function query($sql, $params = []) {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Execute a prepared statement and return single row
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public static function queryRow($sql, $params = []) {
        $stmt = self::query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Execute a prepared statement and return all rows
     * @param string $sql
     * @param array $params
     * @return array
     */
    public static function queryAll($sql, $params = []) {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Execute an INSERT and return the last insert ID
     * @param string $sql
     * @param array $params
     * @return string
     */
    public static function insert($sql, $params = []) {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    }
    
    /**
     * Execute an UPDATE/DELETE and return affected rows
     * @param string $sql
     * @param array $params
     * @return int
     */
    public static function execute($sql, $params = []) {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction() {
        return self::getInstance()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public static function commit() {
        return self::getInstance()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback() {
        return self::getInstance()->rollBack();
    }
}
?> 