<?php

declare(strict_types=1);

class Database
{
    /** @var array<string, bool> */
    public static array $tables = [
        'cost_factors' => true,
        'price_factors' => true,
    ];

    /** @var array<int, array<string, mixed>> */
    public static array $costFactors = [];

    /** @var array<int, array<string, mixed>> */
    public static array $priceFactors = [];

    /** @var array<int, array<string, mixed>> */
    public static array $executed = [];

    public static bool $usersAvailable = true;

    /** @param array<int, mixed> $params */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        if (str_contains($sql, 'information_schema.tables')) {
            return ['c' => !empty(self::$tables[(string) ($params[0] ?? '')]) ? 1 : 0];
        }

        if (str_contains($sql, 'COUNT(*) AS c FROM cost_factors')) {
            $sku = (string) ($params[0] ?? '');
            $count = count(array_filter(self::$costFactors, static fn (array $row): bool => $row['sku'] === $sku));
            return ['c' => $count];
        }

        if (str_contains($sql, 'COUNT(*) AS c FROM price_factors')) {
            $sku = (string) ($params[0] ?? '');
            $count = count(array_filter(self::$priceFactors, static fn (array $row): bool => $row['sku'] === $sku));
            return ['c' => $count];
        }

        if (str_contains($sql, 'FROM users WHERE id = ?')) {
            if (!self::$usersAvailable) {
                throw new RuntimeException('simulated database outage');
            }
            return null;
        }

        throw new RuntimeException('Unexpected query: ' . $sql);
    }

    /** @param array<int, mixed> $params */
    public static function execute(string $sql, array $params = []): bool
    {
        self::$executed[] = ['sql' => $sql, 'params' => $params];

        if (str_contains($sql, 'INSERT INTO cost_factors')) {
            self::$costFactors[] = [
                'sku' => (string) $params[0],
                'category' => (string) $params[1],
                'label' => (string) $params[2],
                'cost' => (float) $params[3],
            ];
            return true;
        }

        if (str_contains($sql, 'INSERT INTO price_factors')) {
            self::$priceFactors[] = [
                'sku' => (string) $params[0],
                'amount' => (float) $params[1],
            ];
            return true;
        }

        throw new RuntimeException('Unexpected execute: ' . $sql);
    }

    public static function isAvailableQuick(float $timeoutSeconds): bool
    {
        return self::$usersAvailable;
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function build_cookie_with_secret(string $uid, string $secret): string
{
    $ts = (string) time();
    $data = $uid . '|' . $ts;
    return base64_encode(json_encode([
        'u' => $uid,
        't' => $ts,
        's' => hash_hmac('sha256', $data, $secret),
        'v' => 2,
    ], JSON_THROW_ON_ERROR));
}

require_once __DIR__ . '/../../includes/auth_cookie.php';
require_once __DIR__ . '/../../includes/helpers/AuthSessionHelper.php';
require_once __DIR__ . '/../../includes/inventory_default_breakdowns.php';

putenv('WF_AUTH_SECRET');
$forgedFallbackCookie = build_cookie_with_secret('1', 'wf_auth_fallback_secret_2025_09');
assert_true(wf_auth_parse_cookie($forgedFallbackCookie) === null, 'Fallback-signed auth cookie must not authenticate without WF_AUTH_SECRET.');

$secret = 'unit-test-secret-for-critical-regressions';
putenv('WF_AUTH_SECRET=' . $secret);
[$validCookie] = wf_auth_make_cookie('42');
$parsed = wf_auth_parse_cookie($validCookie);
assert_true(is_array($parsed) && $parsed['user_id'] === '42', 'Configured WF_AUTH_SECRET should still parse valid cookies.');

$_SESSION = [];
$_COOKIE = [wf_auth_cookie_name() => build_cookie_with_secret('42', $secret)];
Database::$usersAvailable = false;
AuthSessionHelper::reconstructSessionFromCookie();
assert_true(empty($_SESSION['user']), 'Auth cookie reconstruction must not create a minimal session when the user row cannot be verified.');

Database::$usersAvailable = true;
Database::$costFactors = [];
Database::$priceFactors = [];
Database::$executed = [];
wf_seed_default_breakdowns('WF-TEST-001', 12.34, 56.78);

$costTotal = array_sum(array_map(static fn (array $row): float => (float) $row['cost'], Database::$costFactors));
$retailTotal = array_sum(array_map(static fn (array $row): float => (float) $row['amount'], Database::$priceFactors));

assert_true(count(Database::$costFactors) === 4, 'Default cost breakdown should seed four manual rows.');
assert_true(abs($costTotal - 12.34) < 0.001, 'Default cost breakdown total should preserve submitted cost_price.');
assert_true(count(Database::$priceFactors) === 1, 'Default price breakdown should seed one manual retail row.');
assert_true(abs($retailTotal - 56.78) < 0.001, 'Default price breakdown total should preserve submitted retail_price.');

$executedSql = implode("\n", array_map(static fn (array $entry): string => (string) $entry['sql'], Database::$executed));
assert_true(!str_contains($executedSql, 'UPDATE items SET cost_price'), 'Default seeding must not overwrite item cost_price.');
assert_true(!str_contains($executedSql, 'UPDATE items SET retail_price'), 'Default seeding must not overwrite item retail_price.');

wf_seed_default_breakdowns('WF-TEST-001', 99.99, 199.99);
assert_true(count(Database::$costFactors) === 4, 'Default seeding should not duplicate existing cost factors.');
assert_true(count(Database::$priceFactors) === 1, 'Default seeding should not duplicate existing price factors.');

echo "critical regressions passed\n";
