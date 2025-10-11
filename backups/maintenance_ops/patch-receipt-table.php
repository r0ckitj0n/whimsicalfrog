<?php

// Patch receipt table alignment/styling safely and idempotently.
$root = dirname(__DIR__, 2);
$file = $root . '/receipt.php';
if (!is_file($file)) {
    fwrite(STDERR, "receipt.php not found at $file\n");
    exit(1);
}
$src = file_get_contents($file);
$orig = $src;
$changed = false;

// 1) Backup
$backup = $file . '.bak.' . date('Ymd-His');
@copy($file, $backup);

// 2) Ensure CSS for .receipt-table
if (strpos($src, '.receipt-table') === false) {
    $css = "\n<style>\n/* Receipt table alignment rules (patched) */\n.receipt-table{table-layout:fixed}\n.receipt-table th,.receipt-table td{padding:.5rem .75rem;vertical-align:top}\n.receipt-table th{text-align:left !important;font-weight:600}\n.receipt-table th:nth-child(3),.receipt-table td:nth-child(3){text-align:center}\n.receipt-table th:nth-child(4),.receipt-table td:nth-child(4){text-align:right}\n</style>\n";
    $p = strpos($src, '</style>');
    if ($p !== false) {
        $src = substr($src, 0, $p + 8) . "\n" . $css . substr($src, $p + 8);
    } else {
        $q = strpos($src, '?>');
        if ($q !== false) {
            $src = substr($src, 0, $q + 2) . "\n" . $css . substr($src, $q + 2);
        } else {
            $src = $css . $src;
        }
    }
    $changed = true;
}

// 3) Normalize items table (class + colgroup + monospace header)
$needsClass = (strpos($src, 'receipt-table') === false);
$needsColgroup = (strpos($src, '<colgroup>') === false);
if ($needsClass || $needsColgroup) {
    $pattern = '#(<!-- Items Table -->\s*)<table.*?>.*?</table>#s';
    $replacement = <<<'REPL'
$1<table class="receipt-table w-full text-sm border-collapse mt-6">
        <colgroup>
            <col><col><col><col>
        </colgroup>
        <thead>
            <tr class="bg-brand-light border-b-2 border-gray-300">
                <th><span class="font-mono text-xs">Item ID</span></th>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
            </tr>
        </thead>
        <tbody><?php foreach ($orderItems as $it): ?>
            <tr class="border-b border-gray-200">
                <td class="font-mono text-xs"><?php echo htmlspecialchars($it['sku'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($it['itemName'] ?? ($it['sku'] ?? '')); ?></td>
                <td><?php echo $it['quantity'] ?? 0; ?></td>
                <td>$<?php echo number_format($it['price'] ?? 0, 2); ?></td>
            </tr><?php endforeach; ?>
        </tbody>
    </table>
REPL;
    $src2 = preg_replace($pattern, $replacement, $src, 1, $count);
    if ($src2 !== null && $src2 !== $src) {
        $src = $src2;
        $changed = true;
    }
}

// 4) Ensure monospace Item ID header if older header retained
$src2 = str_replace('<th>Item ID</th>', '<th><span class="font-mono text-xs">Item ID</span></th>', $src);
if ($src2 !== $src) {
    $src = $src2;
    $changed = true;
}

if ($changed) {
    file_put_contents($file, $src);
    echo "Patched receipt.php\nBackup: $backup\n";
} else {
    echo "No changes needed.\nBackup: $backup\n";
}
