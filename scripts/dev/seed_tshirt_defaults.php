<?php
/**
 * Seed default colors and sizes for all T-Shirt items.
 * Location: scripts/dev/seed_tshirt_defaults.php
 *
 * Usage:
 *   php scripts/dev/seed_tshirt_defaults.php --dry-run
 *   php scripts/dev/seed_tshirt_defaults.php --apply
 *   php scripts/dev/seed_tshirt_defaults.php --apply --sku=WF-TEE-001   # limit to one SKU
 *
 * Behavior:
 * - Detect T-shirt SKUs by (category LIKE '%shirt%') OR (name LIKE '%shirt%') OR (sku LIKE 'WF-TEE-%').
 * - Ensure default colors exist (Black/White) in item_colors with stock 10 (if missing).
 * - Ensure default unisex sizes exist (S, M, L, XL) in item_sizes with stock 10 (if missing), color_id = NULL.
 * - Create gender-specific duplicates for Women and Men with same stock for each size (if missing).
 * - Recompute color stock from sizes and recompute item total stock.
 *
 * Notes:
 * - Safe to run multiple times; it skips existing rows.
 */

require_once __DIR__ . '/../../api/config.php';

function argval($key, $default = null) {
    $long = "--{$key}=";
    foreach ($GLOBALS['argv'] as $arg) {
        if (strpos($arg, $long) === 0) return substr($arg, strlen($long));
        if ($arg === "--{$key}") return true; // boolean flag
    }
    return $default;
}

