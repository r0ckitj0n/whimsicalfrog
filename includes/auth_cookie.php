<?php
// Stateless auth cookie helper. Allows reconstructing session when PHP sessions are flaky.
// Cookie: WF_AUTH contains base64(userId)|base64(ts)|base64(sig) where sig = HMAC_SHA256(userId|ts, secret)

function wf_auth_secret(): string {
    $secret = getenv('WF_AUTH_SECRET');
    if (!$secret) { $secret = 'wf_auth_fallback_secret_2025_09'; }
    return $secret;
}

function wf_auth_cookie_name(): string { return 'WF_AUTH'; }

function wf_auth_make_cookie($userId, int $ttlSeconds = 60*60*24*7): array {
    $uid = (string)$userId;
    $ts = (string) time();
    $data = $uid . '|' . $ts;
    $sig = hash_hmac('sha256', $data, wf_auth_secret());
    $val = base64_encode($uid) . '|' . base64_encode($ts) . '|' . base64_encode($sig);
    $expires = time() + $ttlSeconds;
    return [$val, $expires];
}

function wf_auth_parse_cookie(?string $cookieVal): ?array {
    if (!$cookieVal) return null;
    $parts = explode('|', $cookieVal);
    if (count($parts) !== 3) return null;
    $uid = base64_decode($parts[0], true);
    $ts  = base64_decode($parts[1], true);
    $sig = base64_decode($parts[2], true);
    if ($uid === false || $ts === false || $sig === false) return null;
    $data = $uid . '|' . $ts;
    $calc = hash_hmac('sha256', $data, wf_auth_secret());
    if (!hash_equals($calc, $sig)) return null;
    // Optional: expire after 7 days
    if (!ctype_digit($ts) || (time() - (int)$ts) > 60*60*24*7) return null;
    return ['userId' => $uid, 'ts' => (int)$ts];
}

function wf_auth_set_cookie($userId, string $domain, bool $secure): void {
    [$val, $exp] = wf_auth_make_cookie($userId);
    $sameSite = $secure ? 'None' : 'Lax';
    $opts = [
        'expires' => $exp,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $sameSite,
    ];
    if (!empty($domain)) { $opts['domain'] = $domain; }
    @setcookie(wf_auth_cookie_name(), $val, $opts);
}

function wf_auth_clear_cookie(string $domain, bool $secure): void {
    $sameSite = $secure ? 'None' : 'Lax';
    $opts = [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => $sameSite,
    ];
    if (!empty($domain)) { $opts['domain'] = $domain; }
    @setcookie(wf_auth_cookie_name(), '', $opts);
}

// Non-HttpOnly client hint for UI sync: WF_AUTH_V carries minimal data
function wf_auth_client_cookie_name(): string { return 'WF_AUTH_V'; }

function wf_auth_set_client_hint($userId, ?string $role, string $domain, bool $secure): void {
    $payload = json_encode([ 'uid' => (string)$userId, 'role' => $role ? (string)$role : null ]);
    $sameSite = $secure ? 'None' : 'Lax';
    $opts = [
        'expires' => time() + 60*60*24*7,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => $sameSite,
    ];
    if (!empty($domain)) { $opts['domain'] = $domain; }
    @setcookie(wf_auth_client_cookie_name(), base64_encode($payload), $opts);
}

function wf_auth_clear_client_hint(string $domain, bool $secure): void {
    $sameSite = $secure ? 'None' : 'Lax';
    $opts = [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => false,
        'samesite' => $sameSite,
    ];
    if (!empty($domain)) { $opts['domain'] = $domain; }
    @setcookie(wf_auth_client_cookie_name(), '', $opts);
}
