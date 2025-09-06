<?php
require __DIR__ . '/../../api/config.php';
$pdo = Database::getInstance();
header('Content-Type: application/json');
$out = [];
try {
    $cnt = $pdo->query('SELECT COUNT(*) AS c FROM items')->fetch(PDO::FETCH_ASSOC)['c'] ?? 0;
    $out['item_count'] = (int)$cnt;
} catch (Throwable $e) {
    $out['item_count_error'] = $e->getMessage();
}
try {
    $cols = $pdo->query('DESCRIBE items')->fetchAll(PDO::FETCH_COLUMN);
    $out['item_columns'] = $cols;
} catch (Throwable $e) {
    $out['item_columns_error'] = $e->getMessage();
}
try {
    $rows = $pdo->query('SELECT sku, name, category, category_id, retailPrice, stockLevel FROM items LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
    $out['sample_rows'] = $rows;
} catch (Throwable $e) {
    $out['sample_rows_error'] = $e->getMessage();
}
try {
    $distinct = $pdo->query('SELECT category, COUNT(*) as n FROM items GROUP BY category ORDER BY n DESC')->fetchAll(PDO::FETCH_ASSOC);
    $out['distinct_category_counts'] = $distinct;
} catch (Throwable $e) {
    $out['distinct_category_counts_error'] = $e->getMessage();
}
echo json_encode($out, JSON_PRETTY_PRINT), "\n";
