<?php
// includes/users_meta.php
// Lightweight helpers to manage flexible user metadata key-value pairs.

require_once dirname(__DIR__) . '/api/config.php';

if (!function_exists('get_user_meta_bulk')) {
    function get_user_meta_bulk($user_id): array {
        if ($user_id === null || $user_id === '') return [];
        try {
            Database::getInstance();
            $rows = Database::queryAll('SELECT meta_key, meta_value FROM users_meta WHERE user_id = ?', [$user_id]) ?: [];
            $out = [];
            foreach ($rows as $r) {
                $k = isset($r['meta_key']) ? (string)$r['meta_key'] : '';
                $v = isset($r['meta_value']) ? (string)$r['meta_value'] : '';
                if ($k !== '') $out[$k] = $v;
            }
            return $out;
        } catch (Throwable $e) {
            error_log('get_user_meta_bulk error: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('set_user_meta_many')) {
    function set_user_meta_many($user_id, array $assoc): bool {
        if ($user_id === null || $user_id === '' || empty($assoc)) return true;
        try {
            Database::getInstance();
            $sql = 'INSERT INTO users_meta (user_id, meta_key, meta_value, updated_at) VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), updated_at = NOW()';
            $ok = true;
            foreach ($assoc as $k => $v) {
                $k = (string)$k;
                if ($k === '') continue;
                $v = is_bool($v) ? ($v ? '1' : '0') : (string)$v;
                $res = Database::execute($sql, [$user_id, $k, $v]);
                if ($res === false) { $ok = false; }
            }
            return $ok;
        } catch (Throwable $e) {
            error_log('set_user_meta_many error: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('ensure_user_meta_table')) {
    function ensure_user_meta_table(): void {
        try {
            Database::getInstance();
            $sql = "CREATE TABLE IF NOT EXISTS users_meta (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(255) NOT NULL,
                meta_key VARCHAR(191) NOT NULL,
                meta_value TEXT NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_user_meta (user_id, meta_key),
                KEY idx_user (user_id),
                KEY idx_key (meta_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            Database::execute($sql, []);
        } catch (Throwable $e) {
            error_log('ensure_user_meta_table error: ' . $e->getMessage());
        }
    }
}
