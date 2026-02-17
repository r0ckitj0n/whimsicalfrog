<?php
/**
 * CLI runner for the SanMar importer (idempotent).
 *
 * Usage:
 *   php scripts/import_sanmar_colors.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/importers/sanmar_colors_importer.php';

try {
    $stats = wf_import_sanmar_colors();
    echo "OK\n";
    echo "Extracted: " . (int)($stats['extracted_base_colors'] ?? 0) . " base color names\n";
    echo "Global colors: added=" . (int)($stats['global_colors']['added'] ?? 0) . " updated=" . (int)($stats['global_colors']['updated'] ?? 0) . " total_sm=" . (int)($stats['global_colors']['total_sm'] ?? 0) . "\n";
    echo "Template '" . (string)($stats['template']['name'] ?? WF_SANMAR_TEMPLATE_NAME) . "': id=" . (int)($stats['template']['id'] ?? 0) . " items_inserted=" . (int)($stats['template']['items_inserted'] ?? 0) . "\n";
    $skipped = array_merge($stats['skipped']['global_colors'] ?? [], $stats['skipped']['template_items'] ?? []);
    if (!empty($skipped)) {
        echo "Skipped (too long):\n";
        foreach (array_values(array_unique($skipped)) as $s) {
            echo " - {$s}\n";
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
