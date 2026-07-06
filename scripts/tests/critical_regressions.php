<?php

declare(strict_types=1);

class Database
{
    public static array $users = [];
    public static array $items = [];
    public static array $costFactors = [];
    public static array $priceFactors = [];
    public static array $tables = [
        'cost_factors' => true,
        'price_factors' => true,
    ];

    public static function queryOne(string $sql, array $params = []): ?array
    {
        if (strpos($sql, 'information_schema.tables') !== false) {
            return ['c' => !empty(self::$tables[(string) ($params[0] ?? '')]) ? 1 : 0];
        }

        if (strpos($sql, 'FROM users') !== false) {
            return self::$users[(string) ($params[0] ?? '')] ?? null;
        }

        if (strpos($sql, 'FROM cost_factors') !== false) {
            $sku = (string) ($params[0] ?? '');
            $rows = array_values(array_filter(
                self::$costFactors,
                static fn (array $row): bool => $row['sku'] === $sku
            ));
            return [
                'c' => count($rows),
                'total' => array_sum(array_map(static fn (array $row): float => (float) $row['cost'], $rows)),
            ];
        }

        if (strpos($sql, 'FROM price_factors') !== false) {
            $sku = (string) ($params[0] ?? '');
            $rows = array_values(array_filter(
                self::$priceFactors,
                static fn (array $row): bool => $row['sku'] === $sku
            ));
            return [
                'c' => count($rows),
                'total' => array_sum(array_map(static fn (array $row): float => (float) $row['amount'], $rows)),
            ];
        }

        return null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        if (strpos($sql, 'INSERT INTO cost_factors') !== false) {
            self::$costFactors[] = [
                'sku' => (string) $params[0],
                'category' => (string) $params[1],
                'label' => (string) $params[2],
                'cost' => (float) $params[3],
            ];
            return 1;
        }

        if (strpos($sql, 'INSERT INTO price_factors') !== false) {
            self::$priceFactors[] = [
                'sku' => (string) $params[0],
                'amount' => (float) $params[1],
            ];
            return 1;
        }

        if (strpos($sql, 'UPDATE items SET cost_price') !== false) {
            self::$items[(string) $params[1]]['cost_price'] = (float) $params[0];
            return 1;
        }

        if (strpos($sql, 'UPDATE items SET retail_price') !== false) {
            self::$items[(string) $params[1]]['retail_price'] = (float) $params[0];
            return 1;
        }

        return 1;
    }
}

require_once __DIR__ . '/../../includes/auth_cookie.php';
require_once __DIR__ . '/../../includes/helpers/AuthSessionHelper.php';
require_once __DIR__ . '/../../includes/inventory_default_breakdowns.php';

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertMoney(float $expected, float $actual, string $message): void
{
    if (abs($expected - $actual) > 0.0001) {
        throw new RuntimeException($message . ' Expected ' . $expected . ', got ' . $actual);
    }
}

function signedCookieFor(string $uid, string $secret): string
{
    $ts = (string) time();
    return base64_encode(json_encode([
        'u' => $uid,
        't' => $ts,
        's' => hash_hmac('sha256', $uid . '|' . $ts, $secret),
        'v' => 2,
    ]));
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';

putenv('WF_AUTH_SECRET');
$fallbackSignedCookie = signedCookieFor('1', 'wf_auth_fallback_secret_2025_09');
assertSameValue(null, wf_auth_parse_cookie($fallbackSignedCookie), 'Fallback-signed cookies must be rejected without WF_AUTH_SECRET.');

putenv('WF_AUTH_SECRET=critical-regression-secret');
$cookie = wf_auth_make_cookie('42');
assertTrue(is_array($cookie), 'Configured WF_AUTH_SECRET should allow cookie creation.');
$parsed = wf_auth_parse_cookie($cookie[0]);
assertSameValue('42', $parsed['user_id'] ?? null, 'Signed auth cookie should parse with configured secret.');

Database::$users = [];
$_SESSION = [];
$_COOKIE = [wf_auth_cookie_name() => signedCookieFor('999', 'critical-regression-secret')];
AuthSessionHelper::reconstructSessionFromCookie();
assertTrue(empty($_SESSION['user']), 'Cookie reconstruction must not create a minimal session for a missing user row.');

Database::$users = [
    '7' => [
        'id' => '7',
        'username' => 'admin',
        'email' => 'admin@example.test',
        'role' => 'admin',
        'first_name' => 'Ada',
        'last_name' => 'Admin',
        'phone_number' => null,
    ],
];
$_SESSION = [];
$_COOKIE = [wf_auth_cookie_name() => signedCookieFor('7', 'critical-regression-secret')];
AuthSessionHelper::reconstructSessionFromCookie();
assertSameValue('admin', $_SESSION['user']['role'] ?? null, 'Valid cookie reconstruction should preserve the database user role.');

Database::$items = ['WF-TST-001' => ['cost_price' => 12.34, 'retail_price' => 45.67]];
Database::$costFactors = [];
Database::$priceFactors = [];
wf_seed_default_breakdowns('WF-TST-001', 12.34, 45.67);
assertMoney(12.34, Database::$items['WF-TST-001']['cost_price'], 'Seeded cost factors must preserve submitted cost_price.');
assertMoney(45.67, Database::$items['WF-TST-001']['retail_price'], 'Seeded price factors must preserve submitted retail_price.');

Database::$items = ['WF-TST-002' => ['cost_price' => 12.34, 'retail_price' => 45.67]];
Database::$costFactors = [['sku' => 'WF-TST-002', 'category' => 'materials', 'label' => 'Old Materials', 'cost' => 99.99]];
Database::$priceFactors = [['sku' => 'WF-TST-002', 'amount' => 88.88]];
wf_seed_default_breakdowns('WF-TST-002', 12.34, 45.67);
assertMoney(12.34, Database::$items['WF-TST-002']['cost_price'], 'Existing cost factors must not overwrite freshly submitted cost_price.');
assertMoney(45.67, Database::$items['WF-TST-002']['retail_price'], 'Existing price factors must not overwrite freshly submitted retail_price.');

echo "critical_regressions: ok\n";
