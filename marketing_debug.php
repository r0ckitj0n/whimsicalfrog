<?php
require_once __DIR__ . '/api/config.php';
header('Content-Type: text/plain');
try {
  $pdo = new PDO($dsn, $user, $pass, $options);
  echo "Connected to DB\n";
  $countItems = $pdo->query("SELECT COUNT(*) FROM order_items")->fetchColumn();
  echo "order_items rows: $countItems\n";
  $countOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
  echo "orders rows: $countOrders\n";
  $stmt = $pdo->query("SELECT COALESCE(SUM(oi.quantity), COUNT(*)) AS units FROM order_items oi JOIN orders o ON oi.orderId COLLATE utf8mb4_unicode_ci = o.id COLLATE utf8mb4_unicode_ci");
  $units = $stmt->fetchColumn();
  echo "units join: $units\n";
} catch (PDOException $e) {
  echo "Error: " . $e->getMessage();
}
?> 