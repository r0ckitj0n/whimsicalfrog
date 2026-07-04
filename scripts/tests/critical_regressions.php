<?php

declare(strict_types=1);

final class Database
{
    /** @var array<string, array<string, mixed>> */
    public static array $items = [];
    /** @var array<int, array<string, mixed>> */
    public static array $costFactors = [];
    /** @var array<int, array<string, mixed>> */
    public static array $priceFactors = [];
    /** @var array<string, array<string, mixed>> */
    public static array $users = [];

    public static function reset(): void
    {
        self::$items = [];
        self::$costFactors = [];
        self::$priceFactors = [];
        self::$users = [];
    }

    public static function isAvailableQuick(float $timeout = 0.0): bool
    {
        return true;
    }

    /**
     * @param array<int, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql)) ?: $sql;
        $sku = isset($params[0]) ? (string) $params[0] : '';

        if (stripos($normalized, 'information_schema.tables') !== false) {
            $table = isset($params[0]) ? (string) $params[0] : '';
            return ['c' => in_array($table, ['cost_factors', 'price_factors'], true) ? 1 : 0];
        }

        if (stripos($normalized, 'FROM cost_factors') !== false) {
            $rows = array_values(array_filter(
                self::$costFactors,
                static fn (array $row): bool => (string) ($row['sku'] ?? '') === $sku
            ));
            if (stripos($normalized, 'SUM(cost)') !== false) {
                return [
                    'c' => count($rows),
                    'total' => array_sum(array_map(static fn (array $row): float => (float) $row['cost'], $rows)),
                ];
            }
            return ['c' => count($rows)];
        }

        if (stripos($normalized, 'FROM price_factors') !== false) {
            $rows = array_values(array_filter(
                self::$priceFactors,
                static function (array $row) use ($sku): bool {
                    $type = strtolower((string) ($row['type'] ?? ''));
                    return (string) ($row['sku'] ?? '') === $sku && !in_array($type, ['analysis', 'meta'], true);
                }
            ));
            if (stripos($normalized, 'SUM(amount)') !== false) {
                return [
                    'c' => count($rows),
                    'total' => array_sum(array_map(static fn (array $row): float => (float) $row['amount'], $rows)),
                ];
            }
            return ['c' => count($rows)];
        }

        if (stripos($normalized, 'FROM users') !== false) {
            $userId = isset($params[0]) ? (string) $params[0] : '';
            return self::$users[$userId] ?? null;
        }

        return null;
    }

    /**
     * @param array<int, mixed> $params
     */
    public static function execute(string $sql, array $params = []): int
    {
        $normalized = preg_replace('/\s+/', ' ', trim($sql)) ?: $sql;

        if (stripos($normalized, 'INSERT INTO cost_factors') === 0) {
            self::$costFactors[] = [
                'sku' => (string) $params[0],
                'category' => (string) $params[1],
                'label' => (string) $params[2],
                'cost' => (float) $params[3],
            ];
            return 1;
        }

        if (stripos($normalized, 'INSERT INTO price_factors') === 0) {
            self::$priceFactors[] = [
                'sku' => (string) $params[0],
                'label' => 'Manual Retail',
                'amount' => (float) $params[1],
                'type' => 'final',
            ];
            return 1;
        }

        if (stripos($normalized, 'UPDATE items SET cost_price = ? WHERE sku = ?') === 0) {
            self::$items[(string) $params[1]]['cost_price'] = (float) $params[0];
            return 1;
        }

        if (stripos($normalized, 'UPDATE items SET retail_price = ? WHERE sku = ?') === 0) {
            self::$items[(string) $params[1]]['retail_price'] = (float) $params[0];
            return 1;
        }

        throw new RuntimeException('Unexpected SQL in regression test: ' . $sql);
    }
}

