<?php

declare(strict_types=1);

function wf_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$previousSecret = getenv('WF_AUTH_SECRET');
putenv('WF_AUTH_SECRET');

require_once __DIR__ . '/../../includes/auth_cookie.php';

$uid = '42';
$ts = (string) time();
$forgedFallbackCookie = base64_encode(json_encode([
    'u' => $uid,
    't' => $ts,
    's' => hash_hmac('sha256', $uid . '|' . $ts, 'wf_auth_fallback_secret_2025_09'),
    'v' => 2,
]));

wf_test_assert(
    wf_auth_parse_cookie($forgedFallbackCookie) === null,
    'WF_AUTH fallback-secret cookie must not authenticate when WF_AUTH_SECRET is unset.'
);

putenv('WF_AUTH_SECRET=unit-test-secret');
[$validCookie] = wf_auth_make_cookie($uid);
$parsed = wf_auth_parse_cookie($validCookie);
wf_test_assert(
    is_array($parsed) && ($parsed['user_id'] ?? null) === $uid,
    'WF_AUTH cookie signed with configured secret should parse.'
);

if ($previousSecret === false) {
    putenv('WF_AUTH_SECRET');
} else {
    putenv('WF_AUTH_SECRET=' . $previousSecret);
}

class Database
{
    /** @var array<string, bool> */
    public static array $tables = [
        'cost_factors' => true,
        'price_factors' => true,
    ];

    /** @var array<string, int> */
    public static array $counts = [
        'cost_factors' => 0,
        'price_factors' => 0,
    ];

    /** @var array<int, array{sql: string, params: array<int, mixed>}> */
    public static array $executions = [];

    /**
     * @param array<int, mixed> $params
     * @return array<string, mixed>|null
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        if (strpos($sql, 'information_schema.tables') !== false) {
            $tableName = (string) ($params[0] ?? '');
            return ['c' => isset(self::$tables[$tableName]) ? 1 : 0];
        }

        if (strpos($sql, 'FROM cost_factors') !== false) {
            return ['c' => self::$counts['cost_factors']];
        }

        if (strpos($sql, 'FROM price_factors') !== false) {
            return ['c' => self::$counts['price_factors']];
        }

        return null;
    }

    /**
     * @param array<int, mixed> $params
     */
    public static function execute(string $sql, array $params = []): int
    {
        self::$executions[] = ['sql' => $sql, 'params' => $params];
        return 1;
    }
}

require_once __DIR__ . '/../../includes/inventory_default_breakdowns.php';

Database::$executions = [];
Database::$counts = [
    'cost_factors' => 0,
    'price_factors' => 0,
];

wf_seed_default_breakdowns('WF-TEST-001', 12.34, 56.78);

wf_test_assert(count(Database::$executions) === 5, 'Expected four cost rows and one price row to be seeded.');
wf_test_assert(
    Database::$executions[0]['params'] === ['WF-TEST-001', 'materials', 'Manual Materials', 12.34],
    'Manual Materials row should carry the submitted cost price.'
);
wf_test_assert(
    Database::$executions[4]['params'] === ['WF-TEST-001', 56.78],
    'Manual Retail row should carry the submitted retail price.'
);

foreach (Database::$executions as $execution) {
    wf_test_assert(
        stripos($execution['sql'], 'UPDATE items SET') === false,
        'Default breakdown seeding must not overwrite items prices from factors.'
    );
}

Database::$executions = [];
Database::$counts = [
    'cost_factors' => 1,
    'price_factors' => 1,
];

wf_seed_default_breakdowns('WF-TEST-002', 10.00, 20.00);
wf_test_assert(Database::$executions === [], 'Existing breakdown rows should not be replaced by defaults.');

echo "critical_regressions: ok\n";
