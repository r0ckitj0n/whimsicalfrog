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
        // Prefer MySQL unless PostgreSQL environment variables are explicitly provided.
        $hasPg = getenv('PGHOST') && getenv('PGPORT') && getenv('PGDATABASE') && getenv('PGUSER') && getenv('PGPASSWORD');
        
        if ($hasPg) {
            // PostgreSQL branch (used primarily in certain hosted dev environments)
            $pgHost = getenv('PGHOST');
            $pgPort = getenv('PGPORT');
            $pgDatabase = getenv('PGDATABASE');
            $pgUser = getenv('PGUSER');
            $pgPassword = getenv('PGPASSWORD');

            $dsn = "pgsql:host=$pgHost;port=$pgPort;dbname=$pgDatabase;sslmode=require";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 10,
            ];

            try {
                $this->pdo = new PDO($dsn, $pgUser, $pgPassword, $options);
                $this->pdo->exec("SET TIME ZONE 'UTC'");
            } catch (PDOException $e) {
                throw new PDOException("PostgreSQL connection failed: " . $e->getMessage(), (int)$e->getCode());
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

            // Optional dev override: allow disabling the DB by setting WF_DB_DEV_DISABLE=1
            // (handled downstream by helpers; do not throw here so landing still responds)

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

            // Development fast-fail: avoid long hangs when DB is down locally.
            // Only apply this preflight on development hosts to keep production behavior unchanged.
            try {
                if ($this->isDevelopmentEnvironment()) {
                    @ini_set('mysql.connect_timeout', '2');
                    @ini_set('default_socket_timeout', '2');
                    $reachable = false;
                    if (!empty($socket)) {
                        // For sockets, a simple existence check is enough as a heuristic
                        $reachable = @file_exists($socket);
                    } else {
                        $h = $host ?: 'localhost';
                        $p = (int)($port ?: 3306);
                        $errno = 0; $errstr = '';
                        $fp = @fsockopen($h, $p, $errno, $errstr, 0.8);
                        if (is_resource($fp)) { fclose($fp); $reachable = true; }
                    }
                    if (!$reachable) {
                        // Throw quickly so callers can catch and render fallbacks (e.g., landing page)
                        throw new PDOException("MySQL host not reachable on dev: {$host}:{$port}", 2001);
                    }
                }
            } catch (\Throwable $____e) { /* ignore preflight errors; proceed to PDO attempt */ }

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
        
        // Only treat as special dev if PostgreSQL envs exist
        if (getenv('PGHOST') && getenv('PGDATABASE') && getenv('PGUSER') && getenv('PGPASSWORD')) {
            return true;
        }
        
        // If REPLIT_DEPLOYMENT_ID is set AND no PostgreSQL, this is a Replit deployment (production)
        if (!empty($_SERVER['REPLIT_DEPLOYMENT_ID']) || getenv('REPLIT_DEPLOYMENT_ID') !== false) {
            return false;
        }
        
        // Generic dev indicators
        return (
            PHP_SAPI === 'cli' ||
            (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) ||
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
     * Quick local DB availability probe to avoid hangs in development.
     * Returns true if a TCP socket to the expected MySQL host:port can be opened within $timeout seconds.
     * Only intended for localhost/dev use; production code should not rely on this.
     */
    public static function isAvailableQuick(float $timeout = 0.6): bool
    {
        try {
            // Consider local/dev if CLI or host indicates localhost/127.0.0.1
            $isLocal = (PHP_SAPI === 'cli');
            if (!$isLocal) {
                $hh = $_SERVER['HTTP_HOST'] ?? '';
                $isLocal = (strpos($hh, 'localhost') !== false) || (strpos($hh, '127.0.0.1') !== false);
            }
            if ($isLocal) {
                $disable = getenv('WF_DB_DEV_DISABLE');
                if ($disable === '1' || strtolower((string)$disable) === 'true') {
                    return false;
                }
            } else {
                return true; // production/staging: assume DB available
            }
            $socket = getenv('WF_DB_LOCAL_SOCKET');
            if ($socket && @file_exists($socket)) {
                return true;
            }
            $host = getenv('WF_DB_LOCAL_HOST') ?: 'localhost';
            $port = (int)(getenv('WF_DB_LOCAL_PORT') ?: 3306);
            $errno = 0; $errstr = '';
            $ctx = stream_context_create([ 'socket' => [ 'so_reuseport' => true ] ]);
            $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
            if (is_resource($fp)) { fclose($fp); return true; }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
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
