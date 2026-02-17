<?php
/**
 * One-shot maintenance: sync items.cost_price / items.retail_price from breakdown tables.
 *
 * Usage:
 *   php scripts/maintenance/sync_item_prices_from_factors.php           # dry-run
 *   php scripts/maintenance/sync_item_prices_from_factors.php --apply   # apply updates
 *
 * Notes:
 * - Only SKUs with at least 1 factor row are synced (won't overwrite manual-only items).
 * - Writes a JSON report under /.local/state/.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/api/config.php';
require_once $root . '/includes/item_price_sync.php';

$apply = in_array('--apply', $argv, true);
$dryRun = !$apply;

if ($dryRun) {
    fwrite(STDOUT, "Running in dry-run mode. Pass --apply to write changes.\n");
} else {
    fwrite(STDOUT, "Running with --apply. Prices will be updated.\n");
}

@mkdir($root . '/.local/state', 0755, true);
$reportPath = $root . '/.local/state/sync_item_prices_from_factors_report_' . date('Ymd_His') . '.json';

$report = [
    'ran_at_utc' => gmdate('c'),
    'apply' => $apply,
    'cost' => ['scanned' => 0, 'would_update' => 0, 'updated' => 0, 'examples' => []],
    'retail' => ['scanned' => 0, 'would_update' => 0, 'updated' => 0, 'examples' => []],
];

function wf_round2(float $n): float
{
    return (float) number_format($n, 2, '.', '');
}

/**
 * @param 'cost'|'retail' $kind
 */
function wf_collect_example(array &$bucket, string $sku, float $stored, float $total): void
{
    if (count($bucket) >= 25) return;
    $bucket[] = [
        'sku' => $sku,
        'stored' => $stored,
        'breakdown_total' => $total,
        'delta' => wf_round2($total - $stored),
    ];
}

try {
    // --- Retail ---
    $retailSkus = Database::queryAll("SELECT DISTINCT sku FROM price_factors ORDER BY sku");
    foreach ($retailSkus as $row) {
        $sku = (string)($row['sku'] ?? '');
        if ($sku === '') continue;
        $report['retail']['scanned']++;

        $item = Database::queryOne("SELECT retail_price FROM items WHERE sku = ? LIMIT 1", [$sku]);
        if (!$item) continue;

        $stored = wf_round2((float)($item['retail_price'] ?? 0));
        $sumRow = Database::queryOne("SELECT COALESCE(SUM(amount), 0) AS total FROM price_factors WHERE sku = ?", [$sku]);
        $total = wf_round2((float)($sumRow['total'] ?? 0));

        if (abs($stored - $total) <= 0.001) continue;

        $report['retail']['would_update']++;
        wf_collect_example($report['retail']['examples'], $sku, $stored, $total);

        if ($apply) {
            wf_sync_item_retail_price_from_factors($sku);
            $report['retail']['updated']++;
        }
    }

    // --- Cost ---
    $costSkus = Database::queryAll("SELECT DISTINCT sku FROM cost_factors ORDER BY sku");
    foreach ($costSkus as $row) {
        $sku = (string)($row['sku'] ?? '');
        if ($sku === '') continue;
        $report['cost']['scanned']++;

        $item = Database::queryOne("SELECT cost_price FROM items WHERE sku = ? LIMIT 1", [$sku]);
        if (!$item) continue;

        $stored = wf_round2((float)($item['cost_price'] ?? 0));
        $sumRow = Database::queryOne("SELECT COALESCE(SUM(cost), 0) AS total FROM cost_factors WHERE sku = ?", [$sku]);
        $total = wf_round2((float)($sumRow['total'] ?? 0));

        if (abs($stored - $total) <= 0.001) continue;

        $report['cost']['would_update']++;
        wf_collect_example($report['cost']['examples'], $sku, $stored, $total);

        if ($apply) {
            wf_sync_item_cost_price_from_factors($sku);
            $report['cost']['updated']++;
        }
    }

    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    fwrite(STDOUT, "Report written: {$reportPath}\n");
    fwrite(STDOUT, "Retail: scanned={$report['retail']['scanned']} would_update={$report['retail']['would_update']} updated={$report['retail']['updated']}\n");
    fwrite(STDOUT, "Cost:   scanned={$report['cost']['scanned']} would_update={$report['cost']['would_update']} updated={$report['cost']['updated']}\n");
} catch (Throwable $e) {
    file_put_contents($reportPath, json_encode(['error' => $e->getMessage(), 'report' => $report], JSON_PRETTY_PRINT) . "\n");
    fwrite(STDERR, "Failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Partial report written: {$reportPath}\n");
    exit(1);
}

