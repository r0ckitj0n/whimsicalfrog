<?php

/**
 * Handles database query execution and transaction management
 */
class QueryExecutor
{
    /**
     * Execute a SELECT and return all rows
     */
    public static function queryAll(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a SELECT and return first row or null
     */
    public static function queryOne(PDO $pdo, string $sql, array $params = []): ?array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Execute an INSERT/UPDATE/DELETE and return affected rows
     */
    public static function execute(PDO $pdo, string $sql, array $params = []): int
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Transaction helpers
     */
    public static function beginTransaction(PDO $pdo): bool
    {
        return $pdo->beginTransaction();
    }

    public static function commit(PDO $pdo): bool
    {
        return $pdo->commit();
    }

    public static function rollBack(PDO $pdo): bool
    {
        return $pdo->rollBack();
    }

    /**
     * Get the last inserted ID
     */
    public static function lastInsertId(PDO $pdo): string
    {
        return $pdo->lastInsertId();
    }
}
