<?php
/**
 * Dev Utility: Detect and auto-resolve duplicate size_code rows before adding unique index
 *
 * Duplicate key: (item_sku, color_id, size_code, gender)
 * Strategy:
 *  - For each dup group, choose a survivor row:
 *      * prefer is_active=1, then highest stock_level, then lowest id (deterministic)
 *  - Sum stock_level from the other rows into survivor
 *  - Keep survivor's price_adjustment, size_name, display_order, is_active
 *  - Delete the other rows
 *
 * Usage:
 *   php scripts/dev/fix_duplicate_size_codes.php [--dry-run]
 */

require_once __DIR__ . '/../../api/config.php';

$dryRun = in_array('--dry-run', $argv, true);

try {
    Database::getInstance();

    echo "Scanning for duplicate (item_sku, color_id, size_code, gender) rows...\n";

    $dups = Database::queryAll(
        "SELECT item_sku, color_id, size_code, gender, COUNT(*) as c
         FROM item_sizes
         GROUP BY item_sku, color_id, size_code, gender
         HAVING c > 1"
    );

    if (empty($dups)) {
        echo "No duplicates found.\n";
        exit(0);
    }

    echo "Found " . count($dups) . " duplicate groups. Processing...\n";

    $resolvedGroups = 0;
    $mergedRows = 0;

    foreach ($dups as $g) {
        $sku = $g['item_sku'];
        $colorId = $g['color_id'];
        $sizeCode = $g['size_code'];
        $gender = $g['gender'];

        $rows = Database::queryAll(
            "SELECT id, item_sku, color_id, size_name, size_code, gender, stock_level, price_adjustment, is_active, display_order
             FROM item_sizes
             WHERE item_sku = ? AND ((color_id IS NULL AND ? IS NULL) OR color_id = ?)
               AND size_code = ? AND ((gender IS NULL AND ? IS NULL) OR gender = ?)
             ORDER BY is_active DESC, stock_level DESC, id ASC",
            [$sku, $colorId, $colorId, $sizeCode, $gender, $gender]
        );

        if (count($rows) < 2) continue;

        $survivor = $rows[0];
        $others = array_slice($rows, 1);

        $sumAdd = 0;
        foreach ($others as $r) {
            $sumAdd += (int)($r['stock_level'] ?? 0);
        }

        echo sprintf(
            "Group sku=%s color_id=%s size_code=%s gender=%s -> survivor id=%d, merging %d rows (+%d stock)\n",
            $sku,
            ($colorId === null ? 'NULL' : (string)$colorId),
            (string)$sizeCode,
            ($gender === null ? 'NULL' : (string)$gender),
            (int)$survivor['id'],
            count($others),
            $sumAdd
        );

        if ($dryRun) {
            $mergedRows += count($others);
            $resolvedGroups++;
            continue;
        }

        // Merge stock into survivor
        $newStock = (int)$survivor['stock_level'] + (int)$sumAdd;
        Database::execute("UPDATE item_sizes SET stock_level = ? WHERE id = ?", [$newStock, (int)$survivor['id']]);

        // Delete others
        $idsToDelete = array_map(function($r){ return (int)$r['id']; }, $others);
        if (!empty($idsToDelete)) {
            $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
            Database::execute("DELETE FROM item_sizes WHERE id IN ($placeholders)", $idsToDelete);
        }

        $mergedRows += count($others);
        $resolvedGroups++;
    }

    echo sprintf("Done. Resolved %d groups, merged %d rows.\n", $resolvedGroups, $mergedRows);

    // Optional: prompt to sync color and item totals? Not here; rely on admin sync or subsequent ops.
    exit(0);

} catch (Exception $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