try {
    $pdo = Database::getInstance();

    $dryRun = !((bool)argval('apply', false));
    $onlySku = trim((string)argval('sku', ''));

    // Identify T-shirt items
    if ($onlySku !== '') {
        $items = Database::queryAll('SELECT sku, name, category FROM items WHERE sku = ?', [$onlySku]);
    } else {
        $items = Database::queryAll(
            "SELECT sku, name, category
             FROM items
             WHERE (
               LOWER(COALESCE(category, '')) LIKE '%shirt%'
               OR LOWER(COALESCE(name, '')) LIKE '%shirt%'
               OR sku LIKE 'WF-TEE-%'
             )",
            []
        );
    }

    if (!$items) {
        echo "No T-shirt items found" . ($onlySku ? " for {$onlySku}" : '') . ".\n";
        exit(0);
    }

    $defaultColors = [
        ['color_name' => 'Black', 'color_code' => '#000000'],
        ['color_name' => 'White', 'color_code' => '#FFFFFF'],
    ];
    $defaultSizes = [
        ['size_name' => 'Small',  'size_code' => 'S'],
        ['size_name' => 'Medium', 'size_code' => 'M'],
        ['size_name' => 'Large',  'size_code' => 'L'],
        ['size_name' => 'X-Large','size_code' => 'XL'],
    ];
    $defaultStock = 10;
    $genders = ['Women','Men'];

    $totalInserts = 0; $totalSkips = 0; $totalUpdates = 0;

    foreach ($items as $it) {
        $sku = $it['sku'];
        echo "\n== Seeding {$sku} ({$it['name']}) ==\n";

        // COLORS
        foreach ($defaultColors as $c) {
            $exists = Database::queryOne(
                'SELECT id FROM item_colors WHERE item_sku = ? AND color_name = ? AND is_active = 1',
                [$sku, $c['color_name']]
            );
            if ($exists) {
                echo "= color exists: {$c['color_name']}\n"; $totalSkips++; continue;
            }
            if ($dryRun) {
                echo "+ add color: {$c['color_name']} ({$c['color_code']}) stock={$defaultStock}\n"; $totalInserts++;
            } else {
                Database::execute(
                    'INSERT INTO item_colors (item_sku, color_name, color_code, image_path, stock_level, display_order, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, 1)',
                    [$sku, $c['color_name'], $c['color_code'], null, $defaultStock, 0]
                );
                $totalInserts++;
            }
        }

        // UNISEX SIZES (color_id NULL)
        foreach ($defaultSizes as $s) {
            $exists = Database::queryOne(
                'SELECT id FROM item_sizes WHERE item_sku = ? AND color_id IS NULL AND size_code = ? AND is_active = 1 AND gender IS NULL',
                [$sku, $s['size_code']]
            );
            if ($exists) { echo "= size exists (unisex): {$s['size_code']}\n"; $totalSkips++; continue; }
            if ($dryRun) {
                echo "+ add unisex size: {$s['size_code']} stock={$defaultStock}\n"; $totalInserts++;
            } else {
                Database::execute(
                    'INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active, gender)
                     VALUES (?, NULL, ?, ?, ?, 0, 0, 1, NULL)',
                    [$sku, $s['size_name'], $s['size_code'], $defaultStock]
                );
                $totalInserts++;
            }
        }

        // Ensure sizes exist for each color as well (copy defaults per color)
        $colorsForSku = Database::queryAll('SELECT id, color_name FROM item_colors WHERE item_sku = ? AND is_active = 1', [$sku]);
        foreach ($colorsForSku as $col) {
            $colorId = (int)$col['id'];
            foreach ($defaultSizes as $s) {
                $exists = Database::queryOne(
                    'SELECT id FROM item_sizes WHERE item_sku = ? AND color_id = ? AND size_code = ? AND is_active = 1 AND gender IS NULL',
                    [$sku, $colorId, $s['size_code']]
                );
                if ($exists) { echo "= size exists (unisex per-color {$col['color_name']}): {$s['size_code']}\n"; $totalSkips++; continue; }
                if ($dryRun) {
                    echo "+ add unisex size for color {$col['color_name']}: {$s['size_code']} stock={$defaultStock}\n"; $totalInserts++;
                } else {
                    Database::execute(
                        'INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active, gender)
                         VALUES (?, ?, ?, ?, ?, 0, 0, 1, NULL)',
                        [$sku, $colorId, $s['size_name'], $s['size_code'], $defaultStock]
                    );
                    $totalInserts++;
                }
            }
        }

        // GENDER-SPECIFIC duplicates (copy stock from unisex rows, for Women & Men)
        // Duplicate gender sizes from all unisex rows (null color and per-color)
        $unisex = Database::queryAll(
            'SELECT id, color_id, size_name, size_code, stock_level, price_adjustment, display_order
             FROM item_sizes WHERE item_sku = ? AND gender IS NULL AND is_active = 1',
            [$sku]
        );
        foreach ($unisex as $row) {
            foreach ($genders as $g) {
                $exists = Database::queryOne(
                    'SELECT id FROM item_sizes WHERE item_sku = ? AND size_code = ? AND size_name = ? AND is_active = 1 AND gender = ? AND ' .
                    (is_null($row['color_id']) ? 'color_id IS NULL' : 'color_id = ?') . ' LIMIT 1',
                    is_null($row['color_id'])
                        ? [$sku, $row['size_code'], $row['size_name'], $g]
                        : [$sku, $row['size_code'], $row['size_name'], $g, (int)$row['color_id']]
                );
                if ($exists) { echo "= size exists ({$g}): {$row['size_code']}\n"; $totalSkips++; continue; }
                if ($dryRun) {
                    echo "+ add {$g} size: {$row['size_code']} stock={$row['stock_level']}\n"; $totalInserts++;
                } else {
                    Database::execute(
                        'INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active, gender)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
                        [$sku, $row['color_id'], $row['size_name'], $row['size_code'], (int)$row['stock_level'], $row['price_adjustment'], $row['display_order'], $g]
                    );
                    $totalInserts++;
                }
            }
        }

        if (!$dryRun) {
            // Sync color stock from sizes
            Database::execute(
                'UPDATE item_colors c SET c.stock_level = (
                    SELECT COALESCE(SUM(s.stock_level), 0)
                    FROM item_sizes s
                    WHERE s.item_sku = c.item_sku AND s.color_id = c.id AND s.is_active = 1
                ) WHERE c.item_sku = ?'
                , [$sku]
            );
            // Sync total item stock from sizes
            Database::execute(
                'UPDATE items i SET i.stockLevel = (
                    SELECT COALESCE(SUM(s.stock_level), 0)
                    FROM item_sizes s
                    WHERE s.item_sku = i.sku AND s.is_active = 1
                ) WHERE i.sku = ?'
                , [$sku]
            );
        }
    }

    echo "\nSUMMARY: inserts={$totalInserts}, skips={$totalSkips}, updates={$totalUpdates}.\n";
    if ($dryRun) echo "(Dry run only. Re-run with --apply to write changes.)\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, '[seed_tshirt_defaults] Error: ' . $e->getMessage() . "\n");
    exit(1);
}
