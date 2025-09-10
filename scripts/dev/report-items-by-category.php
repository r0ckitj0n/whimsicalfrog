<?php
// scripts/dev/report-items-by-category.php
// Quick admin report: items per category and items with invalid/missing categories.

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../api/config.php';

try {
    Database::getInstance();

    // Fetch canonical categories
    $categories = Database::queryAll('SELECT id, name FROM categories ORDER BY id');
    $categoryNames = array_map(fn($c) => $c['name'], $categories);

    // Items per category
    $itemsPerCategory = Database::queryAll('SELECT category, COUNT(*) as count FROM items GROUP BY category ORDER BY count DESC, category');

    // Find invalid items (category not in categories table or NULL/empty)
    $placeholders = implode(',', array_fill(0, count($categoryNames), '?'));
    $params = $categoryNames;
    if ($placeholders === '') {
        // If no categories exist, everything is invalid
        $invalidItems = Database::queryAll('SELECT sku, name, category FROM items ORDER BY sku');
    } else {
        $invalidItems = Database::queryAll(
            "SELECT sku, name, category FROM items \n             WHERE category IS NULL OR category = '' OR category NOT IN ($placeholders)\n             ORDER BY sku",
            $params
        );
    }

    echo json_encode([
        'ok' => true,
        'categories' => $categories,
        'itemsPerCategory' => $itemsPerCategory,
        'invalidItems' => $invalidItems,
        'invalidCount' => count($invalidItems),
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
