<?php
require_once __DIR__ . '/../../api/config.php';

try {
    $pdo = Database::getInstance();

    $row = Database::queryOne('SELECT COUNT(*) AS c FROM dashboard_sections');
    $count = (int)($row['c'] ?? 0);
    echo "dashboard_sections rows: {$count}\n";

    $rows = Database::queryAll('SELECT section_key, display_order, is_active, width_class FROM dashboard_sections ORDER BY display_order');
    if (!$rows) {
        echo "No rows found.\n";
        exit(0);
    }
    foreach ($rows as $r) {
        echo sprintf("%2d | %-18s | active=%d | %s\n",
            (int)($r['display_order'] ?? 0),
            (string)$r['section_key'],
            (int)($r['is_active'] ?? 0),
            (string)($r['width_class'] ?? '')
        );
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
