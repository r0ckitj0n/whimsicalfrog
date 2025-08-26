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

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
        if (!empty($socket)) {
            $dsn = "mysql:unix_socket={$socket};dbname={$db};charset=utf8mb4";
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
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