require_once __DIR__ . '/../../includes/auth_cookie.php';
require_once __DIR__ . '/../../includes/helpers/AuthSessionHelper.php';
require_once __DIR__ . '/../../includes/inventory_default_breakdowns.php';

function wf_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function wf_test_assert_float(float $expected, float $actual, string $message): void
{
    wf_test_assert(abs($expected - $actual) < 0.001, $message . " Expected {$expected}, got {$actual}");
}

function wf_test_signed_cookie(string $uid, string $secret): string
{
    $ts = (string) time();
    return base64_encode(json_encode([
        'u' => $uid,
        't' => $ts,
        's' => hash_hmac('sha256', $uid . '|' . $ts, $secret),
        'v' => 2,
    ]));
}

function wf_test_auth_cookie_requires_configured_secret(): void
{
    putenv('WF_AUTH_SECRET');
    $_ENV['WF_AUTH_SECRET'] = '';
    $_SERVER['HTTP_HOST'] = 'localhost';

    $forged = wf_test_signed_cookie('1', 'wf_auth_fallback_secret_2025_09');
    wf_test_assert(wf_auth_parse_cookie($forged) === null, 'Fallback-secret auth cookie must not parse when WF_AUTH_SECRET is unset');

    try {
        wf_auth_make_cookie('1');
    } catch (RuntimeException $e) {
        return;
    }

    throw new RuntimeException('Issuing a persistent auth cookie without WF_AUTH_SECRET must fail closed');
}

function wf_test_reconstruction_requires_verified_user_row(): void
{
    putenv('WF_AUTH_SECRET=critical_regression_secret');
    $_ENV['WF_AUTH_SECRET'] = 'critical_regression_secret';
    $_SERVER['HTTP_HOST'] = 'localhost';
    Database::reset();

    [$cookie] = wf_auth_make_cookie('42');
    $_SESSION = [];
    $_COOKIE = [wf_auth_cookie_name() => $cookie];
    AuthSessionHelper::reconstructSessionFromCookie();
    wf_test_assert(empty($_SESSION['user']), 'Auth reconstruction must not create a minimal user session without a DB row');

    Database::$users['42'] = [
        'id' => '42',
        'username' => 'admin-user',
        'email' => 'admin@example.test',
        'role' => 'admin',
        'first_name' => 'Admin',
        'last_name' => 'User',
        'phone_number' => null,
    ];
    [$cookie] = wf_auth_make_cookie('42');
    $_SESSION = [];
    $_COOKIE = [wf_auth_cookie_name() => $cookie];
    AuthSessionHelper::reconstructSessionFromCookie();
    wf_test_assert(($_SESSION['user']['username'] ?? null) === 'admin-user', 'Verified users must still reconstruct full sessions');
    wf_test_assert(($_SESSION['user']['role'] ?? null) === 'admin', 'Verified reconstruction must preserve role');
}

function wf_test_default_breakdowns_preserve_submitted_prices(): void
{
    putenv('WF_AUTH_SECRET=critical_regression_secret');
    Database::reset();
    $sku = 'WF-TEST-001';
    Database::$items[$sku] = ['cost_price' => 12.34, 'retail_price' => 45.67];

    wf_seed_default_breakdowns($sku, 12.34, 45.67);

    wf_test_assert(count(Database::$costFactors) === 4, 'Default cost breakdown rows should be seeded once');
    wf_test_assert(count(Database::$priceFactors) === 1, 'Default retail breakdown row should be seeded once');
    wf_test_assert_float(12.34, (float) Database::$items[$sku]['cost_price'], 'Default cost breakdown sync must preserve submitted cost');
    wf_test_assert_float(45.67, (float) Database::$items[$sku]['retail_price'], 'Default retail breakdown sync must preserve submitted retail price');
}

wf_test_auth_cookie_requires_configured_secret();
wf_test_reconstruction_requires_verified_user_row();
wf_test_default_breakdowns_preserve_submitted_prices();

echo "critical_regressions: ok\n";
