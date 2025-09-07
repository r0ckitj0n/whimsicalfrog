<?php
require __DIR__ . '/../../api/config.php';
Database::getInstance();
header('Content-Type: application/json');
$out = [];
try {
    $row = Database::queryOne("SELECT COUNT(*) c FROM items i JOIN categories c ON i.category = c.name");
    $out['by_name_count'] = (int)($row['c'] ?? 0);
} catch (Throwable $e) { $out['by_name_error'] = $e->getMessage(); }
try {
    $out['name_samples'] = Database::queryAll("SELECT DISTINCT i.category AS item_cat, c.name AS cat_name FROM items i LEFT JOIN categories c ON i.category = c.name ORDER BY item_cat IS NULL, item_cat LIMIT 20");
} catch (Throwable $e) { $out['name_samples_error'] = $e->getMessage(); }
try {
    $out['by_id_error_expected'] = null;
    Database::queryOne("SELECT COUNT(*) c FROM items i JOIN categories c ON i.category_id = c.id");
} catch (Throwable $e) { $out['by_id_error_expected'] = $e->getMessage(); }

echo json_encode($out, JSON_PRETTY_PRINT), "\n";
