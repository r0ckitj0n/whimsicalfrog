<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/database/ConnectionManager.php';

try {
    $pdo = Database::getInstance();
    $tablesStmt = $pdo->query("SHOW TABLES");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    $schema = [];
    foreach ($tables as $table) {
        $colsStmt = $pdo->query("DESCRIBE `$table`");
        $cols = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
        $schema[$table] = $cols;
    }

    echo json_encode($schema, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
