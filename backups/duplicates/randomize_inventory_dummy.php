<?php
/**
 * CLI Utility: Randomize inventory for dummy data
 * Location: scripts/dev/randomize_inventory_dummy.php
 *
 * Purpose: Quickly seed believable stock numbers across sizes (and genders if provided)
 * so the UI can be validated end-to-end without real data.
 *
 * Usage examples:
 *   php scripts/dev/randomize_inventory_dummy.php --sku=WF-TEE-001 --genders="Girls,Men" --min=0 --max=25
 *   php scripts/dev/randomize_inventory_dummy.php --all --min=1 --max=30
 *   php scripts/dev/randomize_inventory_dummy.php --sku=WF-TUM-100 --min=5 --max=50 --create-missing
 *
 * Flags:
 *   --sku=SKU              Randomize only this SKU (mutually exclusive with --all)
 *   --all                  Randomize all items
 *   --genders="A,B"       Optional. If provided, will ensure gendered rows exist and randomize per gender
 *   --min=INT             Optional. Minimum stock (default 0)
 *   --max=INT             Optional. Maximum stock (default 20)
 *   --create-missing      Optional. Create missing size rows for genders when none exist but unisex sizes do
 *   --dry-run             Optional. Show intended changes without writing
 */

require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../includes/functions.php';

function argval($key, $default = null)
{
    $long = "--{$key}=";
    foreach ($GLOBALS['argv'] as $arg) {
        if (strpos($arg, $long) === 0) return substr($arg, strlen($long));
        if ($arg === "--{$key}") return true; // boolean flags
    }
    return $default;
}

try {
    Database::getInstance();

    $sku = (string)argval('sku', '');
    $all = (bool)argval('all', false);
    $gendersStr = (string)argval('genders', '');
    $min = (int)max(0, (int)argval('min', 0));
    $max = (int)max($min, (int)argval('max', 20));
    $createMissing = (bool)argval('create-missing', false);
    $dryRun = (bool)argval('dry-run', false);

    if (!$all && $sku === '') {
        throw new Exception('Provide --sku= or --all');
    }

    $genders = array_values(array_filter(array_map('trim', explode(',', $gendersStr)), fn($g)=>$g!==''));

    $skus = [];
    if ($all) {
        $rows = Database::queryAll('SELECT sku FROM items');
        $skus = array_map(fn($r)=>$r['sku'], $rows);
    } else {
        $exists = Database::queryOne('SELECT sku FROM items WHERE sku = ?', [$sku]);
        if (!$exists) throw new Exception("Item not found: {$sku}");
        $skus = [$sku];
    }

    $totalUpdates = 0; $totalCreates = 0; $totalZeroed = 0; $totalColorsSynced = 0; $totalItemsSynced = 0;

    foreach ($skus as $oneSku) {
        // If genders provided and create-missing, ensure gender size rows exist using unisex templates
        if (!empty($genders) && $createMissing) {
            $unisexSizes = Database::queryAll(
                'SELECT id, item_sku, color_id, size_name, size_code, price_adjustment, display_order
                 FROM item_sizes WHERE item_sku = ? AND gender IS NULL AND is_active = 1',
                [$oneSku]
            );
            foreach ($unisexSizes as $row) {
                foreach ($genders as $g) {
                    $exists = Database::queryOne(
                        'SELECT id FROM item_sizes WHERE item_sku = ? AND size_code = ? AND size_name = ? AND gender = ? AND ' .
                        (is_null($row['color_id']) ? 'color_id IS NULL' : 'color_id = ?') . ' LIMIT 1',
                        is_null($row['color_id'])
                            ? [$oneSku, $row['size_code'], $row['size_name'], $g]
                            : [$oneSku, $row['size_code'], $row['size_name'], $g, (int)$row['color_id']]
                    );
                    if (!$exists) {
                        if (!$dryRun) {
                            Database::execute(
                                'INSERT INTO item_sizes (item_sku, color_id, size_name, size_code, stock_level, price_adjustment, display_order, is_active, gender)
                                 VALUES (?, ?, ?, ?, 0, ?, ?, 1, ?)',
                                [$oneSku, $row['color_id'], $row['size_name'], $row['size_code'], $row['price_adjustment'], $row['display_order'], $g]
                            );
                        }
                        $totalCreates++;
                        echo "+ created gender size row sku={$oneSku} size={$row['size_code']} gender={$g} color_id=" . ($row['color_id']??'NULL') . "\n";
                    }
                }
            }
        }

        // Pick scope of sizes to randomize: if genders provided, target both gendered and unisex (fallback display), else all
        $sizes = Database::queryAll(
            'SELECT id, item_sku, color_id, size_name, size_code, stock_level, gender FROM item_sizes WHERE item_sku = ? AND is_active = 1',
            [$oneSku]
        );

        foreach ($sizes as $sRow) {
            // If specific genders provided, prefer randomizing gendered rows; leave unisex unless no gender rows exist
            if (!empty($genders)) {
                $hasAnyGenderRows = Database::queryOne('SELECT id FROM item_sizes WHERE item_sku = ? AND gender IS NOT NULL LIMIT 1', [$oneSku]);
                if ($hasAnyGenderRows) {
                    if ($sRow['gender'] === null) {
                        // Keep unisex as-is when gender rows exist
                        continue;
                    }
                    if (!in_array($sRow['gender'], $genders, true)) {
                        // Skip genders not requested
                        continue;
                    }
                }
            }

            $newStock = random_int($min, $max);
            if (!$dryRun) {
                Database::execute('UPDATE item_sizes SET stock_level = ? WHERE id = ?', [$newStock, (int)$sRow['id']]);
            }
            $totalUpdates++;
            echo "~ updated id={$sRow['id']} sku={$oneSku} size={$sRow['size_code']} gender=" . ($sRow['gender']??'NULL') . " => stock={$newStock}\n";
        }

        // Sync color totals and item totals
        if (!$dryRun) {
            Database::execute(
                'UPDATE item_colors c SET c.stock_level = (
                    SELECT COALESCE(SUM(s.stock_level), 0)
                    FROM item_sizes s
                    WHERE s.item_sku = c.item_sku AND s.color_id = c.id AND s.is_active = 1
                ) WHERE c.item_sku = ?'
                , [$oneSku]
            );
            $totalColorsSynced++;

            Database::execute(
                'UPDATE items i SET i.stockLevel = (
                    SELECT COALESCE(SUM(s.stock_level), 0)
                    FROM item_sizes s
                    WHERE s.item_sku = i.sku AND s.is_active = 1
                ) WHERE i.sku = ?'
                , [$oneSku]
            );
            $totalItemsSynced++;
        }
    }

    echo "Done. Updates={$totalUpdates}, Creates={$totalCreates}, ColorsSynced={$totalColorsSynced}, ItemsSynced={$totalItemsSynced}\n";
    if ($dryRun) echo "(dry run)\n";
    exit(0);
} catch (Exception $e) {
    fwrite(STDERR, '[randomize_inventory_dummy] Error: ' . $e->getMessage() . "\n");
    exit(1);
}
