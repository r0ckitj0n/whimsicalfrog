<?php
require_once __DIR__ . '/../api/config.php';
$pdo = new PDO($dsn, $user, $pass, $options);
$check = $pdo->query("SHOW COLUMNS FROM orders LIKE 'fulfillmentNotes'")->fetch();
if (!$check) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN fulfillmentNotes TEXT NULL");
    echo "Added fulfillmentNotes column to orders table.\n";
} else {
    echo "fulfillmentNotes column already exists.\n";
} 