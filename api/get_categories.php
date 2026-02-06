<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $pdo = Database::getInstance();

    // Categories table is authoritative - no silent fallbacks
    $categories = [];

    // Query categories table (required)
    $rows = Database::queryAll("SELECT name FROM categories ORDER BY name");
    foreach ($rows as $row) {
        $name = $row['name'] ?? null;
        if ($name === null || $name === '') {
            continue;
        }
        $categories[strtolower($name)] = $name;
    }

    // Also include any categories present on items (ensures data integrity)
    $itemRows = Database::queryAll("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category <> '' ORDER BY category");
    foreach ($itemRows as $row) {
        $name = $row['category'] ?? null;
        if ($name === null || $name === '') {
            continue;
        }
        $categories[strtolower($name)] = $name;
    }

    // Return just the names in a stable order
    ksort($categories);
    $categories = array_values($categories);

    echo json_encode($categories);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch categories: ' . $e->getMessage()
    ]);
}
