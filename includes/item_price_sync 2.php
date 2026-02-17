<?php
/**
 * Keep items.cost_price and items.retail_price consistent with their breakdown tables.
 *
 * Source of truth for display across the site is:
 *   - items.cost_price
 *   - items.retail_price
 *
 * Breakdown tables (cost_factors / price_factors) are explanatory, but editing them
 * should immediately update items.*_price to prevent stale/contradictory UI.
 */

declare(strict_types=1);

/**
 * Sync items.cost_price to the sum(cost_factors.cost) for a SKU, but only if there are
 * one or more cost_factors rows (to avoid overwriting deliberate manual-only pricing).
 */
function wf_sync_item_cost_price_from_factors(string $sku): void
{
    $row = Database::queryOne(
        "SELECT COUNT(*) AS c, COALESCE(SUM(cost), 0) AS total
         FROM cost_factors
         WHERE sku = ?",
        [$sku]
    );
    $count = (int) ($row['c'] ?? 0);
    if ($count < 1) {
        return;
    }

    $total = (float) ($row['total'] ?? 0);
    $total = round($total, 2);

    Database::execute("UPDATE items SET cost_price = ? WHERE sku = ?", [$total, $sku]);
}

/**
 * Sync items.retail_price to the sum(price_factors.amount) for a SKU, but only if there are
 * one or more price_factors rows.
 */
function wf_sync_item_retail_price_from_factors(string $sku): void
{
    $row = Database::queryOne(
        "SELECT COUNT(*) AS c, COALESCE(SUM(amount), 0) AS total
         FROM price_factors
         WHERE sku = ?
           AND LOWER(COALESCE(type, '')) NOT IN ('analysis', 'meta')",
        [$sku]
    );
    $count = (int) ($row['c'] ?? 0);
    if ($count < 1) {
        return;
    }

    $total = (float) ($row['total'] ?? 0);
    $total = round($total, 2);

    Database::execute("UPDATE items SET retail_price = ? WHERE sku = ?", [$total, $sku]);
}
