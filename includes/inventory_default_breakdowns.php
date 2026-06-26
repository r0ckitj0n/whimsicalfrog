<?php

declare(strict_types=1);

require_once __DIR__ . '/item_price_sync.php';

/**
 * Seed editable manual breakdown rows for a SKU without changing the prices
 * the admin just submitted for the item.
 */
function wf_seed_default_breakdowns(string $sku, float $costPrice, float $retailPrice): void
{
    if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku)) {
        return;
    }

    $costFactorsTableExists = wf_table_exists('cost_factors');
    if ($costFactorsTableExists) {
        $existingCostFactors = Database::queryOne(
            "SELECT COUNT(*) AS c FROM cost_factors WHERE sku = ?",
            [$sku]
        );
        if (((int) ($existingCostFactors['c'] ?? 0)) === 0) {
            $defaultCostFactors = [
                ['category' => 'materials', 'label' => 'Manual Materials'],
                ['category' => 'labor', 'label' => 'Manual Labor'],
                ['category' => 'energy', 'label' => 'Manual Energy'],
                ['category' => 'equipment', 'label' => 'Manual Equipment']
            ];
            $costParts = wf_split_amount_evenly($costPrice, count($defaultCostFactors));

            foreach ($defaultCostFactors as $idx => $factor) {
                Database::execute(
                    "INSERT INTO cost_factors (sku, category, label, cost, source, created_at, updated_at)
                     VALUES (?, ?, ?, ?, 'manual', NOW(), NOW())",
                    [$sku, $factor['category'], $factor['label'], $costParts[$idx] ?? 0.0]
                );
            }
        }

        wf_sync_item_cost_price_from_factors($sku);
    }

    $priceFactorsTableExists = wf_table_exists('price_factors');
    if ($priceFactorsTableExists) {
        $existingPriceFactors = Database::queryOne(
            "SELECT COUNT(*) AS c FROM price_factors WHERE sku = ?",
            [$sku]
        );
        if (((int) ($existingPriceFactors['c'] ?? 0)) === 0) {
            Database::execute(
                "INSERT INTO price_factors (sku, label, amount, type, explanation, source, created_at)
                 VALUES (?, 'Manual Retail', ?, 'final', '', 'manual', NOW())",
                [$sku, wf_money_amount($retailPrice)]
            );
        }

        wf_sync_item_retail_price_from_factors($sku);
    }
}

function wf_money_amount(float $amount): float
{
    return round(max(0.0, $amount), 2);
}

/**
 * Return currency-safe parts that sum exactly to the rounded input amount.
 *
 * @return float[]
 */
function wf_split_amount_evenly(float $amount, int $parts): array
{
    if ($parts < 1) {
        return [];
    }

    $totalCents = (int) round(wf_money_amount($amount) * 100);
    $baseCents = intdiv($totalCents, $parts);
    $remainder = $totalCents % $parts;
    $values = [];

    for ($idx = 0; $idx < $parts; $idx++) {
        $cents = $baseCents + ($idx < $remainder ? 1 : 0);
        $values[] = $cents / 100;
    }

    return $values;
}
