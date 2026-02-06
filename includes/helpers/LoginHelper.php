<?php
/**
 * includes/helpers/LoginHelper.php
 * Helper class for login processing and validation
 */

class LoginHelper
{
    public static function parseInput(): ?array
    {
        $raw = file_get_contents('php://input');
        if ($raw && ($tmp = json_decode($raw, true)) && is_array($tmp))
            return $tmp;
        if (!empty($_POST))
            return $_POST;
        parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
        return $qs ?: null;
    }

    public static function validateAuth($username, $password): ?array
    {
        try {
            $user = Database::queryOne('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);
            if (!$user) {
                $user = Database::queryOne('SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1', [$username]);
            }
            if ($user && password_verify($password, $user['password'])) {
                return $user;
            }
        } catch (\Throwable $e) {
            error_log("Auth validation error: " . $e->getMessage());
        }
        return null;
    }

    public static function logTrace($event, $data = []): void
    {
        try {
            error_log('[AUTH-TRACE] ' . json_encode(array_merge(['event' => $event], $data)));
            // @reason: Diagnostic logging is non-critical - must not break authentication flow
        } catch (\Throwable $e) {
        }
    }
}
