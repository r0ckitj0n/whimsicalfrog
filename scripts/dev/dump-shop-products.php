<?php
require __DIR__ . '/../../api/config.php';
$categories = [];
require __DIR__ . '/../../includes/shop_data_loader.php';
header('Content-Type: application/json');
echo json_encode($categories, JSON_PRETTY_PRINT), "\n";
