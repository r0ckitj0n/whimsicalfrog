<?php
/**
 * Database Connection Manager (Singleton)
 */
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // These globals are expected to be set by a config file before this class is used.
        global $host, $db, $user, $pass, $port, $socket;

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

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance() {
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

    // Prevent cloning and unserialization
    private function __clone() { }
    public function __wakeup() { }
}
