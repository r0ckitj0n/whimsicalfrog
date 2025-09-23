<?php

// Check shipping rates in database
require_once __DIR__ . '/api/config.php';

try {
    $pdo = Database::getInstance();
    $result = Database::queryAll(
        "SELECT setting_key, setting_value, category FROM business_settings WHERE setting_key LIKE ?",
        ['%shipping%']
    );

    echo "Current shipping settings:\n";
    echo json_encode($result, JSON_PRETTY_PRINT);
    echo "\n\n";

    // Also check if there are any settings with category ecommerce
    $ecommerceResult = Database::queryAll(
        "SELECT setting_key, setting_value, category FROM business_settings WHERE category = ?",
        ['ecommerce']
    );

    echo "Ecommerce category settings:\n";
    echo json_encode($ecommerceResult, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
