<?php

declare(strict_types=1);

final class TestFailure extends RuntimeException
{
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new TestFailure($message);
    }
}

function assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new TestFailure($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_almost(float $expected, float $actual, string $message): void
{
    if (abs($expected - $actual) > 0.001) {
        throw new TestFailure($message . ' Expected ' . $expected . ', got ' . $actual);
    }
}

final class Database
{
    public static array $tables = [
        'cost_factors' => true,
        'price_factors' => true,
    ];
    public static array $costFactors = [];
    public static array $priceFactors = [];
    public static array $itemUpdates = [];
    public static array $users = [];
    public static bool $available = true;

    public static function reset(): void
    {
        self::$costFactors = [];
        self::$priceFactors = [];
        self::$itemUpdates = [];
        self::$users = [];
        self::$available = true;
    }

    public static function isAvailableQuick(float $timeout = 0.6): bool
    {
        return self::$available;
    }

    public static function queryOne(string $sql, array $params = []): ?array
    {
        if (strpos($sql, 'information_schema.tables') !== false) {
            $table = (string)($params[0] ?? '');
            return ['c' => !empty(self::$tables[$table]) ? 1 : 0];
        }

        if (strpos($sql, 'COUNT(*) AS c FROM cost_factors WHERE sku') !== false) {
            $sku = (string)$params[0];
            return ['c' => count(array_filter(self::$costFactors, static fn ($row): bool => $row['sku'] === $sku))];
        }

        if (strpos($sql, 'COUNT(*) AS c, COALESCE(SUM(cost), 0) AS total') !== false) {
            $sku = (string)$params[0];
            $rows = array_filter(self::$costFactors, static fn ($row): bool => $row['sku'] === $sku);
            return [
                'c' => count($rows),
                'total' => array_sum(array_map(static fn ($row): float => (float)$row['cost'], $rows)),
            ];
        }

        if (strpos($sql, 'COUNT(*) AS c FROM price_factors WHERE sku') !== false) {
            $sku = (string)$params[0];
            return ['c' => count(array_filter(self::$priceFactors, static fn ($row): bool => $row['sku'] === $sku))];
        }

        if (strpos($sql, 'COUNT(*) AS c, COALESCE(SUM(amount), 0) AS total') !== false) {
            $sku = (string)$params[0];
            $rows = array_filter(
                self::$priceFactors,
                static fn ($row): bool => $row['sku'] === $sku && !in_array(strtolower((string)$row['type']), ['analysis', 'meta'], true)
            );
            return [
                'c' => count($rows),
                'total' => array_sum(array_map(static fn ($row): float => (float)$row['amount'], $rows)),
            ];
        }

        if (strpos($sql, 'FROM users WHERE id = ?') !== false) {
            $uid = (string)$params[0];
            return self::$users[$uid] ?? null;
        }

        throw new RuntimeException('Unexpected queryOne SQL: ' . $sql);
    }

    public static function execute(string $sql, array $params = []): int
    {
        if (strpos($sql, 'INSERT INTO cost_factors') !== false) {
            self::$costFactors[] = [
                'sku' => (string)$params[0],
                'category' => (string)$params[1],
                'label' => (string)$params[2],
                'cost' => (float)$params[3],
            ];
            return 1;
        }

        if (strpos($sql, 'INSERT INTO price_factors') !== false) {
            self::$priceFactors[] = [
                'sku' => (string)$params[0],
                'label' => 'Manual Retail',
                'amount' => (float)$params[1],
                'type' => 'final',
            ];
            return 1;
        }

        if (strpos($sql, 'UPDATE items SET cost_price = ? WHERE sku = ?') !== false) {
            self::$itemUpdates[] = ['field' => 'cost_price', 'value' => (float)$params[0], 'sku' => (string)$params[1]];
            return 1;
        }

        if (strpos($sql, 'UPDATE items SET retail_price = ? WHERE sku = ?') !== false) {
            self::$itemUpdates[] = ['field' => 'retail_price', 'value' => (float)$params[0], 'sku' => (string)$params[1]];
            return 1;
        }

        throw new RuntimeException('Unexpected execute SQL: ' . $sql);
    }
}

require_once __DIR__ . '/../../includes/auth_cookie.php';
require_once __DIR__ . '/../../includes/helpers/AuthSessionHelper.php';
require_once __DIR__ . '/../../includes/inventory_default_breakdowns.php';

function fallback_cookie_for_user(string $uid): string
{
    $ts = (string)time();
    $sig = hash_hmac('sha256', $uid . '|' . $ts, 'wf_auth_fallback_secret_2025_09');
    return base64_encode(json_encode([
        'u' => $uid,
        't' => $ts,
        's' => $sig,
        'v' => 2,
    ]));
}

function test_auth_cookie_requires_configured_secret(): void
{
    putenv('WF_AUTH_SECRET');

    assert_same(null, wf_auth_parse_cookie(fallback_cookie_for_user('7')), 'Fallback-secret cookies must not validate');

    $threw = false;
    try {
        wf_auth_make_cookie('7');
    } catch (RuntimeException $e) {
        $threw = true;
    }
    assert_true($threw, 'Signing WF_AUTH cookies without WF_AUTH_SECRET must fail closed');

    putenv('WF_AUTH_SECRET=unit-test-secret');
    [$cookie] = wf_auth_make_cookie('7');
    $parsed = wf_auth_parse_cookie($cookie);
    assert_true(is_array($parsed), 'Configured-secret cookie should parse');
    assert_same('7', $parsed['user_id'], 'Configured-secret cookie should preserve user id');
}

function test_cookie_reconstruction_requires_user_row(): void
{
    putenv('WF_AUTH_SECRET=unit-test-secret');
    Database::reset();
    $_SESSION = [];
    $_COOKIE = [];
    [$cookie] = wf_auth_make_cookie('999');
    $_COOKIE[wf_auth_cookie_name()] = $cookie;

    AuthSessionHelper::reconstructSessionFromCookie();
    assert_true(empty($_SESSION['user']), 'Missing DB row must not create a minimal authenticated session');

    Database::$users['42'] = [
        'id' => '42',
        'username' => 'admin-user',
        'email' => 'admin@example.invalid',
        'role' => 'admin',
        'first_name' => null,
        'last_name' => null,
        'phone_number' => null,
    ];
    $_SESSION = [];
    $_COOKIE = [];
    [$cookie] = wf_auth_make_cookie('42');
    $_COOKIE[wf_auth_cookie_name()] = $cookie;

    AuthSessionHelper::reconstructSessionFromCookie();
    assert_same('42', (string)($_SESSION['user']['user_id'] ?? ''), 'Verified DB row should reconstruct session');
    assert_same('admin', (string)($_SESSION['user']['role'] ?? ''), 'Reconstructed session should include DB role');
}

function test_default_breakdown_seed_preserves_submitted_prices(): void
{
    Database::reset();

    wf_seed_default_breakdowns('WF-UT-001', 12.34, 45.67);

    assert_same(4, count(Database::$costFactors), 'Default cost seed should create four cost rows');
    assert_same(1, count(Database::$priceFactors), 'Default price seed should create one retail row');

    $costTotal = array_sum(array_map(static fn ($row): float => (float)$row['cost'], Database::$costFactors));
    $retailTotal = array_sum(array_map(static fn ($row): float => (float)$row['amount'], Database::$priceFactors));

    assert_almost(12.34, $costTotal, 'Seeded cost factors must sum to submitted cost');
    assert_almost(45.67, $retailTotal, 'Seeded price factors must sum to submitted retail');

    $costUpdate = array_values(array_filter(Database::$itemUpdates, static fn ($row): bool => $row['field'] === 'cost_price'))[0] ?? null;
    $retailUpdate = array_values(array_filter(Database::$itemUpdates, static fn ($row): bool => $row['field'] === 'retail_price'))[0] ?? null;

    assert_true(is_array($costUpdate), 'Cost sync update should run');
    assert_true(is_array($retailUpdate), 'Retail sync update should run');
    assert_almost(12.34, (float)$costUpdate['value'], 'Cost sync must preserve submitted cost');
    assert_almost(45.67, (float)$retailUpdate['value'], 'Retail sync must preserve submitted retail');
}

try {
    test_auth_cookie_requires_configured_secret();
    test_cookie_reconstruction_requires_user_row();
    test_default_breakdown_seed_preserves_submitted_prices();
    $logPath = __DIR__ . '/../../logs/auth_debug.log';
    if (is_file($logPath)) {
        @unlink($logPath);
    }
    echo "critical_regressions: ok\n";
} catch (Throwable $e) {
    fwrite(STDERR, "critical_regressions: failed: " . $e->getMessage() . "\n");
    exit(1);
}
