<?php
require __DIR__ . '/../../api/config.php';
$pdo = Database::getInstance();
header('Content-Type: application/json');
$out = [];
try {
    $out['by_name_count'] = (int)$pdo->query("SELECT COUNT(*) c FROM items i JOIN categories c ON i.category = c.name")->fetch(PDO::FETCH_ASSOC)['c'];
} catch (Throwable $e) { $out['by_name_error'] = $e->getMessage(); }
try {
    $out['name_samples'] = $pdo->query("SELECT DISTINCT i.category AS item_cat, c.name AS cat_name FROM items i LEFT JOIN categories c ON i.category = c.name ORDER BY item_cat IS NULL, item_cat LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $out['name_samples_error'] = $e->getMessage(); }
try {
    $out['by_id_error_expected'] = null;
    $pdo->query("SELECT COUNT(*) c FROM items i JOIN categories c ON i.category_id = c.id");
} catch (Throwable $e) { $out['by_id_error_expected'] = $e->getMessage(); }

echo json_encode($out, JSON_PRETTY_PRINT), "\n";
