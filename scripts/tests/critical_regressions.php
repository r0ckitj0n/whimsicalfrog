<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__, 2);

function wf_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require_once $repoRoot . '/includes/auth_cookie.php';

putenv('WF_AUTH_SECRET');
wf_test_assert(wf_auth_secret() === null, 'missing WF_AUTH_SECRET must not use a fallback secret');
wf_test_assert(wf_auth_make_cookie('1') === null, 'missing WF_AUTH_SECRET must not issue WF_AUTH cookies');

$timestamp = (string) time();
$fallbackSignature = hash_hmac('sha256', '1|' . $timestamp, 'wf_auth_fallback_secret_2025_09');
$fallbackCookie = base64_encode(json_encode([
    'u' => '1',
    't' => $timestamp,
    's' => $fallbackSignature,
    'v' => 2,
]));
wf_test_assert(wf_auth_parse_cookie($fallbackCookie) === null, 'repo-visible fallback-secret cookies must be rejected');

putenv('WF_AUTH_SECRET=short');
wf_test_assert(wf_auth_secret() === null, 'weak WF_AUTH_SECRET must not be accepted');

$strongSecret = str_repeat('a', WF_AUTH_SECRET_MIN_LENGTH);
putenv('WF_AUTH_SECRET=' . $strongSecret);
$cookie = wf_auth_make_cookie('42');
wf_test_assert(is_array($cookie), 'configured strong WF_AUTH_SECRET should issue a cookie');
$parsed = wf_auth_parse_cookie($cookie[0]);
wf_test_assert(is_array($parsed) && ($parsed['user_id'] ?? null) === '42', 'configured strong WF_AUTH_SECRET should parse its own cookie');

class Database
{
    public static function isAvailableQuick(float $timeout = 0.6): bool
    {
        return true;
    }

    public static function queryOne(string $sql, array $params = []): ?array
    {
        return null;
    }
}

require_once $repoRoot . '/includes/helpers/AuthSessionHelper.php';

$_SESSION = [];
$_COOKIE = [
    wf_auth_cookie_name() => $cookie[0],
];
$_SERVER['HTTP_HOST'] = 'localhost';
AuthSessionHelper::reconstructSessionFromCookie();
wf_test_assert(empty($_SESSION['user']), 'session reconstruction must not authenticate an unverified user row');

function wf_extract_php_function_body(string $source, string $functionName): string
{
    $tokens = token_get_all($source);
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }

        $name = null;
        for ($j = $i + 1; $j < $count; $j++) {
            $candidate = $tokens[$j];
            if (is_array($candidate) && $candidate[0] === T_STRING) {
                $name = $candidate[1];
                break;
            }
        }

        if ($name !== $functionName) {
            continue;
        }

        for ($k = $j + 1; $k < $count; $k++) {
            if ($tokens[$k] !== '{') {
                continue;
            }

            $depth = 1;
            $body = '';
            for ($m = $k + 1; $m < $count; $m++) {
                $part = $tokens[$m];
                $text = is_array($part) ? $part[1] : $part;
                if ($text === '{') {
                    $depth++;
                } elseif ($text === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return $body;
                    }
                }
                $body .= $text;
            }
        }
    }

    throw new RuntimeException("Function {$functionName} not found");
}

$addInventorySource = file_get_contents($repoRoot . '/api/add_inventory.php');
wf_test_assert(is_string($addInventorySource), 'add_inventory.php must be readable');
$seedBody = wf_extract_php_function_body($addInventorySource, 'wf_seed_default_breakdowns');
wf_test_assert(
    strpos($seedBody, 'wf_sync_item_cost_price_from_factors') === false,
    'default cost breakdown seeding must not overwrite item cost_price'
);
wf_test_assert(
    strpos($seedBody, 'wf_sync_item_retail_price_from_factors') === false,
    'default price breakdown seeding must not overwrite item retail_price'
);

echo "critical_regressions: ok\n";
