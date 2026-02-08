<?php
/**
 * Analytics Tracking Logic
 */
require_once __DIR__ . '/../session.php';

function getAnalyticsSessionId()
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_init([
            'name' => 'PHPSESSID',
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    $sessionId = session_id();
    if ($sessionId === '') {
        throw new RuntimeException('Unable to establish analytics session id');
    }
    return $sessionId;
}

function trackVisit()
{
    $input = json_decode(file_get_contents('php://input'), true);
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
