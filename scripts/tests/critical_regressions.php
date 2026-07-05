<?php

declare(strict_types=1);

final class Database
{
    /** @var array<string, array<string, mixed>> */
    public static array $users = [];
    /** @var array<int, array<string, mixed>> */
    public static array $costFactors = [];
    /** @var array<int, array<string, mixed>> */
    public static array $priceFactors = [];
    /** @var array<int, array<string, mixed>> */
    public static array $itemUpdates = [];
    /** @var array<string, bool> */
    public static array $tables = [
        'cost_factors' => true,
        'price_factors' => true,
    ];

    public static function reset(): void
    {
        self::$users = [];
        self::$costFactors = [];
        self::$priceFactors = [];
        self::$itemUpdates = [];
        self::$tables = [
            'cost_factors' => true,
            'price_factors' => true,
        ];
    }

    public static function isAvailableQuick(float $timeout = 0.6): bool
    {
        return true;
    }

    public static function queryOne(string $sql, array $params = []): ?array
    {
        if (stripos($sql, 'information_schema.tables') !== false) {
            $table = (string) ($params[0] ?? '');
            return ['c' => !empty(self::$tables[$table]) ? 1 : 0];
        }

        if (stripos($sql, 'FROM users') !== false) {
            $uid = (string) ($params[0] ?? '');
            return self::$users[$uid] ?? null;
        }

        if (stripos($sql, 'FROM cost_factors') !== false) {
            $sku = (string) ($params[0] ?? '');
            $count = count(array_filter(
                self::$costFactors,
                static fn(array $row): bool => (string) $row['sku'] === $sku
            ));
            return ['c' => $count];
        }

        if (stripos($sql, 'FROM price_factors') !== false) {
            $sku = (string) ($params[0] ?? '');
            $count = count(array_filter(
                self::$priceFactors,
                static fn(array $row): bool => (string) $row['sku'] === $sku
            ));
            return ['c' => $count];
        }

        return null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        if (stripos($sql, 'INSERT INTO cost_factors') !== false) {
            self::$costFactors[] = [
                'sku' => (string) $params[0],
                'category' => (string) $params[1],
                'label' => (string) $params[2],
                'cost' => (float) $params[3],
            ];
            return 1;
        }

        if (stripos($sql, 'INSERT INTO price_factors') !== false) {
            self::$priceFactors[] = [
                'sku' => (string) $params[0],
                'amount' => (float) $params[1],
            ];
            return 1;
        }

        if (stripos($sql, 'UPDATE items SET') !== false) {
            self::$itemUpdates[] = [
                'sql' => $sql,
                'params' => $params,
            ];
            return 1;
        }

        return 1;
    }
}

function wf_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__, 2);
$authLog = $root . '/logs/auth_debug.log';
$authLogExisted = is_file($authLog);
$authLogContents = $authLogExisted ? (string) file_get_contents($authLog) : null;
register_shutdown_function(static function () use ($authLog, $authLogExisted, $authLogContents): void {
    if ($authLogExisted) {
        file_put_contents($authLog, (string) $authLogContents);
        return;
    }

    if (is_file($authLog)) {
        unlink($authLog);
    }
});

require_once $root . '/includes/auth_cookie.php';
require_once $root . '/includes/helpers/AuthSessionHelper.php';
require_once $root . '/includes/inventory_default_breakdowns.php';

putenv('WF_AUTH_SECRET');
$uid = '42';
$ts = (string) time();
$fallbackSig = hash_hmac('sha256', $uid . '|' . $ts, 'wf_auth_fallback_secret_2025_09');
$fallbackCookie = base64_encode((string) json_encode([
    'u' => $uid,
    't' => $ts,
    's' => $fallbackSig,
    'v' => 2,
]));
wf_assert(wf_auth_parse_cookie($fallbackCookie) === null, 'Fallback-secret auth cookie must be rejected');

putenv('WF_AUTH_SECRET=unit-test-secret');
[$validCookie] = wf_auth_make_cookie('5');
$parsed = wf_auth_parse_cookie($validCookie);
wf_assert(is_array($parsed) && $parsed['user_id'] === '5', 'Configured-secret auth cookie must parse');

Database::reset();
$_SESSION = [];
$_COOKIE = [wf_auth_cookie_name() => $validCookie];
AuthSessionHelper::reconstructSessionFromCookie();
wf_assert(empty($_SESSION['user']), 'Signed cookie without a user row must not create a minimal session');

Database::$users['5'] = [
    'id' => '5',
    'username' => 'admin',
    'email' => 'admin@example.test',
    'role' => 'admin',
    'first_name' => null,
    'last_name' => null,
    'phone_number' => null,
];
$_SESSION = [];
$_COOKIE = [wf_auth_cookie_name() => $validCookie];
AuthSessionHelper::reconstructSessionFromCookie();
wf_assert(($_SESSION['user']['email'] ?? null) === 'admin@example.test', 'Verified user row should reconstruct the session');

Database::reset();
wf_seed_default_breakdowns('WF-TEST-001', 12.34, 56.78);
$costTotal = array_reduce(
    Database::$costFactors,
    static fn(float $sum, array $row): float => $sum + (float) $row['cost'],
    0.0
);
$priceTotal = array_reduce(
    Database::$priceFactors,
    static fn(float $sum, array $row): float => $sum + (float) $row['amount'],
    0.0
);
wf_assert(abs($costTotal - 12.34) < 0.001, 'Default cost factors must preserve submitted cost');
wf_assert(abs($priceTotal - 56.78) < 0.001, 'Default price factors must preserve submitted retail price');
wf_assert(Database::$itemUpdates === [], 'Default seeding must not overwrite items prices from factor sync');

echo "critical_regressions: ok\n";
