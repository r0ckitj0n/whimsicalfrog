<?php
require __DIR__ . '/../../api/config.php';
$categories = [];
require __DIR__ . '/../../includes/shop_data_loader.php';
$catCount = count($categories);
$totalProducts = 0;
$byCat = [];
foreach ($categories as $slug => $c) {
    $count = isset($c['products']) && is_array($c['products']) ? count($c['products']) : 0;
    $byCat[$slug] = $count;
    $totalProducts += $count;
}
header('Content-Type: application/json');
echo json_encode([
    'cats' => $catCount,
    'total_products' => $totalProducts,
    'by_category' => $byCat
], JSON_PRETTY_PRINT), "\n";
