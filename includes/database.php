<?php

/**
 * Database Connection Manager (Singleton)
 */
class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        // Detect environment for database selection
        $isDevelopment = $this->isDevelopmentEnvironment();
        
        if ($isDevelopment) {
            // SQLite for development
            $sqliteDbPath = __DIR__ . '/../database/whimsicalfrog_dev.sqlite';
            
            if (!file_exists($sqliteDbPath)) {
                throw new PDOException("Development database not found. Please run setup-dev-database.php first.");
            }
            
            $dsn = "sqlite:" . $sqliteDbPath;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            try {
                $this->pdo = new PDO($dsn, null, null, $options);
                // Enable foreign key constraints in SQLite
                $this->pdo->exec("PRAGMA foreign_keys = ON");
            } catch (PDOException $e) {
                throw new PDOException("SQLite connection failed: " . $e->getMessage(), (int)$e->getCode());
            }
            
        } else {
            // MySQL for production - use existing globals or environment variables
            global $host, $db, $user, $pass, $port, $socket;
            
            // Fallback to environment variables if globals not set
            if (empty($host)) {
                $host = getenv('WF_DB_LIVE_HOST') ?: 'localhost';
                $db = getenv('WF_DB_LIVE_NAME') ?: 'whimsicalfrog';
                $user = getenv('WF_DB_LIVE_USER') ?: 'root';
                $pass = getenv('WF_DB_LIVE_PASS') ?: '';
                $port = getenv('WF_DB_LIVE_PORT') ?: 3306;
                $socket = getenv('WF_DB_LIVE_SOCKET') ?: '';
            }

            // Add a conservative connect_timeout in the DSN to avoid long hangs
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4;connect_timeout=3";
            if (!empty($socket)) {
                $dsn = "mysql:unix_socket={$socket};dbname={$db};charset=utf8mb4;connect_timeout=3";
            }

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // Fallback timeout guard (may be ignored by some MySQL clients but harmless)
                PDO::ATTR_TIMEOUT            => 3,
            ];

            // Keep init command generic; we'll harmonize to server's collation after connect
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                // @phpstan-ignore-next-line constant defined only for mysql driver
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4";
            }

            try {
                $this->pdo = new PDO($dsn, $user, $pass, $options);
                // Harmonize to server/session collation to avoid mix errors across pages
                try {
                    $colStmt = $this->pdo->query("SELECT @@collation_connection AS coll");
                    $row = $colStmt ? $colStmt->fetch(PDO::FETCH_ASSOC) : null;
                    $serverCollation = isset($row['coll']) ? (string)$row['coll'] : '';
                    $targetCollation = $serverCollation;
                    if (!$targetCollation) {
                        // Prefer utf8mb4_0900_ai_ci on MySQL 8+, else fallback to utf8mb4_unicode_ci
                        $targetCollation = 'utf8mb4_0900_ai_ci';
                    }
                    // Apply target collation; if server doesn't support it, fallback silently to unicode_ci
                    try {
                        $this->pdo->exec("SET NAMES utf8mb4 COLLATE " . $targetCollation);
                    } catch (\Throwable $e2) {
                        try {
                            $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                        } catch (\Throwable $e3) {
                        }
                    }
                    try {
                        $this->pdo->exec("SET collation_connection = " . $targetCollation);
                    } catch (\Throwable $e4) {
                        try {
                            $this->pdo->exec("SET collation_connection = utf8mb4_unicode_ci");
                        } catch (\Throwable $e5) {
                        }
                    }
                } catch (\Throwable $e) { /* ignore */
                }
            } catch (PDOException $e) {
                throw new PDOException("MySQL connection failed: " . $e->getMessage(), (int)$e->getCode());
            }
        }
    }
    
    private function isDevelopmentEnvironment(): bool
    {
        // Check explicit environment variable first - this overrides everything
        $whfEnv = getenv('WHF_ENV') ?: ($_SERVER['WHF_ENV'] ?? '');
        if ($whfEnv === 'prod' || $whfEnv === 'production') {
            return false;
        }
        if ($whfEnv === 'local' || $whfEnv === 'dev' || $whfEnv === 'development') {
            return true;
        }
        
        // If REPLIT_DEPLOYMENT_ID is set, this is a Replit deployment (production)
        if (!empty($_SERVER['REPLIT_DEPLOYMENT_ID']) || getenv('REPLIT_DEPLOYMENT_ID') !== false) {
            return false;
        }
        
        // Multiple checks for development environment (only if no deployment ID)
        return (
            // Command line
            PHP_SAPI === 'cli' ||
            // Localhost indicators
            (isset($_SERVER['HTTP_HOST']) && 
                (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                 strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) ||
            // Replit development environment (but not deployment)
            (!empty($_SERVER['REPL_ID']) && empty($_SERVER['REPLIT_DEPLOYMENT_ID']))
        );
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    /**
     * Create a new PDO connection with provided parameters.
     * Useful for connecting to alternate databases (e.g., live vs local) in admin tools.
     *
     * @param string $host
     * @param string $db
     * @param string $user
     * @param string $pass
     * @param int $port
     * @param string|null $socket
     * @param array $options
     * @return PDO
     * @throws PDOException
     */
    public static function createConnection(
        string $host,
        string $db,
        string $user,
        string $pass,
        int $port = 3306,
        ?string $socket = null,
        array $options = []
    ): PDO {
        $dsn = $socket
            ? "mysql:unix_socket={$socket};dbname={$db};charset=utf8mb4;connect_timeout=3"
            : "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4;connect_timeout=3";

        $defaultOptions = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ];
        $opts = $options + $defaultOptions; // keep provided options but ensure defaults exist

        return new PDO($dsn, $user, $pass, $opts);
    }

    /**
     * Execute a SELECT and return all rows
     * @param string $sql
     * @param array $params
     * @return array
     */
    public static function queryAll(string $sql, array $params = []): array
    {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a SELECT and return first row or null
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }


    /**
     * Execute an INSERT/UPDATE/DELETE and return affected rows
     * @param string $sql
     * @param array $params
     * @return int affected rows
     */
    public static function execute(string $sql, array $params = []): int
    {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Transaction helpers */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    public static function rollBack(): bool
    {
        return self::getInstance()->rollBack();
    }

    /** Get the last inserted ID for the current connection */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    // Prevent cloning and unserialization
    private function __clone()
    {
    }
    public function __wakeup()
    {
    }
}
