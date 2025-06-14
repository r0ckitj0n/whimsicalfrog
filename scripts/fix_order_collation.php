<?php
require_once __DIR__ . '/../api/config.php';
$pdo = new PDO($dsn, $user, $pass, $options);
try {
    $sql = "ALTER TABLE order_items MODIFY orderId VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL";
    $pdo->exec($sql);
    echo "order_items.orderId collation updated to utf8mb4_unicode_ci.\n";
    $sql2 = "ALTER TABLE order_items MODIFY productId VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL";
    $pdo->exec($sql2);
    echo "order_items.productId collation updated to utf8mb4_unicode_ci.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} 