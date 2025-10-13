<?php
// One-time script to create MySQL indexes that speed up room modal and background queries
// Usage:
//   /scripts/dev/create_room_indexes.php?confirm=1
// Notes:
//   - Safe to run multiple times; it checks INFORMATION_SCHEMA before creating each index.
//   - Requires MySQL; PostgreSQL not supported by this script.

require_once dirname(__DIR__) . '/api/config.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['confirm'])) {
    echo "WhimsicalFrog: Create Room Indexes (dry-run)\n";
    echo "Add ?confirm=1 to execute changes.\n\n";
}

try {
    $pdo = Database::getInstance();
} catch (Throwable $e) {
    http_response_code(500);
    echo "ERROR: Database connection failed: " . $e->getMessage() . "\n";
    exit;
}

function index_exists(PDO $pdo, string $table, string $index): bool {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$table, $index]);
    return (bool)$stmt->fetchColumn();
}

function create_index_if_missing(PDO $pdo, string $table, string $index, string $createSql, bool $doWrite): array {
    $exists = index_exists($pdo, $table, $index);
    if ($exists) {
        return ['index' => $index, 'table' => $table, 'action' => 'skip', 'message' => 'exists'];
    }
    if ($doWrite) {
        $pdo->exec($createSql);
        return ['index' => $index, 'table' => $table, 'action' => 'create', 'message' => 'created'];
    }
    return ['index' => $index, 'table' => $table, 'action' => 'plan', 'message' => 'would create'];
}

$doWrite = isset($_GET['confirm']) && $_GET['confirm'] == '1';
$results = [];

$statements = [
    // room assignment lookups
    ['table' => 'room_category_assignments', 'index' => 'idx_rca_room_primary',
     'sql' => 'CREATE INDEX `idx_rca_room_primary` ON `room_category_assignments` (`room_number`, `is_primary`)'],

    // categories by name
    ['table' => 'categories', 'index' => 'idx_categories_name',
     'sql' => 'CREATE INDEX `idx_categories_name` ON `categories` (`name`)'],

    // items by category_id and category
    ['table' => 'items', 'index' => 'idx_items_category_id',
     'sql' => 'CREATE INDEX `idx_items_category_id` ON `items` (`category_id`)'],
    ['table' => 'items', 'index' => 'idx_items_category',
     'sql' => 'CREATE INDEX `idx_items_category` ON `items` (`category`)'],

    // items by SKU and item_images primary
    ['table' => 'items', 'index' => 'idx_items_sku',
     'sql' => 'CREATE INDEX `idx_items_sku` ON `items` (`sku`)'],
    ['table' => 'item_images', 'index' => 'idx_item_images_sku_primary',
     'sql' => 'CREATE INDEX `idx_item_images_sku_primary` ON `item_images` (`sku`, `is_primary`)'],

    // backgrounds by room_number + is_active
    ['table' => 'backgrounds', 'index' => 'idx_backgrounds_room_active',
     'sql' => 'CREATE INDEX `idx_backgrounds_room_active` ON `backgrounds` (`room_number`, `is_active`)'],
];

foreach ($statements as $stmt) {
    try {
        $res = create_index_if_missing($pdo, $stmt['table'], $stmt['index'], $stmt['sql'], $doWrite);
        $results[] = $res;
    } catch (Throwable $e) {
        $results[] = ['index' => $stmt['index'], 'table' => $stmt['table'], 'action' => $doWrite ? 'create' : 'plan', 'error' => $e->getMessage()];
    }
}

// Output report
$mode = $doWrite ? 'EXECUTE' : 'DRY-RUN';
$ts = date('c');
echo "WhimsicalFrog Room Index Creator\n";
echo "Mode: {$mode}\n";
echo "When: {$ts}\n\n";
foreach ($results as $r) {
    if (isset($r['error'])) {
        echo sprintf("[%s] %s on %s: ERROR: %s\n", $r['action'], $r['index'], $r['table'], $r['error']);
    } else {
        echo sprintf("[%s] %s on %s: %s\n", $r['action'], $r['index'], $r['table'], $r['message']);
    }
}

echo "\nDone.\n";
