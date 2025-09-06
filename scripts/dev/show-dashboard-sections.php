<?php
require_once __DIR__ . '/../../api/config.php';

try {
    $pdo = Database::getInstance();

    $count = (int)$pdo->query('SELECT COUNT(*) FROM dashboard_sections')->fetchColumn();
    echo "dashboard_sections rows: {$count}\n";

    $stmt = $pdo->query('SELECT section_key, display_order, is_active, width_class FROM dashboard_sections ORDER BY display_order');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
