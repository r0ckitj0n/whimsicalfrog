<?php

/**
 * WhimsicalFrog Database Connection and Query Management
 * Centralized functions to eliminate duplication and improve maintainability
 * Generated: 2025-07-01 23:15:56
 */

// Include configuration
require_once __DIR__ . '/../config.php';

/**
 * Database management class
 */
class Database
{
    private static $instance = null;
    private $pdo;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        try {
            // Detect environment
            $isLocalhost = false;

            // Check if running from command line
            if (PHP_SAPI === 'cli') {
                $isLocalhost = true;
            }

            // Check HTTP_HOST for localhost indicators
            if (isset($_SERVER['HTTP_HOST'])) {
                if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
                    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                    $isLocalhost = true;
                }
            }

            // Check SERVER_NAME for localhost indicators
            if (isset($_SERVER['SERVER_NAME'])) {
                if (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false ||
                    strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false) {
                    $isLocalhost = true;
                }
            }

            // Database configuration based on environment
            if ($isLocalhost) {
                // Local dev: connect via TCP as root with no password
                $host   = '127.0.0.1';
                $db     = 'whimsicalfrog';
                $user   = 'root';
                $pass   = '';
                $port   = 3306;
                $socket = null;
            } else {
                // Production database credentials - IONOS values
                $host = 'db5017975223.hosting-data.io';
                $db   = 'dbs14295502';
                $user = 'dbu2826619';
                $pass = 'Palz2516!';
            }

            // Create DSN and options
            $charset = 'utf8mb4';
            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get singleton database instance
     * @return PDO
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    /**
     * Get the Database object instance (for accessing the PDO connection)
     * @return Database
     */
    public static function getInstanceObject()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the PDO connection directly
     * @return PDO
     */
    public function getConnection()
    {
        return $this->pdo;
    }

    /**
     * Get a fresh database connection (for cases that need it)
     * @return PDO
     */
    public static function getFreshConnection()
    {
        // Use same logic as constructor but return new instance
        $isLocalhost = false;

        if (PHP_SAPI === 'cli') {
            $isLocalhost = true;
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
                strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
                $isLocalhost = true;
            }
        }

        if (isset($_SERVER['SERVER_NAME'])) {
            if (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false ||
                strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false) {
                $isLocalhost = true;
            }
        }

        if ($isLocalhost) {
            $host = 'localhost';
            $db   = 'whimsicalfrog';
            $iniPath = __DIR__ . '/../config/my.cnf';
            if (file_exists($iniPath)) {
                $configs = parse_ini_file($iniPath, true, INI_SCANNER_TYPED);
                $client = $configs['client'] ?? [];
                $user    = $client['user']     ?? 'admin';
                $pass    = $client['password'] ?? 'Palz2516!';
                $host    = $client['host']     ?? $host;
                $port    = $client['port']     ?? null;
                $socket  = $client['socket']   ?? null;
            } else {
                $user   = 'admin';
                $pass   = 'Palz2516!';
                $port   = null;
                $socket = null;
            }
        } else {
            $host = 'db5017975223.hosting-data.io';
            $db   = 'dbs14295502';
            $user = 'dbu2826619';
            $pass = 'Palz2516!';
        }

        $charset = 'utf8mb4';
        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        return new PDO($dsn, $user, $pass, $options);
    }

    /**
     * Execute a prepared statement and return PDOStatement
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public static function query($sql, $params = [])
    {
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
    public static function queryRow($sql, $params = [])
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


    /**
     * Execute a prepared statement and return all rows
     * @param string $sql
     * @param array $params
     * @return array
     */
    public static function queryAll($sql, $params = [])
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * Execute an UPDATE/DELETE and return affected rows
     * @param string $sql
     * @param array $params
     * @return int
     */
    public static function execute($sql, $params = [])
    {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }


    /**
     * Begin transaction
     */
    public static function beginTransaction()
    {
        return self::getInstance()->beginTransaction();
    }


    /**
     * Commit transaction
     */
    public static function commit()
    {
        return self::getInstance()->commit();
    }


    /**
     * Rollback transaction
     */
    public static function rollback()
    {
        return self::getInstance()->rollBack();
    }
}

/**
 * Get database connection
 */
function getDbConnection()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = Database::getInstance();
        } catch (PDOException $e) {
            error_log("Database connection error in getDbConnection: " . $e->getMessage());
            return null;
        }
    }

    return $pdo;
}
