<?php

// scripts/dev/set-dashboard-card-widths.php
// Force specific dashboard cards to be full-width.
// Usage: php scripts/dev/set-dashboard-card-widths.php

require_once __DIR__ . '/../../includes/functions.php';

function ensure_table(PDO $db): void
{
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `dashboard_sections` (
  `section_key` varchar(64) NOT NULL,
  `display_order` int NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `show_title` tinyint(1) NOT NULL DEFAULT 1,
  `show_description` tinyint(1) NOT NULL DEFAULT 1,
  `custom_title` varchar(255) DEFAULT NULL,
  `custom_description` text DEFAULT NULL,
  `width_class` varchar(64) NOT NULL DEFAULT 'half-width',
  PRIMARY KEY (`section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    $db->exec($sql);
}

try {
    $db = Database::getInstance();
    ensure_table($db);

    $targets = [
        // section_key => display_order (fallback if inserting new)
        'order_fulfillment' => 1,
        'metrics' => 2,
    ];

    $db->beginTransaction();

    // Upsert width_class='full-width' and ensure active
    $stmt = $db->prepare(
        'INSERT INTO dashboard_sections (section_key, display_order, is_active, show_title, show_description, custom_title, custom_description, width_class)
         VALUES (:k, :o, 1, 1, 1, NULL, NULL, "full-width")
         ON DUPLICATE KEY UPDATE width_class=VALUES(width_class), is_active=VALUES(is_active)'
    );

    foreach ($targets as $key => $order) {
        $stmt->execute([':k' => $key, ':o' => $order]);
    }

    $db->commit();

    // Show current state for verification
    $rows = $db->query("SELECT section_key, display_order, is_active, width_class FROM dashboard_sections WHERE section_key IN ('order_fulfillment','metrics') ORDER BY display_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo "Updated sections to full-width:\n";
    foreach ($rows as $r) {
        echo sprintf("- %s | order=%d | active=%d | width=%s\n", $r['section_key'], $r['display_order'], $r['is_active'], $r['width_class']);
    }
    echo "Done.\n";
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        try {
            $db->rollBack();
        } catch (Throwable $__) {
        }
    }
    fwrite(STDERR, "Failed: " . $e->getMessage() . "\n");
    exit(1);
}
