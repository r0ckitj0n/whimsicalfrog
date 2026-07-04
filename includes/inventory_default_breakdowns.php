<?php

declare(strict_types=1);

require_once __DIR__ . '/item_price_sync.php';

function wf_inventory_breakdown_table_exists(string $tableName): bool
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $tableName)) {
        return false;
    }

    $row = Database::queryOne(
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$tableName]
    );

    return ((int) ($row['c'] ?? 0)) > 0;
}

function wf_seed_default_breakdowns(string $sku, float $costPrice = 0.0, float $retailPrice = 0.0): void
{
    if (!preg_match('/^[A-Za-z0-9-]{3,64}$/', $sku)) {
        return;
    }

    $costPrice = round(max(0.0, $costPrice), 2);
    $retailPrice = round(max(0.0, $retailPrice), 2);

    if (wf_inventory_breakdown_table_exists('cost_factors')) {
        $existingCostFactors = Database::queryOne(
            'SELECT COUNT(*) AS c FROM cost_factors WHERE sku = ?',
            [$sku]
        );
        if (((int) ($existingCostFactors['c'] ?? 0)) === 0) {
            $defaultCostFactors = [
                ['category' => 'materials', 'label' => 'Manual Materials', 'cost' => $costPrice],
                ['category' => 'labor', 'label' => 'Manual Labor', 'cost' => 0.0],
                ['category' => 'energy', 'label' => 'Manual Energy', 'cost' => 0.0],
                ['category' => 'equipment', 'label' => 'Manual Equipment', 'cost' => 0.0],
            ];

            foreach ($defaultCostFactors as $factor) {
                Database::execute(
                    'INSERT INTO cost_factors (sku, category, label, cost, source, created_at, updated_at)
                     VALUES (?, ?, ?, ?, \'manual\', NOW(), NOW())',
                    [$sku, $factor['category'], $factor['label'], $factor['cost']]
                );
            }
        }
    }

    if (wf_inventory_breakdown_table_exists('price_factors')) {
        $existingPriceFactors = Database::queryOne(
            'SELECT COUNT(*) AS c FROM price_factors WHERE sku = ?',
            [$sku]
        );
        if (((int) ($existingPriceFactors['c'] ?? 0)) === 0) {
            Database::execute(
                'INSERT INTO price_factors (sku, label, amount, type, explanation, source, created_at)
                 VALUES (?, \'Manual Retail\', ?, \'final\', \'\', \'manual\', NOW())',
                [$sku, $retailPrice]
            );
        }
    }

    wf_sync_item_cost_price_from_factors($sku);
    wf_sync_item_retail_price_from_factors($sku);
}
