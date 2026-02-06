<?php

/**
 * Database Connection Manager (Singleton Conductor)
 * Manages the shared PDO instance by delegating to modular components.
 */

require_once __DIR__ . '/database/DatabaseEnv.php';
require_once __DIR__ . '/database/ConnectionManager.php';
require_once __DIR__ . '/database/QueryExecutor.php';

class Database
{
    private static $instance = null;
    private $pdo;
    private bool $diagEnabled = false;
    private float $diagStart = 0.0;

    private function __construct()
    {
        DatabaseEnv::ensureEnvLoaded();
        $this->diagEnabled = $this->shouldLogDiagnostics();
        $this->diagStart = microtime(true);
        
        $isDev = DatabaseEnv::isDevelopmentEnvironment();
        $this->pdo = ConnectionManager::createPdo($isDev, [$this, 'logDiag']);
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }

    /**
     * Delegates to QueryExecutor
     */
    public static function queryAll(string $sql, array $params = []): array { return QueryExecutor::queryAll(self::getInstance(), $sql, $params); }
    public static function queryOne(string $sql, array $params = []): ?array { return QueryExecutor::queryOne(self::getInstance(), $sql, $params); }
    public static function execute(string $sql, array $params = []): int { return QueryExecutor::execute(self::getInstance(), $sql, $params); }
    public static function beginTransaction(): bool { return QueryExecutor::beginTransaction(self::getInstance()); }
    public static function commit(): bool { return QueryExecutor::commit(self::getInstance()); }
    public static function rollBack(): bool { return QueryExecutor::rollBack(self::getInstance()); }
    public static function lastInsertId(): string { return QueryExecutor::lastInsertId(self::getInstance()); }

    /**
     * Create a new standalone connection (useful for admin tools)
     */
    public static function createConnection(string $host, string $db, string $user, string $pass, int $port = 3306, ?string $socket = null, array $options = []): PDO
    {
        return ConnectionManager::createConnection($host, $db, $user, $pass, $port, $socket, $options);
    }

    public static function isAvailableQuick(float $timeout = 0.6): bool
    {
        // Simple probe for dev/local environments
        if (PHP_SAPI !== 'cli') {
            $hh = $_SERVER['HTTP_HOST'] ?? '';
            if (strpos($hh, 'localhost') === false && strpos($hh, '127.0.0.1') === false) return true;
        }
        
        $host = getenv('WF_DB_LOCAL_HOST') ?: 'localhost';
        $port = (int)(getenv('WF_DB_LOCAL_PORT') ?: 3306);
        $errno = 0; $errstr = '';
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (is_resource($fp)) { fclose($fp); return true; }
        return false;
    }

    private function shouldLogDiagnostics(): bool
    {
        return getenv('WF_DB_CONN_DIAG') === '1' || (isset($_GET['wf_db_conn_diag']) && $_GET['wf_db_conn_diag'] === '1');
    }

    public function logDiag(string $message): void
    {
        if (!$this->diagEnabled) return;
        $delta = microtime(true) - $this->diagStart;
        error_log(sprintf('[WF DB diag +%.3f] %s', $delta, $message));
    }

    private function __clone() {}
    public function __wakeup() {}
}
