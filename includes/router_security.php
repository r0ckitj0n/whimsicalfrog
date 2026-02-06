<?php
/**
 * Router Security for WhimsicalFrog
 * Handles restricted files, extensions, and directory access.
 */

function handleRouterSecurity(string $requestedPath)
{
    // Block file extensions like .sql, .env, .log, archives, and source maps
    if (preg_match('#\.(sql|sqlite|db|env|ini|log|bak|old|zip|tar|gz|7z|rar|bk|bkp|map)$#i', $requestedPath)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden\n";
        exit;
    }

    // Block hidden dotfiles except the ACME/.well-known path
    if (preg_match('#^/\.(?!well-known/)#', $requestedPath)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden\n";
        exit;
    }

    // Block sensitive directories from being served directly
    $denyPrefixes = ['/backups/', '/scripts/', '/documentation/', '/logs/', '/reports/', '/config/', '/.git/', '/vendor/'];
    foreach ($denyPrefixes as $prefix) {
        if (strpos($requestedPath, $prefix) === 0) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Forbidden\n";
            exit;
        }
    }
}
