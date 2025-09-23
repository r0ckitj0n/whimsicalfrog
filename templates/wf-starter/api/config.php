<?php

require_once __DIR__ . '/../includes/logger.php';

class Database
{
    private static ?\PDO $pdo = null;

    public static function getInstance(): \PDO
    {
        if (!self::$pdo) {
            $dsn = getenv('DB_DSN');
            if (!$dsn) {
                $host = getenv('DB_HOST') ?: '127.0.0.1';
                $name = getenv('DB_NAME') ?: 'wf_starter';
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
            }
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            $opts = [
              \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
              \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ];
            try {
                self::$pdo = new \PDO($dsn, $user, $pass, $opts);
            } catch (\Throwable $e) {
                Logger::exception($e, ['source' => 'db_connect']);
                http_response_code(500);
                die('Database connection failed');
            }
        }
        return self::$pdo;
    }

    public static function createConnection(string $host, string $db, string $user, string $pass, array $opts = []): \PDO
    {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $db);
        $opts = $opts + [
          \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
          \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ];
        return new \PDO($dsn, $user, $pass, $opts);
    }
}
