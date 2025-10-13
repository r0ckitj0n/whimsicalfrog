<?php

// scripts/dev/check-env.php
// Non-sensitive environment check: prints which env vars are present without values.
// Usage: php scripts/dev/check-env.php

// Load loader that pulls in .env from project root
require_once __DIR__ . '/../../config.php';

$keys = [
    'MYSQL_HOST_LOCAL', 'MYSQL_USER_LOCAL', 'MYSQL_PASS_LOCAL', 'MYSQL_DB_LOCAL',
    'MYSQL_HOST_REMOTE', 'MYSQL_USER_REMOTE', 'MYSQL_PASS_REMOTE', 'MYSQL_DB_REMOTE',
    'NODE_ENV',
    'VITE_DEV_PORT', 'VITE_HMR_PORT', 'PORT',
    'WF_VITE_DEV', 'WF_VITE_DISABLE_DEV', 'WF_VITE_ORIGIN', 'WF_PUBLIC_BASE', 'WF_BACKEND_ORIGIN',
];

$report = [
    'cwd' => getcwd(),
    'time' => date('c'),
    'loaded_from' => realpath(__DIR__ . '/../../.env') ?: null,
    'keys' => []
];

foreach ($keys as $k) {
    $present = getenv($k) !== false || isset($_ENV[$k]) || isset($_SERVER[$k]);
    $report['keys'][] = [
        'key' => $k,
        'present' => (bool) $present,
    ];
}

// Only send header in non-CLI contexts to avoid warnings
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
