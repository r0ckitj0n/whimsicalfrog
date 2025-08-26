<?php
// scripts/dev/seed-dashboard-sections.php
// Restores/ensures a comprehensive set of dashboard sections.
// Safe to re-run: upserts by section_key and preserves custom titles/descriptions if present.

require_once __DIR__ . '/../../api/config.php';

try {
    $pdo = Database::getInstance();

    // Ensure table exists
    $pdo->exec('CREATE TABLE IF NOT EXISTS dashboard_sections (
        section_key VARCHAR(64) NOT NULL,
        display_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        show_title TINYINT(1) NOT NULL DEFAULT 1,
        show_description TINYINT(1) NOT NULL DEFAULT 1,
        custom_title VARCHAR(255) NULL,
        custom_description VARCHAR(512) NULL,
        width_class VARCHAR(32) NULL,
        PRIMARY KEY (section_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    // Define desired sections and defaults
    $desired = [
        ['section_key' => 'metrics',           'display_order' => 1, 'is_active' => 1, 'show_title' => 1, 'show_description' => 1, 'custom_title' => null, 'custom_description' => null, 'width_class' => 'full-width'],
        ['section_key' => 'recent_orders',     'display_order' => 2, 'is_active' => 1, 'show_title' => 1, 'show_description' => 1, 'custom_title' => null, 'custom_description' => null, 'width_class' => 'half-width'],
        ['section_key' => 'low_stock',         'display_order' => 3, 'is_active' => 1, 'show_title' => 1, 'show_description' => 1, 'custom_title' => null, 'custom_description' => null, 'width_class' => 'half-width'],
        ['section_key' => 'inventory_summary', 'display_order' => 4, 'is_active' => 1, 'show_title' => 1, 'show_description' => 1, 'custom_title' => null, 'custom_description' => null, 'width_class' => 'half-width'],
        ['section_key' => 'customer_summary',  'display_order' => 5, 'is_active' => 1, 'show_title' => 1, 'show_description' => 1, 'custom_title' => null, 'custom_description' => null, 'width_class' => 'half-width'],
        ['section_key' => 'marketing_tools',   'display_order' => 6, 'is_active' => 1, 'show_title' => 1, 'show_description' => 1, 'custom_title' => null, 'custom_description' => null, 'width_class' => 'half-width'],
        ['section_key' => 'order_fulfillment', 'display_order' => 7, 'is_active' => 1, 'show_title' => 1, 'show_description' => 1, 'custom_title' => null, 'custom_description' => null, 'width_class' => 'full-width'],
        ['section_key' => 'reports_summary',   'display_order' => 8, 'is_active' => 1, 'show_title' => 1, 'show_description' => 1, 'custom_title' => null, 'custom_description' => null, 'width_class' => 'half-width'],
    ];

    // Load existing sections keyed by section_key
    $existing = [];
    foreach ($pdo->query('SELECT * FROM dashboard_sections') as $row) {
        $existing[$row['section_key']] = $row;
    }

    // Prepare upsert statement similar to api/dashboard_sections.php
    $sql = 'INSERT INTO dashboard_sections 
            (section_key, display_order, is_active, show_title, show_description, custom_title, custom_description, width_class)
            VALUES (:section_key, :display_order, :is_active, :show_title, :show_description, :custom_title, :custom_description, :width_class)
            ON DUPLICATE KEY UPDATE 
              is_active = VALUES(is_active),
              display_order = VALUES(display_order),
              width_class = VALUES(width_class),
              show_title = VALUES(show_title),
              show_description = VALUES(show_description)';
    $stmt = $pdo->prepare($sql);

    $inserted = 0; $updated = 0; $skipped = 0;
    foreach ($desired as $sec) {
        // Preserve any custom title/description if they already exist
        if (isset($existing[$sec['section_key']])) {
            $row = $existing[$sec['section_key']];
            if (!empty($row['custom_title'])) { $sec['custom_title'] = $row['custom_title']; }
            if (!empty($row['custom_description'])) { $sec['custom_description'] = $row['custom_description']; }
        }

        $ok = $stmt->execute([
            ':section_key' => $sec['section_key'],
            ':display_order' => $sec['display_order'],
            ':is_active' => $sec['is_active'],
            ':show_title' => $sec['show_title'],
            ':show_description' => $sec['show_description'],
            ':custom_title' => $sec['custom_title'],
            ':custom_description' => $sec['custom_description'],
            ':width_class' => $sec['width_class'],
        ]);
        if ($ok) {
            if (isset($existing[$sec['section_key']])) { $updated++; } else { $inserted++; }
        } else {
            $skipped++;
        }
    }

    // Normalize display_order to be contiguous starting at 1
    $rows = $pdo->query('SELECT section_key FROM dashboard_sections ORDER BY display_order ASC')->fetchAll(PDO::FETCH_COLUMN);
    $norm = $pdo->prepare('UPDATE dashboard_sections SET display_order = ? WHERE section_key = ?');
    foreach ($rows as $i => $key) {
        $norm->execute([$i + 1, $key]);
    }

    $count = (int)$pdo->query('SELECT COUNT(*) FROM dashboard_sections')->fetchColumn();
    echo "Dashboard sections seeded. Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}. Total rows: {$count}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error seeding dashboard sections: ' . $e->getMessage() . "\n");
    exit(1);
}
