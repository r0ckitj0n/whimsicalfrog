<?php
// scripts/dev/cleanup-test-orders.php
// Removes specific test orders and restores stock levels for simple (no color/size) items.
// Usage: php scripts/dev/cleanup-test-orders.php

require_once __DIR__ . '/../../api/config.php';

$ordersToDelete = [
    '23H26P45',
    '23H26P93',
    '62H26P71',
];

try {
    Database::getInstance();

    foreach ($ordersToDelete as $orderId) {
        echo "Processing order {$orderId}\n";
        Database::beginTransaction();
        try {
            // Get items for this order
            $items = Database::queryAll("SELECT sku, quantity, color, size FROM order_items WHERE orderId = ?", [$orderId]);

            if (!$items) {
                echo "  No order_items found; skipping stock restore\n";
            } else {
                // Restore stock for simple items (no color/size)
                foreach ($items as $it) {
                    $sku = $it['sku'];
                    $qty = (int)($it['quantity'] ?? 0);
                    $color = $it['color'] ?? null;
                    $size = $it['size'] ?? null;

                    if ($qty <= 0) { continue; }

                    if (empty($color) && empty($size)) {
                        Database::execute("UPDATE items SET stockLevel = stockLevel + ? WHERE sku = ?", [$qty, $sku]);
                        echo "  Restored +{$qty} to items.stockLevel for SKU {$sku}\n";
                    } else {
                        // For color/size-specific items, this script does not attempt complex restoration.
                        // Log and skip to avoid incorrect adjustments.
                        echo "  WARNING: Item with color/size detected (SKU={$sku}, color={$color}, size={$size}). Manual restore recommended.\n";
                    }
                }
            }

            // Delete order_items and order
            $delItemsCount = Database::execute("DELETE FROM order_items WHERE orderId = ?", [$orderId]);
            echo "  Deleted order_items rows: " . ((int)$delItemsCount) . "\n";

            $delOrderCount = Database::execute("DELETE FROM orders WHERE id = ?", [$orderId]);
            echo "  Deleted orders rows: " . ((int)$delOrderCount) . "\n";

            Database::commit();
            echo "  Done.\n";
        } catch (Throwable $e) {
            Database::rollBack();
            echo "  ERROR: " . $e->getMessage() . "\n";
        }
    }

    echo "Cleanup complete.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Fatal: " . $e->getMessage() . "\n");
    exit(1);
}
