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
    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    foreach ($ordersToDelete as $orderId) {
        echo "Processing order {$orderId}\n";
        $pdo->beginTransaction();
        try {
            // Get items for this order
            $stmt = $pdo->prepare("SELECT sku, quantity, color, size FROM order_items WHERE orderId = ?");
            $stmt->execute([$orderId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$items) {
                echo "  No order_items found; skipping stock restore\n";
            } else {
                // Restore stock for simple items (no color/size)
                $restoreStmt = $pdo->prepare("UPDATE items SET stockLevel = stockLevel + ? WHERE sku = ?");
                foreach ($items as $it) {
                    $sku = $it['sku'];
                    $qty = (int)($it['quantity'] ?? 0);
                    $color = $it['color'] ?? null;
                    $size = $it['size'] ?? null;

                    if ($qty <= 0) { continue; }

                    if (empty($color) && empty($size)) {
                        $restoreStmt->execute([$qty, $sku]);
                        echo "  Restored +{$qty} to items.stockLevel for SKU {$sku}\n";
                    } else {
                        // For color/size-specific items, this script does not attempt complex restoration.
                        // Log and skip to avoid incorrect adjustments.
                        echo "  WARNING: Item with color/size detected (SKU={$sku}, color={$color}, size={$size}). Manual restore recommended.\n";
                    }
                }
            }

            // Delete order_items and order
            $delItems = $pdo->prepare("DELETE FROM order_items WHERE orderId = ?");
            $delItems->execute([$orderId]);
            echo "  Deleted order_items rows: " . $delItems->rowCount() . "\n";

            $delOrder = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $delOrder->execute([$orderId]);
            echo "  Deleted orders rows: " . $delOrder->rowCount() . "\n";

            $pdo->commit();
            echo "  Done.\n";
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo "  ERROR: " . $e->getMessage() . "\n";
        }
    }

    echo "Cleanup complete.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Fatal: " . $e->getMessage() . "\n");
    exit(1);
}
