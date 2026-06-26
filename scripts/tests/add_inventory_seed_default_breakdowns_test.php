<?php

declare(strict_types=1);

final class Database
{
    /** @var array<int, array<string, mixed>> */
    public static array $costFactors = [];
    /** @var array<int, array<string, mixed>> */
    public static array $priceFactors = [];
    /** @var array<string, float> */
    public static array $items = [
        'cost_price' => 0.0,
        'retail_price' => 0.0,
    ];

    public static function queryOne(string $sql, array $params = []): ?array
    {
        if (strpos($sql, 'FROM cost_factors') !== false) {
            return [
                'c' => count(self::$costFactors),
                'total' => array_sum(array_map(static fn (array $row): float => (float) $row['cost'], self::$costFactors)),
            ];
        }

        if (strpos($sql, 'FROM price_factors') !== false) {
            return [
                'c' => count(self::$priceFactors),
                'total' => array_sum(array_map(static fn (array $row): float => (float) $row['amount'], self::$priceFactors)),
            ];
        }

        return null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        if (strpos($sql, 'INSERT INTO cost_factors') !== false) {
            self::$costFactors[] = [
                'sku' => $params[0],
                'category' => $params[1],
                'label' => $params[2],
                'cost' => (float) $params[3],
            ];
            return 1;
        }

        if (strpos($sql, 'INSERT INTO price_factors') !== false) {
            self::$priceFactors[] = [
                'sku' => $params[0],
                'amount' => (float) $params[1],
            ];
            return 1;
        }

        if (strpos($sql, 'UPDATE items SET cost_price') !== false) {
            self::$items['cost_price'] = (float) $params[0];
            return 1;
        }

        if (strpos($sql, 'UPDATE items SET retail_price') !== false) {
            self::$items['retail_price'] = (float) $params[0];
            return 1;
        }

        throw new RuntimeException('Unexpected SQL: ' . $sql);
    }
}

function wf_table_exists(string $table): bool
{
    return in_array($table, ['cost_factors', 'price_factors'], true);
}

require_once __DIR__ . '/../../includes/inventory_default_breakdowns.php';

function assertSameMoney(float $expected, float $actual, string $label): void
{
    if (abs($expected - $actual) > 0.001) {
        throw new RuntimeException(sprintf('%s: expected %.2f, got %.2f', $label, $expected, $actual));
    }
}

wf_seed_default_breakdowns('WF-TEST-001', 10.01, 25.99);

assertSameMoney(10.01, Database::$items['cost_price'], 'cost price should survive default breakdown seeding');
assertSameMoney(25.99, Database::$items['retail_price'], 'retail price should survive default breakdown seeding');
assertSameMoney(
    10.01,
    array_sum(array_map(static fn (array $row): float => (float) $row['cost'], Database::$costFactors)),
    'seeded cost factors should total the submitted cost'
);
assertSameMoney(25.99, (float) Database::$priceFactors[0]['amount'], 'seeded price factor should match submitted retail');

echo "add_inventory default breakdown seeding regression passed\n";
