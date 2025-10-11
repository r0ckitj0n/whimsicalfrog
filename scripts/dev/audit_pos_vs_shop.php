<?php
// scripts/dev/audit_pos_vs_shop.php
// Audits inventory consistency between POS/Shop data sources.
// Outputs CSV rows with issues to STDOUT.

// Usage: php scripts/dev/audit_pos_vs_shop.php > reports/audit_pos_vs_shop.csv

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../../api/config.php';

function csv_echo(array $row): void {
    static $out;
    if (!$out) { $out = fopen('php://output', 'w'); }
    fputcsv($out, $row);
}

try {
    $db = Database::getInstance();

    // Pull base items with status, retailPrice, and legacy stockLevel
    $sql = "
        SELECT 
            i.sku,
            i.name,
            i.status,
            i.category,
            i.retailPrice,
            i.stockLevel AS legacyStock
        FROM items i
        ORDER BY i.sku
    ";
    $items = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    // Compute aggregated stock from item_sizes
    $sizes = $db->query("SELECT item_sku AS sku, COALESCE(SUM(stock_level),0) AS totalStock FROM item_sizes GROUP BY item_sku")
                ->fetchAll(PDO::FETCH_KEY_PAIR);

    // Compute primary image existence and path
    $imgStmt = $db->query("SELECT sku, image_path FROM item_images WHERE is_primary = 1");
    $primaryBySku = [];
    foreach ($imgStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $primaryBySku[$r['sku']] = $r['image_path'];
    }

    // Header
    csv_echo(['issue', 'sku', 'name', 'status', 'category', 'legacy_stock', 'total_stock', 'has_primary_image', 'primary_image_path']);

    $issues = 0;
    foreach ($items as $it) {
        $sku = $it['sku'];
        $status = strtolower((string)$it['status']);
        $legacy = is_numeric($it['legacyStock']) ? (int)$it['legacyStock'] : null;
        $total = isset($sizes[$sku]) ? (int)$sizes[$sku] : 0;
        $hasPrimary = array_key_exists($sku, $primaryBySku);
        $primaryPath = $hasPrimary ? (string)$primaryBySku[$sku] : '';

        // 1) Live items with totalStock = 0
        if ($status === 'live' && $total <= 0) {
            csv_echo(['LIVE_ZERO_STOCK', $sku, $it['name'], $it['status'], $it['category'], $legacy, $total, $hasPrimary ? 'yes' : 'no', $primaryPath]);
            $issues++;
        }
        
        // 2) Items missing primary image
        if (!$hasPrimary) {
            csv_echo(['MISSING_PRIMARY_IMAGE', $sku, $it['name'], $it['status'], $it['category'], $legacy, $total, 'no', '']);
            $issues++;
        }

        // 3) Legacy stock mismatch vs aggregated sizes
        if ($legacy !== null && $legacy !== $total) {
            csv_echo(['LEGACY_STOCK_MISMATCH', $sku, $it['name'], $it['status'], $it['category'], $legacy, $total, $hasPrimary ? 'yes' : 'no', $primaryPath]);
            $issues++;
        }
    }

    // Summary to STDERR so it doesn't pollute CSV
    file_put_contents('php://stderr', "Audit complete. Issues found: {$issues}\n");

    // Deprecation proposal (STDERR): recommend phasing out items.imageUrl and items.stockLevel
    $proposal = <<<TXT
DEPRECATION PROPOSAL:
- Replace all reads of items.imageUrl with primary image from item_images (is_primary DESC, sort_order ASC).
- Replace all reads of items.stockLevel with SUM(item_sizes.stock_level) and treat item_sizes as source of truth.
- Optional: create DB view view_items_with_primary_image_and_stock(sku, name, category, retailPrice, status, totalStock, imageUrl).
TXT;
    file_put_contents('php://stderr', $proposal);

} catch (Throwable $e) {
    // Emit a CSV error row and exit non-zero
    csv_echo(['ERROR', '', '', '', '', '', '', '', '']);
    file_put_contents('php://stderr', 'Audit failed: ' . $e->getMessage() . "\n");
    exit(1);
}
