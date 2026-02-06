<?php

/**
 * Handles database connection logic for MySQL and PostgreSQL
 */
class ConnectionManager
{
    public static function createPdo($isDev, callable $logDiag): PDO
    {
        $hasPg = getenv('PGHOST') && getenv('PGPORT') && getenv('PGDATABASE') && getenv('PGUSER') && getenv('PGPASSWORD');

        if ($hasPg) {
            return self::createPgPdo();
        }

        return self::createMySqlPdo($isDev, $logDiag);
    }

    /**
     * Create a standalone connection with explicit credentials
     */
    public static function createConnection(string $host, string $db, string $user, string $pass, int $port = 3306, ?string $socket = null, array $options = []): PDO
    {
        $dsn = empty($socket)
            ? "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4;connect_timeout=10"
            : "mysql:unix_socket={$socket};dbname={$db};charset=utf8mb4;connect_timeout=10";

        $defaultOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ];

        $finalOptions = array_replace($defaultOptions, $options);

        // PHP 8.4+: Use namespaced constant to avoid deprecation warning
        // @phpstan-ignore-next-line
        $initKey = defined('Pdo\\Mysql::ATTR_INIT_COMMAND')
            ? \Pdo\Mysql::ATTR_INIT_COMMAND // @phpstan-ignore-line
            : (defined('PDO::MYSQL_ATTR_INIT_COMMAND') ? PDO::MYSQL_ATTR_INIT_COMMAND : null);

        if ($initKey && !isset($finalOptions[$initKey])) {
            $finalOptions[$initKey] = "SET NAMES utf8mb4";
        }

        try {
            $pdo = new PDO($dsn, $user, $pass, $finalOptions);
            self::harmonizeCollation($pdo);
            return $pdo;
        } catch (PDOException $e) {
            throw new PDOException("ConnectionManager::createConnection failed: " . $e->getMessage(), (int) $e->getCode());
        }
    }

    private static function createPgPdo(): PDO
    {
        $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s;sslmode=require", getenv('PGHOST'), getenv('PGPORT'), getenv('PGDATABASE'));
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ];

        try {
            $pdo = new PDO($dsn, getenv('PGUSER'), getenv('PGPASSWORD'), $options);
            $pdo->exec("SET TIME ZONE 'UTC'");
            return $pdo;
        } catch (PDOException $e) {
            throw new PDOException("PostgreSQL connection failed: " . $e->getMessage(), (int) $e->getCode());
        }
    }

    private static function createMySqlPdo($isDev, callable $logDiag): PDO
    {
        $env = self::getEnvConfig($isDev);

        $host = $env['host'];
        $db = $env['name'];
        $user = $env['user'];
        $pass = $env['pass'];
        $port = $env['port'];
        $socket = $env['socket'];

        $dsn = empty($socket)
            ? "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4;connect_timeout=10"
            : "mysql:unix_socket={$socket};dbname={$db};charset=utf8mb4;connect_timeout=10";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,
        ];

        // PHP 8.4+: Use namespaced constant to avoid deprecation warning
        // @phpstan-ignore-next-line
        $initKey = defined('Pdo\\Mysql::ATTR_INIT_COMMAND')
            ? \Pdo\Mysql::ATTR_INIT_COMMAND // @phpstan-ignore-line
            : (defined('PDO::MYSQL_ATTR_INIT_COMMAND') ? PDO::MYSQL_ATTR_INIT_COMMAND : null);

        if ($initKey) {
            $options[$initKey] = "SET NAMES utf8mb4";
        }

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
            self::harmonizeCollation($pdo);
            return $pdo;
        } catch (PDOException $e) {
            $logDiag('PDO connection failed: ' . $e->getMessage() . ' to DSN: ' . $dsn . ' as user: ' . $user);
            throw new PDOException("MySQL connection failed: " . $e->getMessage() . " (Host: $host, User: $user, DB: $db, Port: $port)", (int) $e->getCode());
        }
    }

    private static function getEnvConfig($isDev): array
    {
        $get = function ($k) {
            return $_ENV[$k] ?? ($_SERVER[$k] ?? (getenv($k) ?: ''));
        };

        if ($isDev) {
            return [
                'host' => $get('WF_DB_LOCAL_HOST') ?: 'localhost',
                'name' => $get('WF_DB_LOCAL_NAME') ?: 'whimsicalfrog',
                'user' => $get('WF_DB_LOCAL_USER') ?: 'root',
                'pass' => $get('WF_DB_LOCAL_PASS') ?: '',
                'port' => $get('WF_DB_LOCAL_PORT') ?: 3306,
                'socket' => $get('WF_DB_LOCAL_SOCKET') ?: '',
            ];
        }
        return [
            'host' => $get('WF_DB_LIVE_HOST') ?: 'db5017975223.hosting-data.io',
            'name' => $get('WF_DB_LIVE_NAME') ?: 'dbs14295502',
            'user' => $get('WF_DB_LIVE_USER') ?: 'dbu2826619',
            'pass' => $get('WF_DB_LIVE_PASS') ?: 'Ruok2drvacar?',
            'port' => $get('WF_DB_LIVE_PORT') ?: 3306,
            'socket' => $get('WF_DB_LIVE_SOCKET') ?: '',
        ];
    }

    private static function harmonizeCollation(PDO $pdo): void
    {
        try {
            $row = $pdo->query("SELECT @@collation_connection AS coll")->fetch();
            $target = $row['coll'] ?? 'utf8mb4_0900_ai_ci';
            $pdo->exec("SET NAMES utf8mb4 COLLATE " . $target);
            $pdo->exec("SET collation_connection = " . $target);
        } catch (Throwable $e) {
        }
    }
}
