<?php

declare(strict_types=1);

final class Database
{
    public static array $tables = [
        'cost_factors' => true,
        'price_factors' => true,
    ];
    public static array $users = [];
    public static array $items = [];
    public static array $costFactors = [];
    public static array $priceFactors = [];

    public static function reset(): void
    {
        self::$tables = [
            'cost_factors' => true,
            'price_factors' => true,
        ];
        self::$users = [];
        self::$items = [];
        self::$costFactors = [];
        self::$priceFactors = [];
    }

    public static function isAvailableQuick(float $timeout): bool
    {
        return true;
    }

    public static function queryOne(string $sql, array $params = []): ?array
    {
        if (str_contains($sql, 'information_schema.tables')) {
            $table = (string) ($params[0] ?? '');
            return ['c' => !empty(self::$tables[$table]) ? 1 : 0];
        }

        if (str_contains($sql, 'COUNT(*) AS c FROM cost_factors')) {
            $sku = (string) ($params[0] ?? '');
            $rows = array_values(array_filter(
                self::$costFactors,
                static fn (array $row): bool => $row['sku'] === $sku
            ));
            return ['c' => count($rows)];
        }

        if (str_contains($sql, 'COUNT(*) AS c FROM price_factors')) {
            $sku = (string) ($params[0] ?? '');
            $rows = array_values(array_filter(
                self::$priceFactors,
                static fn (array $row): bool => $row['sku'] === $sku
            ));
            return ['c' => count($rows)];
        }

        if (str_contains($sql, 'FROM users WHERE id = ?')) {
            $id = (string) ($params[0] ?? '');
            return self::$users[$id] ?? null;
        }

        return null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        if (str_starts_with($sql, 'INSERT INTO cost_factors')) {
            self::$costFactors[] = [
                'sku' => (string) $params[0],
                'category' => (string) $params[1],
                'label' => (string) $params[2],
                'cost' => (float) $params[3],
            ];
            return 1;
        }

        if (str_starts_with($sql, 'INSERT INTO price_factors')) {
            self::$priceFactors[] = [
                'sku' => (string) $params[0],
                'amount' => (float) $params[1],
            ];
            return 1;
        }

        if (str_starts_with($sql, 'UPDATE items SET cost_price = ?')) {
            self::$items[(string) $params[1]]['cost_price'] = (float) $params[0];
            return 1;
        }

        if (str_starts_with($sql, 'UPDATE items SET retail_price = ?')) {
            self::$items[(string) $params[1]]['retail_price'] = (float) $params[0];
            return 1;
        }

        return 0;
    }
}

require_once __DIR__ . '/../../includes/auth_cookie.php';
require_once __DIR__ . '/../../includes/helpers/AuthSessionHelper.php';
require_once __DIR__ . '/../../includes/inventory_default_breakdowns.php';

function wf_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function wf_make_test_auth_cookie(string $userId, string $secret): string
{
    $ts = (string) time();
    return base64_encode(json_encode([
        'u' => $userId,
        't' => $ts,
        's' => hash_hmac('sha256', $userId . '|' . $ts, $secret),
        'v' => 2,
    ]));
}

function wf_assert_money(float $actual, float $expected, string $message): void
{
    wf_assert(abs($actual - $expected) < 0.001, $message . " Expected {$expected}, got {$actual}.");
}

putenv('WF_AUTH_SECRET');
$legacyFallbackSecret = implode('', ['wf_auth_', 'fallback_', 'secret_', '2025_', '09']);
$forgedFallbackCookie = wf_make_test_auth_cookie('7', $legacyFallbackSecret);
wf_assert(wf_auth_parse_cookie($forgedFallbackCookie) === null, 'Fallback-signed WF_AUTH cookie must be rejected when WF_AUTH_SECRET is unset.');
try {
    wf_auth_set_cookie('7', '', false);
} catch (Throwable $e) {
    throw new RuntimeException('wf_auth_set_cookie must not throw when WF_AUTH_SECRET is unset: ' . $e->getMessage());
}

putenv('WF_AUTH_SECRET=critical-regression-test-secret');
$validCookie = wf_make_test_auth_cookie('7', 'critical-regression-test-secret');
$parsed = wf_auth_parse_cookie($validCookie);
wf_assert(is_array($parsed) && $parsed['user_id'] === '7', 'Valid WF_AUTH cookie should parse when WF_AUTH_SECRET is configured.');

Database::reset();
$_SESSION = [];
$_COOKIE = [wf_auth_cookie_name() => $validCookie];
AuthSessionHelper::reconstructSessionFromCookie();
wf_assert(empty($_SESSION['user']), 'Session reconstruction must fail closed when the user row is missing.');

Database::$users['7'] = [
    'id' => '7',
    'username' => 'admin',
    'email' => 'admin@example.test',
    'role' => 'admin',
    'first_name' => 'Ada',
    'last_name' => 'Admin',
    'phone_number' => null,
];
$_SESSION = [];
$_COOKIE = [wf_auth_cookie_name() => $validCookie];
AuthSessionHelper::reconstructSessionFromCookie();
wf_assert(($_SESSION['user']['role'] ?? null) === 'admin', 'Session reconstruction should use verified DB user rows.');

Database::reset();
Database::$items['WF-TEST-001'] = [
    'cost_price' => 12.50,
    'retail_price' => 24.99,
];
wf_seed_default_breakdowns('WF-TEST-001', 12.50, 24.99);
$seededCostTotal = array_sum(array_map(
    static fn (array $row): float => $row['sku'] === 'WF-TEST-001' ? (float) $row['cost'] : 0.0,
    Database::$costFactors
));
wf_assert_money($seededCostTotal, 12.50, 'Default cost factors should be seeded from the submitted cost price.');
wf_assert_money((float) (Database::$priceFactors[0]['amount'] ?? -1), 24.99, 'Default price factor should be seeded from the submitted retail price.');
wf_assert_money((float) Database::$items['WF-TEST-001']['cost_price'], 12.50, 'Default breakdown seeding must not overwrite item cost_price.');
wf_assert_money((float) Database::$items['WF-TEST-001']['retail_price'], 24.99, 'Default breakdown seeding must not overwrite item retail_price.');

Database::$items['WF-TEST-001']['cost_price'] = 15.00;
Database::$items['WF-TEST-001']['retail_price'] = 30.00;
wf_seed_default_breakdowns('WF-TEST-001', 15.00, 30.00);
wf_assert(count(Database::$costFactors) === 4, 'Existing cost factors should not be duplicated during item save.');
wf_assert(count(Database::$priceFactors) === 1, 'Existing price factors should not be duplicated during item save.');
wf_assert_money((float) Database::$items['WF-TEST-001']['cost_price'], 15.00, 'Existing factors must not resync over a submitted cost price during item save.');
wf_assert_money((float) Database::$items['WF-TEST-001']['retail_price'], 30.00, 'Existing factors must not resync over a submitted retail price during item save.');

$authDebugLog = __DIR__ . '/../../logs/auth_debug.log';
if (is_file($authDebugLog)) {
    @unlink($authDebugLog);
}

echo "critical regressions passed\n";
