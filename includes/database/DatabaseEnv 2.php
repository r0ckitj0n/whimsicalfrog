<?php

/**
 * Database Environment and Discovery
 */
class DatabaseEnv
{
    private static bool $envLoaded = false;

    public static function ensureEnvLoaded(): void
    {
        if (self::$envLoaded) {
            return;
        }

        // Candidate paths for .env relative to this file (includes/database/)
        $candidates = [
            __DIR__ . '/../../../.env',       // Root (standard prod/dev)
            __DIR__ . '/../../.env',          // includes/ (alternative root)
            dirname($_SERVER['SCRIPT_FILENAME'] ?? '') . '/.env',        // Script dir
        ];

        $envPath = null;
        foreach ($candidates as $path) {
            if (is_readable($path) && !is_dir($path)) {
                $envPath = $path;
                break;
            }
        }

        if ($envPath) {
            $content = file_get_contents($envPath);
            // Strip Bom
            $content = str_replace("\xEF\xBB\xBF", "", $content);
            $lines = explode("\n", str_replace("\r", "", $content));

            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                    continue;
                }

                [$k, $v] = array_map('trim', explode('=', $line, 2));

                // Remove inline comments
                if (strpos($v, '#') !== false && !preg_match('/^["\']/', $v)) {
                    $v = trim(explode('#', $v)[0]);
                }

                $len = strlen($v);
                if ($len >= 2) {
                    $first = $v[0];
                    $last = $v[$len - 1];
                    if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                        $v = substr($v, 1, -1);
                    }
                }
                putenv("{$k}={$v}");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }

        self::$envLoaded = true;
    }

    public static function isDevelopmentEnvironment(): bool
    {
        $forceLocal = getenv('WF_DB_FORCE_LOCAL') ?: ($_SERVER['WF_DB_FORCE_LOCAL'] ?? null);
        if (self::isTruthyFlag($forceLocal)) {
            return true;
        }

        $forceLive = getenv('WF_DB_FORCE_LIVE') ?: ($_SERVER['WF_DB_FORCE_LIVE'] ?? null);
        if (self::isTruthyFlag($forceLive) && !self::isLocalDevContext()) {
            return false;
        }

        $whfEnv = getenv('WHF_ENV') ?: ($_SERVER['WHF_ENV'] ?? '');
        $whfEnvLower = strtolower($whfEnv);
        if ($whfEnvLower === 'prod' || $whfEnvLower === 'production') {
            return false;
        }
        if (in_array($whfEnvLower, ['local', 'dev', 'development'], true)) {
            return true;
        }

        if (getenv('PGHOST') && getenv('PGDATABASE') && getenv('PGUSER') && getenv('PGPASSWORD')) {
            return true;
        }

        if (self::isLocalDevContext()) {
            return true;
        }

        return PHP_SAPI === 'cli';
    }

    private static function isLocalDevContext(): bool
    {
        if (PHP_SAPI === 'cli-server') {
            return true;
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return false;
        }

        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, '192.168.') !== false) {
            return true;
        }
        if (preg_match('/\.(local|test)(:|$)/', $host)) {
            return true;
        }

        return false;
    }

    private static function isTruthyFlag($value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
