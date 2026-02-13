<?php
/**
 * Config Helper - Environment and DB building logic.
 */

function wf_detect_environment()
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (PHP_SAPI === 'cli')
        return true;
    return (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, '192.168.') !== false);
}

function wf_load_db_config($isLocal)
{
    if ($isLocal) {
        // Local defaults should be safe to commit (no secrets).
        $cfg = ['host' => '127.0.0.1', 'db' => 'whimsicalfrog', 'user' => 'root', 'pass' => '', 'port' => 3306, 'socket' => null];
        $ini = __DIR__ . '/../config/my.cnf';
        if (file_exists($ini)) {
            $inClient = false;
            foreach (file($ini) as $line) {
                $line = trim($line);
                if (preg_match('/^\[client\]/i', $line)) {
                    $inClient = true;
                    continue;
                }
                if ($inClient && preg_match('/^\[.*\]/', $line))
                    break;
                if ($inClient && strpos($line, '=') !== false) {
                    list($k, $v) = array_map('trim', explode('=', $line, 2));
                    $key = strtolower($k);
                    if (isset($cfg[$key]) || $key === 'password')
                        $cfg[$key === 'password' ? 'pass' : $key] = $v;
                }
            }
        }
        return [
            'host' => getenv('WF_DB_LOCAL_HOST') ?: $cfg['host'],
            'db' => getenv('WF_DB_LOCAL_NAME') ?: $cfg['db'],
            'user' => getenv('WF_DB_LOCAL_USER') ?: $cfg['user'],
            'pass' => getenv('WF_DB_LOCAL_PASS') ?: $cfg['pass'],
            'port' => (int) (getenv('WF_DB_LOCAL_PORT') ?: $cfg['port']),
            'socket' => getenv('WF_DB_LOCAL_SOCKET') ?: $cfg['socket']
        ];
    }
    return [
        // Live credentials must come from environment (.env on server or host-provided env vars).
        // Keep non-secret defaults (host/db/user) only if you want a "helpful" baseline; never commit passwords.
        'host' => getenv('WF_DB_LIVE_HOST') ?: 'db5017975223.hosting-data.io',
        'db' => getenv('WF_DB_LIVE_NAME') ?: 'dbs14295502',
        'user' => getenv('WF_DB_LIVE_USER') ?: 'dbu2826619',
        'pass' => getenv('WF_DB_LIVE_PASS') ?: '',
        'port' => (int) (getenv('WF_DB_LIVE_PORT') ?: 3306),
        'socket' => getenv('WF_DB_LIVE_SOCKET') ?: null
    ];
}

/**
 * Legacy wrapper for getting database configuration by environment.
 * @param string $env 'local' or 'live'
 * @return array
 */
function wf_get_db_config(string $env): array
{
    $isLocal = ($env === 'local');
    return wf_load_db_config($isLocal);
}
