<?php

/**
 * Shared configuration and environment detection for Vite assets
 */
class ViteConfig
{
    public static function getProjectRoot(): string
    {
        static $cachedRoot = null;
        if ($cachedRoot !== null)
            return $cachedRoot;

        // 1. Climb up from this file to find the first directory containing 'dist' AND 'includes'
        $dir = __DIR__;
        for ($i = 0; $i < 6; $i++) {
            if (file_exists($dir . '/dist') && file_exists($dir . '/includes')) {
                $cachedRoot = $dir;
                return $dir;
            }
            $parent = dirname($dir);
            if (!$parent || $parent === $dir)
                break;
            $dir = $parent;
        }

        // 2. Fallback to document root detection
        $cachedRoot = dirname(__DIR__, 3);
        return $cachedRoot;
    }

    public static function getViteOrigin(): string
    {
        $viteOrigin = getenv('WF_VITE_ORIGIN');
        if (!$viteOrigin && file_exists(__DIR__ . '/../../hot')) {
            $hotContents = @file_get_contents(__DIR__ . '/../../hot');
            $viteOrigin = is_string($hotContents) ? trim($hotContents) : '';
        }
        if (empty($viteOrigin)) {
            $viteOrigin = 'http://localhost:5173';
        }

        // Normalize host
        try {
            $parts = @parse_url($viteOrigin);
            if (is_array($parts) && isset($parts['host'])) {
                $hostIn = $parts['host'];
                if ($hostIn === '127.0.0.1' || $hostIn === '0.0.0.0') {
                    $scheme = $parts['scheme'] ?? 'http';
                    $host = 'localhost';
                    $port = isset($parts['port']) ? (':' . $parts['port']) : '';
                    $path = $parts['path'] ?? '';
                    $viteOrigin = $scheme . '://' . $host . $port . $path;
                }
                if (($hostIn === 'localhost' || $hostIn === '127.0.0.1' || $hostIn === '0.0.0.0') && isset($parts['port']) && (int) $parts['port'] === 8080) {
                    $currentHost = $_SERVER['HTTP_HOST'] ?? '';
                    if ($currentHost && stripos($currentHost, 'localhost') === false && stripos($currentHost, '127.0.0.1') === false) {
                        $viteOrigin = str_replace('localhost', explode(':', $currentHost)[0], $viteOrigin);
                    }
                }
            }
        } catch (Throwable $e) {
        }

        return $viteOrigin;
    }

    private static $isDevMemo = null;

    public static function isDevRequested(): bool
    {
        if (self::$isDevMemo !== null) {
            return self::$isDevMemo;
        }

        $requestedMode = isset($_GET['vite']) ? strtolower((string) $_GET['vite']) : '';
        $cookieMode = isset($_COOKIE['wf_vite_mode']) ? strtolower((string) $_COOKIE['wf_vite_mode']) : '';

        // Global kill switch for production safety
        $disableDevByEnv = getenv('WF_VITE_DISABLE_DEV') === '1' || getenv('WF_VITE_MODE') === 'prod';
        $projectRoot = self::getProjectRoot();
        $disableDevByFlag = file_exists($projectRoot . '/.disable-vite-dev');

        $result = true;
        if ($disableDevByEnv || $disableDevByFlag) {
            $result = false;
        } else {
            // Check for hot file
            $isLocal = (stripos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) || (stripos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false);
            $hotExists = file_exists($projectRoot . '/hot');

            if ($isLocal && $hotExists) {
                $result = ($requestedMode !== 'prod');
            } else if ($requestedMode === 'prod' || $cookieMode === 'prod') {
                $result = false;
            } else if ($requestedMode === 'dev' || $cookieMode === 'dev') {
                $result = true;
            } else {
                $result = $hotExists;
            }
        }

        self::$isDevMemo = $result;
        return $result;
    }

    public static function getBackendOrigin(): string
    {
        $backendOrigin = getenv('WF_BACKEND_ORIGIN');
        if (!$backendOrigin) {
            $proto = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
            $port = $_SERVER['SERVER_PORT'] ?? null;
            $needsPort = $port && !strpos($host, ':') && !(($proto === 'http' && (int) $port === 80) || ($proto === 'https' && (int) $port === 443));
            $backendOrigin = $proto . '://' . $host . ($needsPort ? (':' . $port) : '');
        }
        return rtrim($backendOrigin, '/');
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
            ? (strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443));
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        $payload = $context ? ($message . ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES)) : $message;
        switch (strtoupper($level)) {
            case 'ERROR':
                if (function_exists('logError')) {
                    logError($message, $context);
                    return;
                }
                break;
            case 'WARNING':
                if (function_exists('logWarning')) {
                    logWarning($message, $context);
                    return;
                }
                break;
            case 'INFO':
                if (function_exists('logInfo')) {
                    logInfo($message, $context);
                    return;
                }
                break;
        }
        error_log('[VITE ' . strtoupper($level) . '] ' . $payload);
    }
}
