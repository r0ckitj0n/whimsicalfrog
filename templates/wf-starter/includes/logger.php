<?php

class Logger
{
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }
    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }
    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }
    public static function exception(Throwable $e, array $context = []): void
    {
        $ctx = $context + ['exception' => get_class($e), 'file' => $e->getFile(), 'line' => $e->getLine()];
        self::log('EXCEPTION', $e->getMessage(), $ctx);
    }
    protected static function log(string $level, string $message, array $context): void
    {
        $line = sprintf('[%s] %s %s %s', date('c'), $level, $message, $context ? json_encode($context) : '');
        error_log($line);
    }
}
