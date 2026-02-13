<?php
/**
 * Analytics Tracking Logic
 */

function getAnalyticsSessionId()
{
    // Analytics should not depend on PHP sessions.
    // Using session_start() here can block on session file locks and cause 30s timeouts (HTTP 500),
    // which breaks primary UX flows. Use a dedicated cookie instead.
    if (session_status() === PHP_SESSION_ACTIVE) {
        $sid = session_id();
        if (is_string($sid) && $sid !== '') return $sid;
    }

    $cookieKey = 'wf_analytics_sid';
    $existing = (string) ($_COOKIE[$cookieKey] ?? '');
    if ($existing !== '' && preg_match('/^[a-f0-9]{16,128}$/i', $existing)) {
        return $existing;
    }

    $sid = bin2hex(random_bytes(16));
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443) ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
    );

    // 180 days is plenty; analytics session id is not auth.
    @setcookie($cookieKey, $sid, [
        'expires' => time() + (180 * 24 * 60 * 60),
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$cookieKey] = $sid;
    return $sid;
}

function trackVisit()
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];
    $input = array_merge($_GET ?? [], $_POST ?? [], $input);

    $sessionId = getAnalyticsSessionId();
    $deviceInfo = parseUserAgent($_SERVER['HTTP_USER_AGENT'] ?? '');

    Database::execute(
        "INSERT INTO analytics_sessions (session_id, ip_address, user_agent, referrer, landing_page, utm_source, utm_medium, utm_campaign, utm_term, utm_content, device_type, browser, operating_system)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE last_activity = CURRENT_TIMESTAMP",
        [
            $sessionId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', 
            $input['referrer'] ?? '', $input['landing_page'] ?? '',
            $input['utm_source'] ?? '', $input['utm_medium'] ?? '', $input['utm_campaign'] ?? '',
            $input['utm_term'] ?? '', $input['utm_content'] ?? '',
            $deviceInfo['device_type'], $deviceInfo['browser'], $deviceInfo['os']
        ]
    );
    Response::success(['session_id' => $sessionId]);
}

function trackPageView()
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];
    $input = array_merge($_GET ?? [], $_POST ?? [], $input);

    $sessionId = getAnalyticsSessionId();
    
    Database::execute(
        "INSERT INTO page_views (session_id, page_url, page_title, page_type, item_sku) VALUES (?, ?, ?, ?, ?)",
        [$sessionId, $input['page_url'] ?? '', $input['page_title'] ?? '', $input['page_type'] ?? '', $input['item_sku'] ?? null]
    );
    
    Database::execute("UPDATE analytics_sessions SET total_page_views = total_page_views + 1 WHERE session_id = ?", [$sessionId]);
    Response::success();
}

function parseUserAgent($ua)
{
    $dt = preg_match('/Mobile|Android|iPhone|iPad/', $ua) ? (preg_match('/iPad/', $ua) ? 'tablet' : 'mobile') : 'desktop';
    $br = preg_match('/Chrome/', $ua) ? 'Chrome' : (preg_match('/Firefox/', $ua) ? 'Firefox' : (preg_match('/Safari/', $ua) ? 'Safari' : 'Unknown'));
    $os = preg_match('/Windows/', $ua) ? 'Windows' : (preg_match('/Macintosh/', $ua) ? 'macOS' : (preg_match('/Linux/', $ua) ? 'Linux' : 'Unknown'));
    return ['device_type' => $dt, 'browser' => $br, 'os' => $os];
}
