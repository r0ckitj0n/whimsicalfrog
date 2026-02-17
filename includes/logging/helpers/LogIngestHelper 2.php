<?php
// includes/logging/helpers/LogIngestHelper.php

class LogIngestHelper
{
    /**
     * Ingest log entries from the client
     */
    public static function ingestClientLogs($entries)
    {
        if (!is_array($entries) || empty($entries)) {
            return ['success' => true, 'ingested' => 0];
        }

        // Ensure table exists
        LogMaintenanceHelper::createLogTable('client_logs', 'client_logs');

        $user = null;
        try { $user = AuthHelper::getCurrentUser(); } catch (Throwable $e) { $user = null; }
        $adminUserId = (is_array($user) && !empty($user['user_id'])) ? (int)$user['user_id'] : null;

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referer = $_SERVER['HTTP_REFERER'] ?? null;

        $max = 50;
        $count = 0;
        foreach ($entries as $e) {
            if ($count >= $max) break;
            if (!is_array($e)) continue;

            $level = strtolower((string)($e['level'] ?? 'info'));
            if (!in_array($level, ['debug','info','warn','error'], true)) {
                $level = 'info';
            }

            $message = $e['message'] ?? '';
            if (is_array($message) || is_object($message)) {
                $message = json_encode($message);
            }
            $message = (string)$message;
            if ($message === '') continue;

            $context = $e['context'] ?? null;
            $pageUrl = $e['page_url'] ?? $referer;

            Database::execute(
                "INSERT INTO client_logs (admin_user_id, level, message, context_data, page_url, user_agent, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $adminUserId,
                    $level,
                    $message,
                    $context !== null ? json_encode($context) : null,
                    $pageUrl,
                    $ua,
                    $ip
                ]
            );

            $count++;
        }

        return ['success' => true, 'ingested' => $count];
    }
}
